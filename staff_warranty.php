<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$conn = db();
$currentPosition = $_SESSION['staff_position'] ?? '';

// Kiểm tra quyền truy cập
if ($currentPosition !== 'Nhân viên bảo hành/bảo dưỡng') {
    header('Location: admin.php');
    exit;
}

$message = '';
$errors = [];
$activeTab = $_GET['tab'] ?? 'warranty';

// Xử lý các action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_warranty') {
        $carId = (int)($_POST['car_id'] ?? 0);
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $fee = $_POST['fee'] ?? 0;
        $purchaseDate = $_POST['purchase_date'] ?? '';
        
        if ($carId && $customerId && $startDate && $endDate) {
            $result = pg_query_params($conn,
                'INSERT INTO bao_hiem_xe (ngay_bat_dau, ngay_het_bh, phi_bao_hiem, ngay_mua, ma_xe, ma_khachhang) VALUES ($1, $2, $3, $4, $5, $6)',
                [$startDate, $endDate, $fee, $purchaseDate, $carId, $customerId]
            );
            if ($result) {
                $message = 'Đã tạo phiếu bảo hành thành công.';
            } else {
                $errors[] = 'Không thể tạo phiếu bảo hành: ' . pg_last_error($conn);
            }
        } else {
            $errors[] = 'Vui lòng nhập đầy đủ thông tin.';
        }
    } elseif ($action === 'create_service') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $warrantyDate = $_POST['warranty_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        
        if ($customerId && $content && $warrantyDate) {
            $result = pg_query_params($conn,
                'INSERT INTO bao_hanh_dich_vu (noi_dung, ngay_baohanh, ngay_het_bh, ma_khachhang) VALUES ($1, $2, $3, $4)',
                [$content, $warrantyDate, $endDate, $customerId]
            );
            if ($result) {
                $message = 'Đã ghi nhận dịch vụ bảo hành/bảo dưỡng thành công.';
            } else {
                $errors[] = 'Không thể ghi nhận dịch vụ: ' . pg_last_error($conn);
            }
        } else {
            $errors[] = 'Vui lòng nhập đầy đủ thông tin.';
        }
    }
}

