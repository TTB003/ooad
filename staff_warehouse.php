<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$conn = db();

function notifyPreorderCustomersWhenRestocked($conn, int $carId, string $carName): int
{
    $preorderResult = pg_query_params(
        $conn,
        "SELECT ma_khachhang FROM dat_truoc_xe WHERE ma_xe = $1 AND trang_thai = 'cho_hang'",
        [$carId]
    );

    if (!$preorderResult || pg_num_rows($preorderResult) === 0) {
        return 0;
    }

    $notified = 0;
    while ($row = pg_fetch_assoc($preorderResult)) {
        pg_query_params(
            $conn,
            'INSERT INTO thong_bao (tieu_de, noi_dung, loai_thongbao, ma_khachhang) VALUES ($1, $2, $3, $4)',
            [
                'Xe đã về hàng',
                'Xe ' . $carName . ' bạn đặt trước đã có hàng. Hãy đặt ngay để giữ chỗ.',
                'dat_truoc',
                $row['ma_khachhang']
            ]
        );
        $notified++;
    }

    pg_query_params(
        $conn,
        "UPDATE dat_truoc_xe SET trang_thai = 'da_thong_bao', da_thong_bao = TRUE, ngay_thong_bao = NOW()
         WHERE ma_xe = $1 AND trang_thai = 'cho_hang'",
        [$carId]
    );

    return $notified;
}

// Thêm cột mo_ta vào bảng xe nếu chưa có
pg_query($conn, "ALTER TABLE xe ADD COLUMN IF NOT EXISTS mo_ta TEXT");

$currentPosition = $_SESSION['staff_position'] ?? '';

// Kiểm tra quyền truy cập
if ($currentPosition !== 'Nhân viên kho') {
    header('Location: admin.php');
    exit;
}

$message = '';
$errors = [];
$activeTab = $_GET['tab'] ?? 'inventory';

