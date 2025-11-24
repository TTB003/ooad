<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$conn = db();
$message = '';
$errors = [];
$carId = (int)($_GET['car_id'] ?? 0);

// Lấy thông tin xe
$car = null;
if ($carId) {
    $carResult = pg_query_params($conn, 
        "SELECT x.ma_xe, x.hang_xe, x.loai_xe, x.mau_xe, x.nam_san_xuat, x.gia_ban, x.tinh_trang, x.mo_ta,
                (SELECT url FROM hinh_anh_xe WHERE ma_xe = x.ma_xe AND la_chinh = TRUE LIMIT 1) as hinh_anh,
                COALESCE(tk.so_luong_ton, 0) as so_luong_ton
         FROM xe x
         LEFT JOIN ton_kho tk ON x.ma_baotri = tk.ma_baotri
         WHERE x.ma_xe = $1",
        [$carId]
    );
    if ($carResult && $row = pg_fetch_assoc($carResult)) {
        $car = $row;
    } else {
        $errors[] = 'Không tìm thấy xe.';
    }
} else {
    $errors[] = 'Vui lòng chọn xe.';
}

// Lấy thông tin khách hàng
$customer = null;
$customerResult = pg_query_params($conn,
    'SELECT ma_khachhang, ho_ten, email, sdt, diachi FROM khach_hang WHERE ma_khachhang = $1',
    [$_SESSION['customer_id']]
);
if ($customerResult) {
    $customer = pg_fetch_assoc($customerResult);
}

// Thêm cột dia_chi_giao_hang vào don_hang nếu chưa có
pg_query($conn, "ALTER TABLE don_hang ADD COLUMN IF NOT EXISTS dia_chi_giao_hang TEXT");