// Lấy danh sách bảo hành xe
$warranties = [];
$warrantyResult = pg_query($conn, "SELECT bh.ma_baohiem, bh.ngay_bat_dau, bh.ngay_het_bh, bh.phi_bao_hiem, bh.ngay_mua,
                                           x.ma_xe, x.hang_xe, x.loai_xe,
                                           kh.ma_khachhang, kh.ho_ten as ten_khachhang
                                    FROM bao_hiem_xe bh
                                    INNER JOIN xe x ON bh.ma_xe = x.ma_xe
                                    INNER JOIN khach_hang kh ON bh.ma_khachhang = kh.ma_khachhang
                                    ORDER BY bh.ngay_bat_dau DESC
                                    LIMIT 50");
if ($warrantyResult) {
    while ($row = pg_fetch_assoc($warrantyResult)) {
        $warranties[] = $row;
    }
}

// Lấy danh sách dịch vụ bảo hành
$services = [];
$serviceResult = pg_query($conn, "SELECT bd.ma_baohanh, bd.noi_dung, bd.ngay_baohanh, bd.ngay_het_bh,
                                          kh.ma_khachhang, kh.ho_ten as ten_khachhang, kh.email, kh.sdt
                                   FROM bao_hanh_dich_vu bd
                                   INNER JOIN khach_hang kh ON bd.ma_khachhang = kh.ma_khachhang
                                   ORDER BY bd.ngay_baohanh DESC
                                   LIMIT 50");
if ($serviceResult) {
    while ($row = pg_fetch_assoc($serviceResult)) {
        $services[] = $row;
    }
}

// Lấy danh sách xe
$cars = [];
$carsResult = pg_query($conn, "SELECT ma_xe, hang_xe, loai_xe, mau_xe FROM xe ORDER BY ma_xe DESC LIMIT 100");
if ($carsResult) {
    while ($row = pg_fetch_assoc($carsResult)) {
        $cars[] = $row;
    }
}

// Lấy danh sách khách hàng
$customers = [];
$customersResult = pg_query($conn, "SELECT ma_khachhang, ho_ten, email, sdt FROM khach_hang ORDER BY ma_khachhang DESC LIMIT 100");
if ($customersResult) {
    while ($row = pg_fetch_assoc($customersResult)) {
        $customers[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Nhân viên bảo hành/bảo dưỡng | AutoLux</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary: #ff4d4f;
            --dark: #0f172a;
        }
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
            gap: 24px;
        }
        nav a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 0.95rem;
        }
        nav a:hover { color: #fff; }
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
        main {
            background: #f1f5f9;
            border-top-left-radius: 32px;
            border-top-right-radius: 32px;
            padding: 32px 5vw 64px;
            min-height: calc(100vh - 96px);
            color: #0f172a;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .btn-back {
            display: inline-block;
            margin-bottom: 24px;
            padding: 10px 20px;
            border-radius: 999px;
            background: rgba(15,23,42,0.1);
            color: #0f172a;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-back:hover {
            background: rgba(15,23,42,0.2);
        }
        .tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            border-bottom: 2px solid rgba(15,23,42,0.1);
        }
        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            color: rgba(15,23,42,0.6);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }
        .tab.active {
            color: #0f172a;
            border-bottom-color: #ff4d4f;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
            margin-top: 24px;
        }
        th, td {
            padding: 16px 18px;
            text-align: left;
            border-bottom: 1px solid rgba(15,23,42,0.06);
        }
        th {
            background: #f8fafc;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover {
            background: #f8fafc;
        }
        .alert {
            margin-bottom: 18px;
            padding: 12px 18px;
            border-radius: 14px;
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
        .form-card {
            background: #fff;
            padding: 24px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
            margin-bottom: 24px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(15,23,42,0.15);
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .btn-submit {
            padding: 10px 24px;
            border-radius: 999px;
            border: none;
            background: linear-gradient(120deg, #06b6d4, #3b82f6);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    
    <main>
        <div class="container">
            <a href="admin.php" class="btn-back">← Quay lại trang quản trị</a>
            
            <h2>Chức năng dành cho Nhân viên bảo hành/bảo dưỡng</h2>
            
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
            
            <div class="tabs">
                <button class="tab <?= $activeTab === 'warranty' ? 'active' : '' ?>" onclick="switchTab('warranty')">🔧 Quản lý bảo hành</button>
                <button class="tab <?= $activeTab === 'create_warranty' ? 'active' : '' ?>" onclick="switchTab('create_warranty')">📝 Ghi nhận bảo hành</button>
                <button class="tab <?= $activeTab === 'maintenance' ? 'active' : '' ?>" onclick="switchTab('maintenance')">🛠️ Quản lý bảo dưỡng</button>
                <button class="tab <?= $activeTab === 'history' ? 'active' : '' ?>" onclick="switchTab('history')">📋 Lịch sử dịch vụ</button>
            </div>
            
            <!-- Tab Quản lý bảo hành -->
            <div id="tab-warranty" class="tab-content <?= $activeTab === 'warranty' ? 'active' : '' ?>">
                <h3>Danh sách bảo hành xe</h3>
                <?php if (empty($warranties)): ?>
                    <p>Chưa có phiếu bảo hành nào.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Mã BH</th>
                                <th>Khách hàng</th>
                                <th>Xe</th>
                                <th>Ngày bắt đầu</th>
                                <th>Ngày hết hạn</th>
                                <th>Phí bảo hiểm</th>
                                <th>Ngày mua</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($warranties as $w): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($w['ma_baohiem']) ?></td>
                                    <td><?= htmlspecialchars($w['ten_khachhang']) ?></td>
                                    <td><?= htmlspecialchars($w['hang_xe']) ?> <?= htmlspecialchars($w['loai_xe'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($w['ngay_bat_dau']) ?></td>
                                    <td><?= htmlspecialchars($w['ngay_het_bh'] ?? 'N/A') ?></td>
                                    <td><?= number_format($w['phi_bao_hiem'] ?? 0, 0, ',', '.') ?> đ</td>
                                    <td><?= htmlspecialchars($w['ngay_mua'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Tab Ghi nhận bảo hành -->
            <div id="tab-create_warranty" class="tab-content <?= $activeTab === 'create_warranty' ? 'active' : '' ?>">
                <h3>Tạo phiếu bảo hành mới</h3>
                <form method="post" class="form-card">
                    <input type="hidden" name="action" value="create_warranty">
                    <div class="form-group">
                        <label for="warranty_car_id">Chọn xe</label>
                        <select name="car_id" id="warranty_car_id" required>
                            <option value="">-- Chọn xe --</option>
                            <?php foreach ($cars as $car): ?>
                                <option value="<?= htmlspecialchars($car['ma_xe']) ?>">
                                    #<?= htmlspecialchars($car['ma_xe']) ?> - <?= htmlspecialchars($car['hang_xe']) ?> <?= htmlspecialchars($car['loai_xe'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="warranty_customer_id">Chọn khách hàng</label>
                        <select name="customer_id" id="warranty_customer_id" required>
                            <option value="">-- Chọn khách hàng --</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= htmlspecialchars($customer['ma_khachhang']) ?>">
                                    <?= htmlspecialchars($customer['ho_ten']) ?> - <?= htmlspecialchars($customer['email'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="warranty_start_date">Ngày bắt đầu</label>
                        <input type="date" name="start_date" id="warranty_start_date" required>
                    </div>
                    <div class="form-group">
                        <label for="warranty_end_date">Ngày hết hạn</label>
                        <input type="date" name="end_date" id="warranty_end_date">
                    </div>
                    <div class="form-group">
                        <label for="warranty_fee">Phí bảo hiểm</label>
                        <input type="number" name="fee" id="warranty_fee" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="warranty_purchase_date">Ngày mua</label>
                        <input type="date" name="purchase_date" id="warranty_purchase_date">
                    </div>
                    <button type="submit" class="btn-submit">Tạo phiếu bảo hành</button>
                </form>
            </div>
            
            <!-- Tab Quản lý bảo dưỡng -->
            <div id="tab-maintenance" class="tab-content <?= $activeTab === 'maintenance' ? 'active' : '' ?>">
                <h3>Ghi nhận dịch vụ bảo dưỡng</h3>
                <form method="post" class="form-card">
                    <input type="hidden" name="action" value="create_service">
                    <div class="form-group">
                        <label for="service_customer_id">Chọn khách hàng</label>
                        <select name="customer_id" id="service_customer_id" required>
                            <option value="">-- Chọn khách hàng --</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= htmlspecialchars($customer['ma_khachhang']) ?>">
                                    <?= htmlspecialchars($customer['ho_ten']) ?> - <?= htmlspecialchars($customer['email'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="service_content">Nội dung dịch vụ</label>
                        <textarea name="content" id="service_content" required placeholder="Mô tả chi tiết công việc bảo dưỡng đã thực hiện..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="service_warranty_date">Ngày bảo hành/bảo dưỡng</label>
                        <input type="date" name="warranty_date" id="service_warranty_date" required>
                    </div>
                    <div class="form-group">
                        <label for="service_end_date">Ngày hết hạn bảo hành</label>
                        <input type="date" name="end_date" id="service_end_date">
                    </div>
                    <button type="submit" class="btn-submit">Ghi nhận dịch vụ</button>
                </form>
            </div>
            
            <!-- Tab Lịch sử dịch vụ -->
            <div id="tab-history" class="tab-content <?= $activeTab === 'history' ? 'active' : '' ?>">
                <h3>Lịch sử dịch vụ bảo hành/bảo dưỡng</h3>
                <?php if (empty($services)): ?>
                    <p>Chưa có lịch sử dịch vụ nào.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Mã DV</th>
                                <th>Khách hàng</th>
                                <th>Email</th>
                                <th>SĐT</th>
                                <th>Nội dung</th>
                                <th>Ngày bảo hành</th>
                                <th>Ngày hết hạn</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($service['ma_baohanh']) ?></td>
                                    <td><?= htmlspecialchars($service['ten_khachhang']) ?></td>
                                    <td><?= htmlspecialchars($service['email'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($service['sdt'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($service['noi_dung']) ?></td>
                                    <td><?= htmlspecialchars($service['ngay_baohanh']) ?></td>
                                    <td><?= htmlspecialchars($service['ngay_het_bh'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script>
        const chip = document.getElementById('userChip');
        const menu = document.getElementById('userMenu');
        
        if (chip && menu) {
            chip.addEventListener('click', function(e) {
                e.stopPropagation();
                menu.classList.toggle('active');
            });
            
            document.addEventListener('click', function(e) {
                if (!chip.contains(e.target) && !menu.contains(e.target)) {
                    menu.classList.remove('active');
                }
            });
        }
        
        function switchTab(tab) {
            window.location.href = '?tab=' + tab;
        }
    </script>
</body>
</html>