// Xử lý các action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_car') {
        // Thêm xe để bán với hãng xe (chọn từ danh sách), loại xe, thông tin xe, mô tả và hình ảnh
        $hangXeId = (int)($_POST['hang_xe_id'] ?? 0);
        $loaiXe = trim($_POST['loai_xe'] ?? '');
        $mauXe = trim($_POST['mau_xe'] ?? '');
        $namSanXuat = (int)($_POST['nam_san_xuat'] ?? date('Y'));
        $giaBan = (float)($_POST['gia_ban'] ?? 0);
        $tinhTrang = $_POST['tinh_trang'] ?? 'Moi';
        $moTa = trim($_POST['mo_ta'] ?? '');
        
        // Lấy tên hãng xe từ ID
        $hangXe = '';
        if ($hangXeId) {
            $brandResult = pg_query_params($conn, 'SELECT ten_hangxe FROM hang_xe WHERE ma_hangxe = $1', [$hangXeId]);
            if ($brandResult && $brandRow = pg_fetch_assoc($brandResult)) {
                $hangXe = $brandRow['ten_hangxe'];
            }
        }
        
        // Kiểm tra upload hình ảnh
        $uploadedImages = [];
        if (isset($_FILES['hinh_anh']) && is_array($_FILES['hinh_anh']['name'])) {
            $fileCount = count($_FILES['hinh_anh']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['hinh_anh']['error'][$i] === UPLOAD_ERR_OK) {
                    $uploadedImages[] = [
                        'tmp_name' => $_FILES['hinh_anh']['tmp_name'][$i],
                        'name' => $_FILES['hinh_anh']['name'][$i],
                        'size' => $_FILES['hinh_anh']['size'][$i],
                        'type' => $_FILES['hinh_anh']['type'][$i]
                    ];
                }
            }
        }
        
        if ($hangXe === '' || $loaiXe === '') {
            $errors[] = 'Vui lòng chọn hãng xe và nhập loại xe.';
        } elseif ($giaBan <= 0) {
            $errors[] = 'Giá bán phải lớn hơn 0.';
        } elseif (empty($uploadedImages)) {
            $errors[] = 'Vui lòng tải lên ít nhất 1 hình ảnh.';
        } else {
            // Validate hình ảnh
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            $validImages = [];
            
            foreach ($uploadedImages as $img) {
                if (!in_array($img['type'], $allowedTypes)) {
                    $errors[] = 'File ' . htmlspecialchars($img['name']) . ' không phải là hình ảnh hợp lệ.';
                    continue;
                }
                if ($img['size'] > $maxSize) {
                    $errors[] = 'File ' . htmlspecialchars($img['name']) . ' vượt quá 5MB.';
                    continue;
                }
                $validImages[] = $img;
            }
            
            if (empty($validImages)) {
                $errors[] = 'Không có hình ảnh hợp lệ nào được tải lên.';
            }
            
            if (empty($errors)) {
                // Tạo thư mục upload nếu chưa có
                $uploadDir = __DIR__ . '/uploads/cars/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Tạo xe mới (lưu mô tả vào bảng xe)
                $result = pg_query_params($conn,
                    'INSERT INTO xe (hang_xe, loai_xe, mau_xe, nam_san_xuat, gia_ban, tinh_trang, mo_ta) VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING ma_xe',
                    [$hangXe, $loaiXe, $mauXe ?: null, $namSanXuat ?: null, $giaBan, $tinhTrang, $moTa ?: null]
                );
                
                if ($result && $row = pg_fetch_assoc($result)) {
                    $newCarId = $row['ma_xe'];
                    
                    // Lưu hình ảnh
                    $imageSaved = false;
                    foreach ($validImages as $index => $img) {
                        $fileExt = pathinfo($img['name'], PATHINFO_EXTENSION);
                        $fileName = 'car_' . $newCarId . '_' . time() . '_' . ($index + 1) . '.' . $fileExt;
                        $filePath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($img['tmp_name'], $filePath)) {
                            $imageUrl = 'uploads/cars/' . $fileName;
                            $isMain = ($index === 0); // Hình đầu tiên là hình chính
                            
                            $imgResult = pg_query_params($conn,
                                'INSERT INTO hinh_anh_xe (ma_xe, url, la_chinh) VALUES ($1, $2, $3)',
                                [$newCarId, $imageUrl, $isMain]
                            );
                            
                            if ($imgResult) {
                                $imageSaved = true;
                            }
                        }
                    }
                    
                    if ($imageSaved) {
                        $message = 'Đã thêm xe để bán thành công (Mã xe: #' . $newCarId . ') với ' . count($validImages) . ' hình ảnh. Bạn có thể nhập kho ngay bây giờ.';
                        // Reload danh sách xe để hiển thị xe mới
                        $cars = [];
                        $carsResult = pg_query($conn, "SELECT ma_xe, hang_xe, loai_xe, mau_xe, nam_san_xuat
                                                        FROM xe
                                                        ORDER BY ma_xe DESC
                                                        LIMIT 100");
                        if ($carsResult) {
                            while ($row = pg_fetch_assoc($carsResult)) {
                                $cars[] = $row;
                            }
                        }
                        // Redirect để cập nhật danh sách và chọn xe mới
                        header('Location: ?tab=import&new_car_id=' . $newCarId);
                        exit;
                    } else {
                        $errors[] = 'Đã tạo xe nhưng không thể lưu hình ảnh.';
                    }
                } else {
                    $errors[] = 'Không thể tạo xe mới: ' . pg_last_error($conn);
                }
            }
        }
    } elseif ($action === 'import_stock') {
        $carId = (int)($_POST['car_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        
        if ($carId && $quantity > 0) {
            // Tìm hoặc tạo bản ghi tồn kho
            $inventoryCheck = pg_query_params($conn, 
                'SELECT ma_baotri, so_luong_ton FROM ton_kho WHERE ma_baotri IN (SELECT ma_baotri FROM xe WHERE ma_xe = $1)',
                [$carId]
            );
            
            $previousStock = 0;
            $newQuantity = $quantity;
            $result = false;

            if ($inventoryCheck && pg_num_rows($inventoryCheck) > 0) {
                $inv = pg_fetch_assoc($inventoryCheck);
                $previousStock = (int)($inv['so_luong_ton'] ?? 0);
                $newQuantity = $previousStock + $quantity;
                $result = pg_query_params(
                    $conn,
                    'UPDATE ton_kho SET so_luong_nhap = COALESCE(so_luong_nhap, 0) + $1, so_luong_ton = $2, ngay_cap_nhat = CURRENT_DATE WHERE ma_baotri = $3',
                    [$quantity, $newQuantity, $inv['ma_baotri']]
                );
            } else {
                $previousStock = 0;
                $newQuantity = $quantity;
                // Tạo mới tồn kho
                $result = pg_query_params(
                    $conn,
                    'INSERT INTO ton_kho (so_luong_nhap, so_luong_ton, ngay_cap_nhat, ma_nhanvien) VALUES ($1, $1, CURRENT_DATE, $2) RETURNING ma_baotri',
                    [$quantity, $_SESSION['staff_id']]
                );
                if ($result && $row = pg_fetch_assoc($result)) {
                    pg_query_params($conn, 'UPDATE xe SET ma_baotri = $1 WHERE ma_xe = $2', [$row['ma_baotri'], $carId]);
                }
            }
            
            if ($result) {
                $message = 'Đã nhập hàng thành công.';
                $shouldNotifyPreorder = ($previousStock <= 0 && $newQuantity > 0);
                if ($shouldNotifyPreorder) {
                    $carInfoResult = pg_query_params(
                        $conn,
                        'SELECT hang_xe, loai_xe FROM xe WHERE ma_xe = $1 LIMIT 1',
                        [$carId]
                    );
                    $carInfo = $carInfoResult ? pg_fetch_assoc($carInfoResult) : null;
                    $carName = trim(($carInfo['hang_xe'] ?? 'Xe') . ' ' . ($carInfo['loai_xe'] ?? '#' . $carId));
                    $notified = notifyPreorderCustomersWhenRestocked($conn, $carId, $carName);
                    if ($notified > 0) {
                        $message .= ' Đã gửi thông báo cho ' . $notified . ' khách hàng đặt trước.';
                    }
                }
            } else {
                $errors[] = 'Không thể nhập hàng: ' . pg_last_error($conn);
            }
        } else {
            $errors[] = 'Vui lòng nhập đầy đủ thông tin.';
        }
    } elseif ($action === 'export_stock') {
        $carId = (int)($_POST['car_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $confirmExport = ($_POST['confirm_export'] ?? '') === 'yes';
        
        if ($carId && $quantity > 0) {
            // Kiểm tra xem có đơn hàng nào đang chờ cho xe này không
            $pendingOrders = [];
            $ordersResult = pg_query_params($conn,
                "SELECT dh.ma_donhang, dh.ngay_lap, dh.tong_tien, dh.trang_thai, dh.dia_chi_giao_hang,
                        kh.ho_ten, kh.sdt, kh.email,
                        ctdh.so_luong, ctdh.don_gia
                 FROM don_hang dh
                 INNER JOIN chi_tiet_don_hang ctdh ON dh.ma_donhang = ctdh.ma_donhang
                 INNER JOIN khach_hang kh ON dh.ma_khachhang = kh.ma_khachhang
                 WHERE ctdh.ma_xe = $1 AND dh.trang_thai IN ('Cho_duyet', 'Da_duyet')
                 ORDER BY dh.ngay_lap DESC",
                [$carId]
            );
            
            if ($ordersResult) {
                while ($order = pg_fetch_assoc($ordersResult)) {
                    $pendingOrders[] = $order;
                }
            }
            
            // Nếu có đơn hàng chờ và chưa xác nhận, hiển thị danh sách đơn hàng
            if (!empty($pendingOrders) && !$confirmExport) {
                $_SESSION['pending_orders_for_export'] = $pendingOrders;
                $_SESSION['export_car_id'] = $carId;
                $_SESSION['export_quantity'] = $quantity;
                // Sẽ hiển thị modal/table đơn hàng trong view
            } else {
                // Thực hiện xuất kho
                $inventoryCheck = pg_query_params($conn,
                    'SELECT tk.ma_baotri, tk.so_luong_ton FROM ton_kho tk INNER JOIN xe x ON x.ma_baotri = tk.ma_baotri WHERE x.ma_xe = $1',
                    [$carId]
                );
                
                if ($inventoryCheck && $inv = pg_fetch_assoc($inventoryCheck)) {
                    $currentStock = $inv['so_luong_ton'] ?? 0;
                    if ($currentStock >= $quantity) {
                        $newQuantity = $currentStock - $quantity;
                        $result = pg_query_params($conn,
                            'UPDATE ton_kho SET so_luong_ban = COALESCE(so_luong_ban, 0) + $1, so_luong_ton = $2, ngay_cap_nhat = CURRENT_DATE WHERE ma_baotri = $3',
                            [$quantity, $newQuantity, $inv['ma_baotri']]
                        );
                        if ($result) {
                            // Cập nhật trạng thái đơn hàng thành "Da_thanh_toan" nếu có đơn hàng liên quan
                            if (!empty($pendingOrders)) {
                                foreach ($pendingOrders as $order) {
                                    pg_query_params($conn,
                                        'UPDATE don_hang SET trang_thai = $1 WHERE ma_donhang = $2',
                                        ['Da_thanh_toan', $order['ma_donhang']]
                                    );
                                }
                            }
                            
                            $message = 'Đã xuất hàng thành công' . (!empty($pendingOrders) ? ' và cập nhật trạng thái đơn hàng.' : '.');
                            unset($_SESSION['pending_orders_for_export']);
                            unset($_SESSION['export_car_id']);
                            unset($_SESSION['export_quantity']);
                        } else {
                            $errors[] = 'Không thể xuất hàng.';
                        }
                    } else {
                        $errors[] = 'Số lượng tồn kho không đủ.';
                    }
                } else {
                    $errors[] = 'Không tìm thấy thông tin tồn kho cho xe này.';
                }
            }
        } else {
            $errors[] = 'Vui lòng nhập đầy đủ thông tin.';
        }
    } elseif ($action === 'edit_car') {
        // Sửa thông tin xe (nhân viên kho chỉ sửa được loại xe, màu xe, năm sản xuất, mô tả)
        $carId = (int)($_POST['car_id'] ?? 0);
        $loaiXe = trim($_POST['loai_xe'] ?? '');
        $mauXe = trim($_POST['mau_xe'] ?? '');
        $namSanXuat = (int)($_POST['nam_san_xuat'] ?? date('Y'));
        $moTa = trim($_POST['mo_ta'] ?? '');
        
        if ($carId && $loaiXe !== '') {
            // Cập nhật thông tin xe (bao gồm mo_ta trong bảng xe)
            $result = pg_query_params($conn,
                'UPDATE xe SET loai_xe = $1, mau_xe = $2, nam_san_xuat = $3, mo_ta = $4 WHERE ma_xe = $5',
                [$loaiXe, $mauXe ?: null, $namSanXuat ?: null, $moTa ?: null, $carId]
            );
            
            if ($result) {
                $message = 'Đã cập nhật thông tin xe thành công.';
            } else {
                $errors[] = 'Không thể cập nhật thông tin xe: ' . pg_last_error($conn);
            }
        } else {
            $errors[] = 'Vui lòng nhập đầy đủ thông tin.';
        }
    }
}

// Lấy danh sách tồn kho
$inventory = [];
$inventoryResult = pg_query($conn, "SELECT tk.ma_baotri, x.ma_xe, x.hang_xe, x.loai_xe, x.mau_xe,
                                            tk.so_luong_nhap, tk.so_luong_ban, tk.so_luong_ton, tk.ngay_cap_nhat
                                     FROM ton_kho tk
                                     LEFT JOIN xe x ON x.ma_baotri = tk.ma_baotri
                                     ORDER BY tk.ngay_cap_nhat DESC, tk.ma_baotri DESC
                                     LIMIT 100");
if ($inventoryResult) {
    while ($row = pg_fetch_assoc($inventoryResult)) {
        $inventory[] = $row;
    }
}

// Lấy danh sách hãng xe (nhân viên chỉ chọn từ danh sách có sẵn)
$brands = [];
$brandResult = pg_query($conn, "SELECT ma_hangxe, ten_hangxe FROM hang_xe ORDER BY ten_hangxe");
if ($brandResult) {
    while ($row = pg_fetch_assoc($brandResult)) {
        $brands[] = $row;
    }
}

// Lấy danh sách xe để nhập/xuất
$cars = [];
$carsResult = pg_query($conn, "SELECT ma_xe, hang_xe, loai_xe, mau_xe, nam_san_xuat
                                FROM xe
                                ORDER BY ma_xe DESC
                                LIMIT 100");
if ($carsResult) {
    while ($row = pg_fetch_assoc($carsResult)) {
        $cars[] = $row;
    }
}

// Lấy danh sách xe để sửa (có mô tả và hình ảnh)
$carsForEdit = [];
$carsForEditResult = pg_query($conn, "SELECT x.ma_xe, x.hang_xe, x.loai_xe, x.mau_xe, x.nam_san_xuat, x.mo_ta,
                                             (SELECT url FROM hinh_anh_xe WHERE ma_xe = x.ma_xe AND la_chinh = TRUE LIMIT 1) as hinh_anh
                                      FROM xe x
                                      ORDER BY x.ma_xe DESC");
if ($carsForEditResult) {
    while ($row = pg_fetch_assoc($carsForEditResult)) {
        $carsForEdit[] = $row;
    }
}

// Báo cáo tồn kho
$lowStock = [];
$lowStockResult = pg_query($conn, "SELECT tk.ma_baotri, x.ma_xe, x.hang_xe, x.loai_xe,
                                           tk.so_luong_ton
                                    FROM ton_kho tk
                                    LEFT JOIN xe x ON x.ma_baotri = tk.ma_baotri
                                    WHERE tk.so_luong_ton <= 5 OR tk.so_luong_ton IS NULL
                                    ORDER BY tk.so_luong_ton ASC");
if ($lowStockResult) {
    while ($row = pg_fetch_assoc($lowStockResult)) {
        $lowStock[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Nhân viên kho | AutoLux</title>
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
        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            border: none;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            background: rgba(59,130,246,0.18);
            color: #1e40af;
            margin: 2px;
        }
        .btn-action:hover {
            opacity: 0.8;
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
        .warning {
            background: rgba(251,191,36,0.18);
            color: #92400e;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .form-inline {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            margin-bottom: 24px;
            background: #fff;
            padding: 20px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
        }
        .form-group {
            flex: 1;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(15,23,42,0.15);
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .btn-submit {
            padding: 10px 24px;
            border-radius: 999px;
            border: none;
            background: linear-gradient(120deg, #06b6d4, #3b82f6);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    
    <main>
        <div class="container">
            <a href="admin.php" class="btn-back">← Quay lại trang quản trị</a>
            
            <h2>Chức năng dành cho Nhân viên kho</h2>
            
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
                <button class="tab <?= $activeTab === 'inventory' ? 'active' : '' ?>" onclick="switchTab('inventory')">📦 Quản lý tồn kho</button>
                <button class="tab <?= $activeTab === 'import' ? 'active' : '' ?>" onclick="switchTab('import')">📥 Nhập hàng</button>
                <button class="tab <?= $activeTab === 'export' ? 'active' : '' ?>" onclick="switchTab('export')">📤 Xuất hàng</button>
                <button class="tab <?= $activeTab === 'fixcar' ? 'active' : '' ?>" onclick="switchTab('fixcar')">✏️ Sửa thông tin xe</button>
                <button class="tab <?= $activeTab === 'reports' ? 'active' : '' ?>" onclick="switchTab('reports')">📊 Báo cáo tồn kho</button>
            </div>
            
            <!-- Tab Quản lý tồn kho -->
            <div id="tab-inventory" class="tab-content <?= $activeTab === 'inventory' ? 'active' : '' ?>">
                <h3>Danh sách tồn kho</h3>
                <?php if (empty($inventory)): ?>
                    <p>Chưa có dữ liệu tồn kho.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Mã xe</th>
                                <th>Hãng xe</th>
                                <th>Loại xe</th>
                                <th>Màu xe</th>
                                <th>Số lượng nhập</th>
                                <th>Số lượng bán</th>
                                <th>Số lượng tồn</th>
                                <th>Ngày cập nhật</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $inv): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($inv['ma_xe'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($inv['hang_xe'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($inv['loai_xe'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($inv['mau_xe'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($inv['so_luong_nhap'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars($inv['so_luong_ban'] ?? 0) ?></td>
                                    <td>
                                        <?= htmlspecialchars($inv['so_luong_ton'] ?? 0) ?>
                                        <?php if (($inv['so_luong_ton'] ?? 0) <= 5): ?>
                                            <span class="warning">⚠️ Sắp hết</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($inv['ngay_cap_nhat'] ?? 'N/A') ?></td>
                                    <td>-</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Tab Nhập hàng -->
            <div id="tab-import" class="tab-content <?= $activeTab === 'import' ? 'active' : '' ?>">
                <h3>Nhập hàng vào kho</h3>
                
                <!-- Form thêm xe để bán -->
                <div style="background: #fff; padding: 20px; border-radius: 18px; box-shadow: 0 10px 30px rgba(15,23,42,0.1); margin-bottom: 24px;">
                    <h4 style="margin-top: 0; margin-bottom: 16px;">🚗 Thêm xe để bán</h4>
                    <form method="post" enctype="multipart/form-data" id="createCarForm">
                        <input type="hidden" name="action" value="create_car">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px;">
                            <div class="form-group">
                                <label for="hang_xe_id">Hãng xe *</label>
                                <select name="hang_xe_id" id="hang_xe_id" required>
                                    <option value="">-- Chọn hãng xe --</option>
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?= htmlspecialchars($brand['ma_hangxe']) ?>">
                                            <?= htmlspecialchars($brand['ten_hangxe']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="display: block; margin-top: 4px; color: #64748b;">Chỉ có thể chọn từ danh sách hãng xe có sẵn</small>
                            </div>
                            <div class="form-group">
                                <label for="loai_xe">Loại xe *</label>
                                <input type="text" name="loai_xe" id="loai_xe" placeholder="VD: Camry, Civic, Focus..." required>
                            </div>
                            <div class="form-group">
                                <label for="mau_xe">Màu xe</label>
                                <input type="text" name="mau_xe" id="mau_xe" placeholder="VD: Đỏ, Xanh, Trắng...">
                            </div>
                            <div class="form-group">
                                <label for="nam_san_xuat">Năm sản xuất</label>
                                <input type="number" name="nam_san_xuat" id="nam_san_xuat" min="1900" max="<?= date('Y') + 1 ?>" value="<?= date('Y') ?>">
                            </div>
                            <div class="form-group">
                                <label for="gia_ban">Giá bán (VNĐ) *</label>
                                <input type="number" name="gia_ban" id="gia_ban" min="0" step="1000" placeholder="VD: 500000000" required>
                            </div>
                            <div class="form-group">
                                <label for="tinh_trang">Tình trạng</label>
                                <select name="tinh_trang" id="tinh_trang">
                                    <option value="Moi">Mới</option>
                                    <option value="Da_qua_su_dung">Đã qua sử dụng</option>
                                    <option value="Dang_ban">Đang bán</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="mo_ta">Mô tả</label>
                            <textarea name="mo_ta" id="mo_ta" rows="3" placeholder="Mô tả chi tiết về xe..."></textarea>
                        </div>
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="hinh_anh">Hình ảnh xe * (Tối thiểu 1 tấm, tối đa 10 tấm)</label>
                            <input type="file" name="hinh_anh[]" id="hinh_anh" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" multiple required onchange="previewImages(this)">
                            <small style="display: block; margin-top: 4px; color: #64748b;">Chấp nhận: JPG, PNG, GIF, WEBP (tối đa 5MB mỗi file)</small>
                            <div id="imagePreview" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; margin-top: 12px;"></div>
                        </div>
                        <button type="submit" class="btn-submit" style="background: linear-gradient(120deg, #10b981, #059669);">Thêm xe để bán</button>
                    </form>
                </div>
                
                <!-- Form nhập hàng -->
                <div style="background: #fff; padding: 20px; border-radius: 18px; box-shadow: 0 10px 30px rgba(15,23,42,0.1);">
                    <h4 style="margin-top: 0; margin-bottom: 16px;">📥 Nhập hàng vào kho</h4>
                    <form method="post" class="form-inline" style="margin-bottom: 0;">
                        <input type="hidden" name="action" value="import_stock">
                        <div class="form-group">
                            <label for="import_car_id">Chọn xe</label>
                            <select name="car_id" id="import_car_id" required>
                                <option value="">-- Chọn xe --</option>
                                <?php foreach ($cars as $car): ?>
                                    <option value="<?= htmlspecialchars($car['ma_xe']) ?>" <?= isset($_GET['new_car_id']) && $_GET['new_car_id'] == $car['ma_xe'] ? 'selected' : '' ?>>
                                        #<?= htmlspecialchars($car['ma_xe']) ?> - <?= htmlspecialchars($car['hang_xe']) ?> <?= htmlspecialchars($car['loai_xe'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="import_quantity">Số lượng</label>
                            <input type="number" name="quantity" id="import_quantity" min="1" required>
                        </div>
                        <button type="submit" class="btn-submit">Nhập hàng</button>
                    </form>
                </div>
            </div>
            
            <!-- Tab Xuất hàng -->
            <div id="tab-export" class="tab-content <?= $activeTab === 'export' ? 'active' : '' ?>">
                <h3>Xuất hàng từ kho</h3>
                
                <?php if (isset($_SESSION['pending_orders_for_export']) && !empty($_SESSION['pending_orders_for_export'])): ?>
                    <div style="background: #fff; padding: 20px; border-radius: 18px; box-shadow: 0 10px 30px rgba(15,23,42,0.1); margin-bottom: 24px;">
                        <h4 style="color: #991b1b; margin-top: 0;">⚠️ Có đơn hàng đang chờ cho xe này:</h4>
                        <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid rgba(15,23,42,0.1);">
                                    <th style="padding: 12px; text-align: left;">Mã đơn</th>
                                    <th style="padding: 12px; text-align: left;">Khách hàng</th>
                                    <th style="padding: 12px; text-align: left;">Số lượng</th>
                                    <th style="padding: 12px; text-align: left;">Tổng tiền</th>
                                    <th style="padding: 12px; text-align: left;">Địa chỉ giao hàng</th>
                                    <th style="padding: 12px; text-align: left;">Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['pending_orders_for_export'] as $order): ?>
                                    <tr style="border-bottom: 1px solid rgba(15,23,42,0.06);">
                                        <td style="padding: 12px;">#<?= htmlspecialchars($order['ma_donhang']) ?></td>
                                        <td style="padding: 12px;">
                                            <strong><?= htmlspecialchars($order['ho_ten']) ?></strong><br>
                                            <small style="color: #64748b;"><?= htmlspecialchars($order['sdt']) ?></small>
                                        </td>
                                        <td style="padding: 12px;"><?= htmlspecialchars($order['so_luong']) ?></td>
                                        <td style="padding: 12px; font-weight: 600; color: #3b82f6;">
                                            <?= number_format($order['tong_tien'], 0, ',', '.') ?> đ
                                        </td>
                                        <td style="padding: 12px; max-width: 250px;">
                                            <?= htmlspecialchars($order['dia_chi_giao_hang'] ?? 'N/A') ?>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="padding: 4px 8px; border-radius: 6px; background: rgba(251,191,36,0.18); color: #92400e; font-size: 0.85rem;">
                                                <?= htmlspecialchars($order['trang_thai']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <form method="post" style="margin-top: 20px;">
                            <input type="hidden" name="action" value="export_stock">
                            <input type="hidden" name="car_id" value="<?= htmlspecialchars($_SESSION['export_car_id'] ?? '') ?>">
                            <input type="hidden" name="quantity" value="<?= htmlspecialchars($_SESSION['export_quantity'] ?? '') ?>">
                            <input type="hidden" name="confirm_export" value="yes">
                            <button type="submit" class="btn-submit" style="background: linear-gradient(120deg, #10b981, #059669);">Đồng ý xuất hàng</button>
                            <a href="?tab=export" class="btn-submit" style="background: #e2e8f0; color: #0f172a; text-decoration: none; display: inline-block; margin-left: 12px;">Hủy</a>
                        </form>
                    </div>
                <?php endif; ?>
                
                <form method="post" class="form-inline">
                    <input type="hidden" name="action" value="export_stock">
                    <div class="form-group">
                        <label for="export_car_id">Chọn xe</label>
                        <select name="car_id" id="export_car_id" required>
                            <option value="">-- Chọn xe --</option>
                            <?php foreach ($cars as $car): ?>
                                <option value="<?= htmlspecialchars($car['ma_xe']) ?>">
                                    #<?= htmlspecialchars($car['ma_xe']) ?> - <?= htmlspecialchars($car['hang_xe']) ?> <?= htmlspecialchars($car['loai_xe'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="export_quantity">Số lượng</label>
                        <input type="number" name="quantity" id="export_quantity" min="1" required>
                    </div>
                    <button type="submit" class="btn-submit">Xuất hàng</button>
                </form>
            </div>
            
            <!-- Tab Sửa thông tin xe -->
            <div id="tab-fixcar" class="tab-content <?= $activeTab === 'fixcar' ? 'active' : '' ?>">
                <h3>Danh sách xe để sửa</h3>
                <?php if (empty($carsForEdit)): ?>
                    <p>Chưa có xe nào trong hệ thống.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Hình ảnh</th>
                                <th>Mã xe</th>
                                <th>Hãng xe</th>
                                <th>Loại xe</th>
                                <th>Màu xe</th>
                                <th>Năm SX</th>
                                <th>Mô tả</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($carsForEdit as $car): ?>
                                <tr>
                                    <td>
                                        <?php if ($car['hinh_anh']): ?>
                                            <img src="<?= htmlspecialchars($car['hinh_anh']) ?>" alt="Xe" style="width: 80px; height: 60px; object-fit: cover; border-radius: 8px;" onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>#<?= htmlspecialchars($car['ma_xe']) ?></td>
                                    <td><?= htmlspecialchars($car['hang_xe']) ?></td>
                                    <td><?= htmlspecialchars($car['loai_xe'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($car['mau_xe'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($car['nam_san_xuat'] ?? 'N/A') ?></td>
                                    <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars($car['mo_ta'] ?? 'Chưa có mô tả') ?>
                                    </td>
                                    <td>
                                        <button class="btn-action" style="background: rgba(59,130,246,0.18); color: #1e40af;" onclick="openEditModal(<?= htmlspecialchars($car['ma_xe']) ?>)">✏️ Sửa</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Tab Báo cáo tồn kho -->
            <div id="tab-reports" class="tab-content <?= $activeTab === 'reports' ? 'active' : '' ?>">
                <h3>Báo cáo tồn kho</h3>
                
                <?php if (!empty($lowStock)): ?>
                    <div style="margin-bottom: 24px;">
                        <h4 style="color: #991b1b;">⚠️ Cảnh báo: Hàng sắp hết</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>Mã xe</th>
                                    <th>Hãng xe</th>
                                    <th>Loại xe</th>
                                    <th>Số lượng tồn</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStock as $item): ?>
                                    <tr>
                                        <td>#<?= htmlspecialchars($item['ma_xe'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($item['hang_xe'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($item['loai_xe'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="warning"><?= htmlspecialchars($item['so_luong_ton'] ?? 0) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <h4>Thống kê tổng quan</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Mã xe</th>
                            <th>Hãng xe</th>
                            <th>Loại xe</th>
                            <th>Số lượng nhập</th>
                            <th>Số lượng bán</th>
                            <th>Số lượng tồn</th>
                            <th>Ngày cập nhật</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $inv): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($inv['ma_xe'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($inv['hang_xe'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($inv['loai_xe'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($inv['so_luong_nhap'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($inv['so_luong_ban'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($inv['so_luong_ton'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($inv['ngay_cap_nhat'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
        
        function previewImages(input) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files.length > 0) {
                const fileCount = input.files.length;
                if (fileCount > 10) {
                    alert('Chỉ được tải lên tối đa 10 hình ảnh.');
                    input.value = '';
                    return;
                }
                
                for (let i = 0; i < fileCount; i++) {
                    const file = input.files[i];
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.style.position = 'relative';
                            div.style.border = '2px solid #e2e8f0';
                            div.style.borderRadius = '8px';
                            div.style.overflow = 'hidden';
                            div.style.aspectRatio = '1';
                            
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.style.width = '100%';
                            img.style.height = '100%';
                            img.style.objectFit = 'cover';
                            
                            const label = document.createElement('div');
                            label.style.position = 'absolute';
                            label.style.bottom = '0';
                            label.style.left = '0';
                            label.style.right = '0';
                            label.style.background = 'rgba(0,0,0,0.7)';
                            label.style.color = '#fff';
                            label.style.padding = '4px 8px';
                            label.style.fontSize = '0.75rem';
                            label.textContent = i === 0 ? 'Hình chính' : 'Hình ' + (i + 1);
                            
                            div.appendChild(img);
                            div.appendChild(label);
                            preview.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    }
                }
            }
        }
        
        document.getElementById('createCarForm')?.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('hinh_anh');
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                alert('Vui lòng tải lên ít nhất 1 hình ảnh.');
                return false;
            }
            
            // Validate file size
            let hasLargeFile = false;
            for (let i = 0; i < fileInput.files.length; i++) {
                if (fileInput.files[i].size > 5 * 1024 * 1024) {
                    hasLargeFile = true;
                    break;
                }
            }
            
            if (hasLargeFile) {
                e.preventDefault();
                alert('Một hoặc nhiều file vượt quá 5MB. Vui lòng chọn file nhỏ hơn.');
                return false;
            }
        });
    </script>
    <style>
        #imagePreview img {
            cursor: pointer;
            transition: transform 0.2s;
        }
        #imagePreview img:hover {
            transform: scale(1.05);
        }
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: #fff;
            border-radius: 18px;
            padding: 24px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h3 {
            margin: 0;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-close:hover {
            color: #0f172a;
        }
        .form-full {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .form-full > div {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-full label {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .form-full input,
        .form-full textarea {
            width: 100%;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(15,23,42,0.15);
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .form-full textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }
        .btn-cancel {
            padding: 10px 24px;
            border-radius: 999px;
            border: none;
            background: #e2e8f0;
            color: #0f172a;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
    
    <!-- Modal sửa xe -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Sửa thông tin xe</h3>
                <button class="modal-close" onclick="closeEditModal()">✕</button>
            </div>
            <form method="post" class="form-full" id="editCarForm">
                <input type="hidden" name="action" value="edit_car">
                <input type="hidden" name="car_id" id="edit_car_id">
                
                <div>
                    <label for="edit_loai_xe">Loại xe *</label>
                    <input type="text" id="edit_loai_xe" name="loai_xe" required>
                </div>
                
                <div>
                    <label for="edit_mau_xe">Màu xe</label>
                    <input type="text" id="edit_mau_xe" name="mau_xe">
                </div>
                
                <div>
                    <label for="edit_nam_san_xuat">Năm sản xuất</label>
                    <input type="number" id="edit_nam_san_xuat" name="nam_san_xuat" min="1900" max="<?= date('Y') + 1 ?>">
                </div>
                
                <div>
                    <label for="edit_mo_ta">Mô tả</label>
                    <textarea id="edit_mo_ta" name="mo_ta" placeholder="Nhập mô tả chi tiết về xe..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-submit">Lưu thay đổi</button>
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Hủy</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openEditModal(carId) {
            fetch('get_car_detail.php?id=' + carId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.car) {
                        document.getElementById('edit_car_id').value = data.car.ma_xe;
                        document.getElementById('edit_loai_xe').value = data.car.loai_xe || '';
                        document.getElementById('edit_mau_xe').value = data.car.mau_xe || '';
                        document.getElementById('edit_nam_san_xuat').value = data.car.nam_san_xuat || '';
                        document.getElementById('edit_mo_ta').value = data.car.mo_ta || '';
                        document.getElementById('editModal').classList.add('active');
                    } else {
                        alert('Không thể tải thông tin xe.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Lỗi khi tải thông tin xe.');
                });
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        document.getElementById('editModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>