// Xử lý thanh toán
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $car && $customer) {
    $phuongThuc = $_POST['phuong_thuc'] ?? '';
    $soLuong = (int)($_POST['so_luong'] ?? 1);
    $diaChiThanhToan = $_POST['dia_chi_thanh_toan'] ?? 'tai_cua_hang';
    $diaChiGiaoHang = trim($_POST['dia_chi_giao_hang'] ?? '');
    
    if (!in_array($phuongThuc, ['Tien_mat', 'Chuyen_khoan', 'The'])) {
        $errors[] = 'Vui lòng chọn phương thức thanh toán.';
    } elseif ($soLuong < 1) {
        $errors[] = 'Số lượng phải lớn hơn 0.';
    } elseif ($diaChiThanhToan === 'tai_nha' && empty($diaChiGiaoHang)) {
        $errors[] = 'Vui lòng nhập địa chỉ giao hàng.';
    } else {
        // Kiểm tra tồn kho
        $soLuongTon = (int)($car['so_luong_ton'] ?? 0);
        if ($soLuongTon < $soLuong) {
            $errors[] = 'Số lượng tồn kho không đủ. Hiện có: ' . $soLuongTon . ' xe.';
        } else {
            // Xác định địa chỉ giao hàng
            $finalDiaChi = '';
            if ($diaChiThanhToan === 'tai_nha') {
                $finalDiaChi = $diaChiGiaoHang;
                // Cập nhật địa chỉ khách hàng
                pg_query_params($conn,
                    'UPDATE khach_hang SET diachi = $1 WHERE ma_khachhang = $2',
                    [$diaChiGiaoHang, $_SESSION['customer_id']]
                );
            } else {
                $finalDiaChi = 'SGU, An Dương Vương, Q5, HCM';
            }
            
            // Tạo đơn hàng
            $tongTien = (float)$car['gia_ban'] * $soLuong;
            $orderResult = pg_query_params($conn,
                'INSERT INTO don_hang (ngay_lap, tong_tien, trang_thai, ma_khachhang, dia_chi_giao_hang) VALUES (CURRENT_DATE, $1, $2, $3, $4) RETURNING ma_donhang',
                [$tongTien, 'Cho_duyet', $_SESSION['customer_id'], $finalDiaChi]
            );
            
            if ($orderResult && $orderRow = pg_fetch_assoc($orderResult)) {
                $orderId = $orderRow['ma_donhang'];
                
                // Thêm chi tiết đơn hàng
                $detailResult = pg_query_params($conn,
                    'INSERT INTO chi_tiet_don_hang (ma_xe, ma_donhang, don_gia, so_luong) VALUES ($1, $2, $3, $4)',
                    [$carId, $orderId, $car['gia_ban'], $soLuong]
                );
                
                // Tạo thanh toán
                $paymentResult = pg_query_params($conn,
                    'INSERT INTO thanh_toan (phuong_thuc, so_lien, trang_thai_tt, ngay, ma_donhang) VALUES ($1, $2, $3, CURRENT_DATE, $4)',
                    [$phuongThuc, $tongTien, 'Cho', $orderId]
                );
                
                // Cập nhật tồn kho
                if ($detailResult && $paymentResult) {
                    $newQuantity = $soLuongTon - $soLuong;
                    pg_query_params($conn,
                        'UPDATE ton_kho SET so_luong_ban = COALESCE(so_luong_ban, 0) + $1, so_luong_ton = $2, ngay_cap_nhat = CURRENT_DATE WHERE ma_baotri = (SELECT ma_baotri FROM xe WHERE ma_xe = $3)',
                        [$soLuong, $newQuantity, $carId]
                    );
                    
                    // Tạo thông báo cho khách hàng
                    pg_query_params($conn,
                        'INSERT INTO thong_bao (tieu_de, noi_dung, loai_thongbao, ma_khachhang, ma_donhang) VALUES ($1, $2, $3, $4, $5)',
                        [
                            'Đơn hàng #' . $orderId . ' đã được tạo',
                            'Đơn hàng của bạn đã được tạo thành công. Tổng tiền: ' . number_format($tongTien, 0, ',', '.') . ' đ. Vui lòng chờ xác nhận từ nhân viên.',
                            'don_hang',
                            $_SESSION['customer_id'],
                            $orderId
                        ]
                    );
                    
                    // Tạo thông báo cho nhân viên và admin
                    $staffResult = pg_query($conn, "SELECT ma_nhanvien FROM nhan_vien WHERE chuc_vu = 'Nhân viên bán hàng' OR ma_loaitk IN (SELECT ma_loaitk FROM loai_tai_khoan WHERE ten_loai = 'Admin')");
                    if ($staffResult) {
                        while ($staff = pg_fetch_assoc($staffResult)) {
                            pg_query_params($conn,
                                'INSERT INTO thong_bao (tieu_de, noi_dung, loai_thongbao, ma_nhanvien, ma_donhang) VALUES ($1, $2, $3, $4, $5)',
                                [
                                    'Đơn hàng mới #' . $orderId,
                                    'Khách hàng ' . htmlspecialchars($customer['ho_ten']) . ' vừa đặt đơn hàng mới. Tổng tiền: ' . number_format($tongTien, 0, ',', '.') . ' đ.',
                                    'don_hang',
                                    $staff['ma_nhanvien'],
                                    $orderId
                                ]
                            );
                        }
                    }
                    
                    // Tạo thông báo hệ thống cho admin
                    pg_query_params($conn,
                        'INSERT INTO thong_bao (tieu_de, noi_dung, loai_thongbao, ma_donhang) VALUES ($1, $2, $3, $4)',
                        [
                            'Đơn hàng mới #' . $orderId,
                            'Có đơn hàng mới được tạo. Khách hàng: ' . htmlspecialchars($customer['ho_ten']) . '. Tổng tiền: ' . number_format($tongTien, 0, ',', '.') . ' đ.',
                            'he_thong',
                            $orderId
                        ]
                    );
                    
                    $message = 'Đặt hàng thành công! Mã đơn hàng: #' . $orderId . '. Vui lòng chờ xác nhận từ nhân viên.';
                } else {
                    $errors[] = 'Có lỗi xảy ra khi tạo đơn hàng.';
                }
            } else {
                $errors[] = 'Không thể tạo đơn hàng.';
            }
        }
    }
}

function formatCurrency($value) {
    return number_format($value, 0, ',', '.') . ' ₫';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thanh toán | AutoLux</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #ff4d4f;
            --dark: #0f172a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(145deg, #020817, #0f172a 70%);
            color: #0f172a;
            min-height: 100vh;
        }
        header {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(2, 8, 23, 0.9);
            backdrop-filter: blur(14px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 5vw;
        }
        header .logo {
            color: #fff;
            font-weight: 700;
            letter-spacing: 0.6px;
            font-size: 1.2rem;
        }
        nav {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        nav a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 0.95rem;
        }
        nav a:hover { color: #fff; }
        nav a.cta {
            padding: 8px 18px;
            border-radius: 999px;
            background: var(--primary);
            color: #fff;
            font-weight: 600;
        }
        .user-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.18);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid rgba(255,255,255,0.25);
            position: relative;
        }
        .user-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            min-width: 180px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            z-index: 100;
        }
        .user-menu.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .user-menu a {
            display: block;
            padding: 12px 18px;
            color: #0f172a;
            text-decoration: none;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(15,23,42,0.06);
        }
        .user-menu a:last-child {
            border-bottom: none;
        }
        .user-menu a:hover {
            background: #f8fafc;
        }
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            z-index: 1000;
            padding: 8px 0;
        }
        .dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .dropdown-item {
            display: block;
            padding: 10px 18px;
            color: #0f172a;
            text-decoration: none;
            font-size: 0.9rem;
            position: relative;
            cursor: pointer;
        }
        .dropdown-item:hover {
            background: #f8fafc;
        }
        .dropdown-item.has-submenu:hover .submenu {
            opacity: 1;
            visibility: visible;
            transform: translateX(0);
        }
        .submenu {
            position: absolute;
            left: 100%;
            top: 0;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            min-width: 300px;
            max-width: 400px;
            max-height: 500px;
            overflow-y: auto;
            opacity: 0;
            visibility: hidden;
            transform: translateX(-10px);
            transition: all 0.2s ease;
            padding: 12px;
            margin-left: 8px;
        }
        .submenu-item {
            display: block;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            text-decoration: none;
            color: #0f172a;
            border: 1px solid rgba(15,23,42,0.1);
            transition: all 0.2s;
        }
        .submenu-item:hover {
            border-color: var(--primary);
            background: #fef2f2;
        }
        .submenu-item img {
            width: 60px;
            height: 45px;
            object-fit: cover;
            border-radius: 6px;
            float: left;
            margin-right: 12px;
        }
        .submenu-item-content {
            display: flex;
            flex-direction: column;
        }
        .submenu-item-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }
        .submenu-item-price {
            font-size: 0.85rem;
            color: var(--primary);
            font-weight: 600;
        }
        main {
            padding: 40px 5vw;
            max-width: 1200px;
            margin: 0 auto;
        }
        .container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 32px;
        }
        @media (max-width: 968px) {
            .container {
                grid-template-columns: 1fr;
            }
        }
        .card {
            background: rgba(255,255,255,0.95);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 25px 70px rgba(15,23,42,0.2);
        }
        .car-info {
            display: flex;
            gap: 24px;
            margin-bottom: 24px;
        }
        .car-info img {
            width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: 12px;
        }
        .car-details h2 {
            margin-top: 0;
        }
        .alert {
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .alert-success {
            background: rgba(34,197,94,0.18);
            border: 1px solid rgba(34,197,94,0.3);
            color: #166534;
        }
        .alert-error {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.3);
            color: #991b1b;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid rgba(15,23,42,0.15);
            font-size: 0.95rem;
        }
        .price-summary {
            border-top: 2px solid rgba(15,23,42,0.1);
            padding-top: 20px;
            margin-top: 20px;
        }
        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .price-total {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
        }
        .btn {
            width: 100%;
            padding: 14px;
            border-radius: 999px;
            border: none;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            background: linear-gradient(120deg, #ff4d4f, #ff6b6b);
            color: #fff;
            margin-top: 20px;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn-back {
            display: inline-block;
            margin-bottom: 24px;
            padding: 10px 20px;
            border-radius: 999px;
            background: rgba(255,255,255,0.2);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    
    <main>
        <a href="index.php" class="btn-back">← Quay lại</a>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($errors): ?>
            <div class="alert alert-error">
                <ul style="margin:0;padding-left:18px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($car && $customer): ?>
            <div class="container">
                <div class="card">
                    <h2>Thông tin đơn hàng</h2>
                    
                    <div class="car-info">
                        <img src="<?= htmlspecialchars($car['hinh_anh'] ?: 'https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg') ?>" alt="Xe" onerror="this.src='https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg'">
                        <div class="car-details">
                            <h2><?= htmlspecialchars($car['hang_xe']) ?> <?= htmlspecialchars($car['loai_xe']) ?></h2>
                            <p><strong>Màu:</strong> <?= htmlspecialchars($car['mau_xe'] ?? 'N/A') ?></p>
                            <p><strong>Năm sản xuất:</strong> <?= htmlspecialchars($car['nam_san_xuat'] ?? 'N/A') ?></p>
                            <?php if ($car['mo_ta']): ?>
                                <p><strong>Mô tả:</strong> <?= htmlspecialchars($car['mo_ta']) ?></p>
                            <?php endif; ?>
                            <p style="font-size: 1.2rem; font-weight: 700; color: var(--primary); margin-top: 12px;">
                                <?= formatCurrency($car['gia_ban']) ?>
                            </p>
                        </div>
                    </div>
                    
                    <form method="post">
                        <div class="form-group">
                            <label for="so_luong">Số lượng</label>
                            <input type="number" id="so_luong" name="so_luong" min="1" max="<?= (int)($car['so_luong_ton'] ?? 0) ?>" value="1" required>
                            <small style="color: #64748b;">Tồn kho: <?= (int)($car['so_luong_ton'] ?? 0) ?> xe</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Thanh toán tại *</label>
                            <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 8px;">
                                <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
                                    <input type="radio" name="dia_chi_thanh_toan" value="tai_cua_hang" checked onchange="toggleAddressInput()">
                                    <span>Tại cửa hàng</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
                                    <input type="radio" name="dia_chi_thanh_toan" value="tai_nha" onchange="toggleAddressInput()">
                                    <span>Tại nhà</span>
                                </label>
                            </div>
                            <div id="address_display" style="margin-top: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; color: #0f172a;">
                                <strong>Địa chỉ:</strong> SGU, An Dương Vương, Q5, HCM
                            </div>
                            <div id="address_input" style="display: none; margin-top: 12px;">
                                <label for="dia_chi_giao_hang" style="display: block; margin-bottom: 8px; font-weight: 600;">Địa chỉ giao hàng *</label>
                                <textarea id="dia_chi_giao_hang" name="dia_chi_giao_hang" rows="3" placeholder="Nhập địa chỉ giao hàng chi tiết..." style="width: 100%; padding: 12px; border-radius: 12px; border: 1px solid rgba(15,23,42,0.15); font-size: 0.95rem; box-sizing: border-box;"></textarea>
                                <small style="color: #64748b; display: block; margin-top: 4px;">Địa chỉ này sẽ được lưu làm địa chỉ của bạn</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phuong_thuc">Phương thức thanh toán *</label>
                            <select id="phuong_thuc" name="phuong_thuc" required>
                                <option value="">-- Chọn phương thức --</option>
                                <option value="Tien_mat">Tiền mặt</option>
                                <option value="Chuyen_khoan">Chuyển khoản</option>
                                <option value="The">Thẻ</option>
                            </select>
                        </div>
                        
                        <div class="price-summary">
                            <div class="price-row">
                                <span>Đơn giá:</span>
                                <span id="don_gia"><?= formatCurrency($car['gia_ban']) ?></span>
                            </div>
                            <div class="price-row">
                                <span>Số lượng:</span>
                                <span id="so_luong_display">1</span>
                            </div>
                            <div class="price-row price-total">
                                <span>Tổng tiền:</span>
                                <span id="tong_tien"><?= formatCurrency($car['gia_ban']) ?></span>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">Xác nhận đặt hàng</button>
                    </form>
                </div>
                
                <div class="card">
                    <h3>Thông tin khách hàng</h3>
                    <p><strong>Họ tên:</strong> <?= htmlspecialchars($customer['ho_ten']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($customer['email']) ?></p>
                    <p><strong>Số điện thoại:</strong> <?= htmlspecialchars($customer['sdt']) ?></p>
                    <p><strong>Địa chỉ:</strong> <?= htmlspecialchars($customer['diachi'] ?? 'N/A') ?></p>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
        const soLuongInput = document.getElementById('so_luong');
        const donGia = <?= (float)($car['gia_ban'] ?? 0) ?>;
        
        function updatePrice() {
            const soLuong = parseInt(soLuongInput.value) || 1;
            const tongTien = donGia * soLuong;
            
            document.getElementById('so_luong_display').textContent = soLuong;
            document.getElementById('tong_tien').textContent = new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: 'VND'
            }).format(tongTien).replace('₫', '₫');
        }
        
        function toggleAddressInput() {
            const taiNha = document.querySelector('input[name="dia_chi_thanh_toan"][value="tai_nha"]').checked;
            const addressInput = document.getElementById('address_input');
            const addressDisplay = document.getElementById('address_display');
            
            if (taiNha) {
                addressInput.style.display = 'block';
                addressDisplay.style.display = 'none';
            } else {
                addressInput.style.display = 'none';
                addressDisplay.style.display = 'block';
            }
        }
        
        if (soLuongInput) {
            soLuongInput.addEventListener('input', updatePrice);
        }
        
        // Load danh sách xe theo hãng khi hover
        const brandItems = document.querySelectorAll('.dropdown-item.has-submenu');
        brandItems.forEach(item => {
            const brandName = item.getAttribute('data-brand');
            let submenu = item.querySelector('.submenu');
            if (!submenu) {
                submenu = document.createElement('div');
                submenu.className = 'submenu';
                item.appendChild(submenu);
            }
            item.addEventListener('mouseenter', function() {
                if (submenu.innerHTML === '') {
                    submenu.innerHTML = '<p style="padding: 12px; color: #64748b;">Đang tải...</p>';
                    fetch('get_cars_by_brand.php?brand=' + encodeURIComponent(brandName))
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.cars.length > 0) {
                                submenu.innerHTML = '';
                                data.cars.forEach(car => {
                                    const carItem = document.createElement('a');
                                    carItem.href = 'car_detail.php?id=' + car.ma_xe;
                                    carItem.className = 'submenu-item';
                                    carItem.innerHTML = `<img src="${car.hinh_anh || 'https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg'}" alt="${car.loai_xe}" onerror="this.src='https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg'"><div class="submenu-item-content"><div class="submenu-item-title">${car.hang_xe} ${car.loai_xe}</div><div class="submenu-item-price">${new Intl.NumberFormat('vi-VN').format(car.gia_ban)} ₫</div></div><div style="clear: both;"></div>`;
                                    submenu.appendChild(carItem);
                                });
                            } else {
                                submenu.innerHTML = '<p style="padding: 12px; color: #64748b;">Chưa có xe nào</p>';
                            }
                        })
                        .catch(error => {
                            submenu.innerHTML = '<p style="padding: 12px; color: #991b1b;">Lỗi tải dữ liệu</p>';
                        });
                }
            });
        });
        
        // User menu toggle
        const userChip = document.getElementById('userChip');
        const userMenu = document.getElementById('userMenu');
        if (userChip && userMenu) {
            userChip.addEventListener('click', function(e) {
                e.stopPropagation();
                userMenu.classList.toggle('active');
            });
            document.addEventListener('click', function(e) {
                if (!userChip.contains(e.target) && !userMenu.contains(e.target)) {
                    userMenu.classList.remove('active');
                }
            });
        }
        const staffChip = document.getElementById('staffChip');
        const staffMenu = document.getElementById('staffMenu');
        if (staffChip && staffMenu) {
            staffChip.addEventListener('click', function(e) {
                e.stopPropagation();
                staffMenu.classList.toggle('active');
            });
            document.addEventListener('click', function(e) {
                if (!staffChip.contains(e.target) && !staffMenu.contains(e.target)) {
                    staffMenu.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>

