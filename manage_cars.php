<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php?type=staff');
    exit;
}

$conn = db();
$isAdmin = ($_SESSION['staff_role'] ?? '') === 'Admin';

// ===================== XỬ LÝ THÊM/SỬA/XÓA =========================
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Thêm hãng xe
    if (isset($_POST['add_brand'])) {
        $ten_hangxe = trim($_POST['ten_hangxe']);
        if ($ten_hangxe) {
            $res = pg_query_params($conn, "INSERT INTO hang_xe (ten_hangxe) VALUES ($1) ON CONFLICT DO NOTHING", [$ten_hangxe]);
            $flash = $res ? "✅ Thêm hãng xe thành công" : "❌ Lỗi thêm hãng xe";
        }
    }

    // Sửa hãng xe
    if (isset($_POST['edit_brand'])) {
        $ma_hangxe = $_POST['ma_hangxe'];
        $ten_hangxe = trim($_POST['ten_hangxe']);
        if ($ma_hangxe && $ten_hangxe) {
            $res = pg_query_params($conn, "UPDATE hang_xe SET ten_hangxe=$1 WHERE ma_hangxe=$2", [$ten_hangxe, $ma_hangxe]);
            $flash = $res ? "✅ Cập nhật hãng xe thành công" : "❌ Lỗi cập nhật hãng xe";
        }
    }

    // Xóa hãng xe
    if (isset($_POST['delete_brand'])) {
        $ma_hangxe = $_POST['ma_hangxe'];
        if ($ma_hangxe) {
            $res = pg_query_params($conn, "DELETE FROM hang_xe WHERE ma_hangxe=$1", [$ma_hangxe]);
            $flash = $res ? "✅ Xóa hãng xe thành công" : "❌ Lỗi xóa hãng xe";
        }
    }

    // Thêm xe
    if (isset($_POST['add_car'])) {
        $hangXeId = (int)($_POST['hang_xe_id'] ?? 0);
        $loai_xe = trim($_POST['loai_xe'] ?? '');
        $mau_xe = trim($_POST['mau_xe'] ?? '');
        $nam_san_xuat = (int)($_POST['nam_san_xuat'] ?? date('Y'));
        $gia_ban = (float)($_POST['gia_ban'] ?? 0);
        $tinh_trang = $_POST['tinh_trang'] ?? 'Moi';
        $mo_ta = trim($_POST['mo_ta'] ?? '');
        
        // Lấy tên hãng xe từ ID
        $hang_xe = '';
        if ($hangXeId > 0) {
            $brandResult = pg_query_params($conn, 'SELECT ten_hangxe FROM hang_xe WHERE ma_hangxe = $1', [$hangXeId]);
            if ($brandResult && $brandRow = pg_fetch_assoc($brandResult)) {
                $hang_xe = $brandRow['ten_hangxe'];
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
        
        if ($hangXeId <= 0 || $hang_xe === '' || $loai_xe === '') {
            $flash = "❌ Vui lòng chọn hãng xe và nhập loại xe.";
        } elseif ($gia_ban <= 0) {
            $flash = "❌ Giá bán phải lớn hơn 0.";
        } elseif (empty($uploadedImages)) {
            $flash = "❌ Vui lòng tải lên ít nhất 1 hình ảnh.";
        } else {
            // Validate hình ảnh
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            $validImages = [];
            
            foreach ($uploadedImages as $img) {
                if (!in_array($img['type'], $allowedTypes)) {
                    $flash = "❌ File " . htmlspecialchars($img['name']) . " không phải là hình ảnh hợp lệ.";
                    continue;
                }
                if ($img['size'] > $maxSize) {
                    $flash = "❌ File " . htmlspecialchars($img['name']) . " vượt quá 5MB.";
                    continue;
                }
                $validImages[] = $img;
            }
            
            if (empty($validImages)) {
                $flash = "❌ Không có hình ảnh hợp lệ nào được tải lên.";
            } else {
                // Tạo thư mục upload nếu chưa có
                $uploadDir = __DIR__ . '/uploads/cars/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Tạo xe mới
                $result = pg_query_params(
                    $conn,
                    'INSERT INTO xe (ma_hangxe, hang_xe, loai_xe, mau_xe, nam_san_xuat, gia_ban, tinh_trang, mo_ta) VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING ma_xe',
                    [
                        $hangXeId,
                        $hang_xe,
                        $loai_xe,
                        $mau_xe ?: null,
                        $nam_san_xuat ?: null,
                        $gia_ban,
                        $tinh_trang,
                        $mo_ta ?: null
                    ]
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
                        $flash = "✅ Thêm xe thành công (Mã xe: #" . $newCarId . ") với " . count($validImages) . " hình ảnh.";
                    } else {
                        $flash = "✅ Đã tạo xe nhưng không thể lưu hình ảnh.";
                    }
                } else {
                    $flash = "❌ Không thể tạo xe mới: " . pg_last_error($conn);
                }
            }
        }
    }

    // Sửa xe
    if (isset($_POST['edit_car'])) {
        $ma_xe = (int)($_POST['ma_xe'] ?? 0);
        $hangXeId = (int)($_POST['hang_xe_id'] ?? 0);
        $loai_xe = trim($_POST['loai_xe'] ?? '');
        $gia_ban = (float)($_POST['gia_ban'] ?? 0);
        $tinh_trang = $_POST['tinh_trang'] ?? 'Moi';
        $mau_xe = trim($_POST['mau_xe'] ?? '');
        $nam_san_xuat = (int)($_POST['nam_san_xuat'] ?? date('Y'));
        $mo_ta = trim($_POST['mo_ta'] ?? '');

        $hang_xe = '';
        if ($hangXeId > 0) {
            $brandResult = pg_query_params($conn, 'SELECT ten_hangxe FROM hang_xe WHERE ma_hangxe = $1', [$hangXeId]);
            if ($brandResult && $brandRow = pg_fetch_assoc($brandResult)) {
                $hang_xe = $brandRow['ten_hangxe'];
            }
        }

        $res = false;
        if ($ma_xe <= 0 || $hangXeId <= 0 || $hang_xe === '' || $loai_xe === '' || $gia_ban <= 0) {
            $flash = "❌ Thiếu thông tin cần thiết để cập nhật xe.";
        } else {
            $res = pg_query_params(
                $conn,
                "UPDATE xe SET ma_hangxe=$1, hang_xe=$2, loai_xe=$3, gia_ban=$4, tinh_trang=$5, mau_xe=$6, nam_san_xuat=$7, mo_ta=$8 WHERE ma_xe=$9",
                [$hangXeId, $hang_xe, $loai_xe, $gia_ban, $tinh_trang, $mau_xe, $nam_san_xuat, $mo_ta ?: null, $ma_xe]
            );
            $flash = $res ? "✅ Cập nhật xe thành công" : "❌ Lỗi cập nhật xe";
        }
    }

    // Xóa xe
    if (isset($_POST['delete_car'])) {
        $ma_xe = $_POST['ma_xe'];
        $res = pg_query_params($conn, "DELETE FROM xe WHERE ma_xe=$1", [$ma_xe]);
        $flash = $res ? "✅ Xóa xe thành công" : "❌ Lỗi xóa xe";
    }
}

// ===================== LẤY DỮ LIỆU =========================
// Danh sách hãng xe
$brands = [];
$res_brands = pg_query($conn, "SELECT * FROM hang_xe ORDER BY ten_hangxe ASC");
if ($res_brands) {
    while ($row = pg_fetch_assoc($res_brands)) $brands[] = $row;
}

// Danh sách xe
$cars = [];
$res_cars = pg_query($conn, "SELECT x.*, COALESCE(hx.ten_hangxe, x.hang_xe) AS brand_name FROM xe x LEFT JOIN hang_xe hx ON x.ma_hangxe = hx.ma_hangxe ORDER BY x.ma_xe DESC");
if ($res_cars) {
    while ($row = pg_fetch_assoc($res_cars)) $cars[] = $row;
}

// Các option tình trạng xe
$tinh_trang_options = ['Moi'=>'Mới','Da_qua_su_dung'=>'Đã qua sử dụng','Dang_ban'=>'Đang bán'];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý xe | AutoLux</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary: #ff4d4f;
            --dark: #0f172a;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(145deg, #020817, #0f172a 70%);
            color: #0f172a;
            min-height: 100vh;
        }
        
        main {
            background: #f1f5f9;
            border-top-left-radius: 32px;
            border-top-right-radius: 32px;
            padding: 32px 5vw 64px;
            min-height: calc(100vh - 96px);
            color: #0f172a;
        }
        
        form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px 24px;
            max-width: 880px;
        }
        label { 
            font-weight: 600; 
            display: block; 
            margin-bottom: 6px; 
        }
        input, select {
            width: 100%;
            padding: 11px 12px;
            border-radius: 12px;
            border: 1px solid rgba(15,23,42,0.15);
            font-size: 0.95rem;
        }
        textarea {
            width: 100%;
            padding: 11px 12px;
            border-radius: 12px;
            border: 1px solid rgba(15,23,42,0.15);
            font-size: 0.95rem;
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        button {
            padding: 12px;
            border-radius: 999px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            color: #fff;
            background: linear-gradient(120deg, #06b6d4, #3b82f6);
            transition: all 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .btn-success {
            background: linear-gradient(120deg, #22c55e, #16a34a);
        }
        .btn-danger {
            background: linear-gradient(120deg, #ef4444, #dc2626);
        }
        .btn-warning {
            background: linear-gradient(120deg, #f59e0b, #d97706);
        }
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        .tabs {
            display: flex;
            background: #fff;
            border-radius: 18px;
            padding: 8px;
            margin-bottom: 32px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
            gap: 8px;
        }
        .tab {
            flex: 1;
            padding: 14px 20px;
            text-align: center;
            cursor: pointer;
            background: transparent;
            border: none;
            font-size: 0.95rem;
            font-weight: 600;
            color: #64748b;
            transition: all 0.2s;
            border-radius: 12px;
            font-family: inherit;
        }
        .tab:hover {
            background: #f8fafc;
            color: #0f172a;
        }
        .tab.active {
            color: #0f172a;
            background: linear-gradient(120deg, #06b6d4, #3b82f6);
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .flash {
            margin-bottom: 18px;
            padding: 12px 18px;
            border-radius: 14px;
            background: rgba(34,197,94,0.18);
            border: 1px solid rgba(34,197,94,0.3);
            color: #166534;
        }
        .card {
            background: #fff;
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
            margin-top: 32px;
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
        tbody tr:hover {
            background: #f8fafc;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-new {
            background: rgba(34,197,94,0.18);
            color: #15803d;
        }
        .badge-used {
            background: rgba(245,158,11,0.18);
            color: #92400e;
        }
        .badge-selling {
            background: rgba(59,130,246,0.18);
            color: #1e40af;
        }
        .actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
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
            background: white;
            border-radius: 18px;
            padding: 30px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-content form {
            max-width: 100%;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .modal-header h3 {
            font-size: 1.5rem;
            color: #0f172a;
            font-weight: 600;
        }
        .close-modal {
            background: #f1f3f5;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .close-modal:hover {
            background: #e2e8f0;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        a { 
            color: #38bdf8; 
            text-decoration: none; 
        }
        .btn-back {
            padding: 8px 16px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.4);
            background: none;
            color: #fff;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .btn-back:hover {
            background: rgba(255,255,255,0.1);
        }
        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
            }
            form {
                grid-template-columns: 1fr;
            }
            table {
                overflow-x: scroll;
                display: block;
            }
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    
    <main>
        <h1 style="margin-top: 0; margin-bottom: 32px; color: #0f172a; font-size: 2rem;">🚗 Quản lý xe và hãng xe</h1>
        
        <?php if($flash): ?>
            <div class="flash"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab active" onclick="switchTab('cars', this)">🚗 Quản lý xe</button>
            <button class="tab" onclick="switchTab('edit', this)">✏️ Sửa thông tin xe</button>
            <button class="tab" onclick="switchTab('brands', this)">🏭 Quản lý hãng xe</button>
        </div>
        
        <!-- Tab: Quản lý xe -->
        <div id="tab-cars" class="tab-content active">
            <div class="card">
                <div class="card-title">➕ Thêm xe mới</div>
                <form method="post" enctype="multipart/form-data" id="createCarForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Hãng xe *</label>
                            <select name="hang_xe_id" id="hang_xe_id" required>
                                <option value="">-- Chọn hãng xe --</option>
                                <?php foreach($brands as $b): ?>
                                    <option value="<?= htmlspecialchars($b['ma_hangxe']) ?>"><?= htmlspecialchars($b['ten_hangxe']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display: block; margin-top: 4px; color: #64748b; font-size: 0.85rem;">Chỉ có thể chọn từ danh sách hãng xe có sẵn</small>
                        </div>
                        <div class="form-group">
                            <label>Loại xe *</label>
                            <input type="text" name="loai_xe" placeholder="Ví dụ: Sedan, SUV, Hatchback" required>
                        </div>
                        <div class="form-group">
                            <label>Màu xe</label>
                            <input type="text" name="mau_xe" placeholder="Ví dụ: Đen, Trắng, Xám">
                        </div>
                        <div class="form-group">
                            <label>Năm sản xuất</label>
                            <input type="number" name="nam_san_xuat" placeholder="Ví dụ: 2024" min="1900" max="<?= date('Y') + 1 ?>" value="<?= date('Y') ?>">
                        </div>
                        <div class="form-group">
                            <label>Giá bán (₫) *</label>
                            <input type="number" name="gia_ban" placeholder="Nhập giá bán" step="1000" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Tình trạng</label>
                            <select name="tinh_trang">
                                <?php foreach($tinh_trang_options as $k=>$v): ?>
                                    <option value="<?= $k ?>"><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label>Mô tả</label>
                            <textarea name="mo_ta" placeholder="Mô tả chi tiết về xe..."></textarea>
                        </div>
                        <div class="form-group full-width">
                            <label>Hình ảnh xe * (Tối thiểu 1 tấm, tối đa 10 tấm)</label>
                            <input type="file" name="hinh_anh[]" id="hinh_anh" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" multiple required onchange="previewImages(this)">
                            <small style="display: block; margin-top: 4px; color: #64748b; font-size: 0.85rem;">Chấp nhận: JPG, PNG, GIF, WEBP (tối đa 5MB mỗi file)</small>
                            <div id="imagePreview" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; margin-top: 12px;"></div>
                        </div>
                    </div>
                    <button type="submit" name="add_car" style="grid-column: 1 / -1;">➕ Thêm xe</button>
                </form>
            </div>
            
            <div class="card">
                <div class="card-title">📋 Danh sách xe (<?= count($cars) ?>)</div>
                <?php if(empty($cars)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🚗</div>
                        <p>Chưa có xe nào trong hệ thống</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Hãng</th>
                                    <th>Loại</th>
                                    <th>Giá bán</th>
                                    <th>Tình trạng</th>
                                    <th>Màu</th>
                                    <th>Năm SX</th>
                                    <th>Mô tả</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($cars as $car): ?>
                                    <tr>
                                        <td><strong>#<?= $car['ma_xe'] ?></strong></td>
                                        <td><strong><?= htmlspecialchars($car['brand_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($car['loai_xe']) ?></td>
                                        <td><strong style="color: #22c55e;"><?= number_format($car['gia_ban'],0,',','.') ?> ₫</strong></td>
                                        <td>
                                            <?php 
                                            $status = $car['tinh_trang'];
                                            $badgeClass = $status === 'Moi' ? 'badge-new' : ($status === 'Da_qua_su_dung' ? 'badge-used' : 'badge-selling');
                                            ?>
                                            <span class="badge <?= $badgeClass ?>">
                                                <?= $tinh_trang_options[$status] ?? $status ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($car['mau_xe']) ?></td>
                                        <td><?= htmlspecialchars($car['nam_san_xuat']) ?></td>
                                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= htmlspecialchars($car['mo_ta']) ?>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($car, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)" class="btn-warning btn-sm">✏️ Sửa</button>
                                                <form method="post" onsubmit="return confirm('Bạn có chắc muốn xóa xe này?');" style="display: inline;">
                                                    <input type="hidden" name="ma_xe" value="<?= $car['ma_xe'] ?>">
                                                    <button type="submit" name="delete_car" class="btn-danger btn-sm">🗑️ Xóa</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tab: Sửa thông tin xe -->
        <div id="tab-edit" class="tab-content">
            <div class="card">
                <div class="card-title">✏️ Sửa thông tin xe</div>
                <p style="color: #6c757d; margin-bottom: 20px;">Chọn xe từ danh sách "Quản lý xe" và nhấn nút "Sửa" để chỉnh sửa thông tin.</p>
                <?php if(empty($cars)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📝</div>
                        <p>Chưa có xe nào để chỉnh sửa</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Hãng</th>
                                    <th>Loại</th>
                                    <th>Giá bán</th>
                                    <th>Tình trạng</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($cars as $car): ?>
                                    <tr>
                                        <td><strong>#<?= $car['ma_xe'] ?></strong></td>
                                        <td><strong><?= htmlspecialchars($car['brand_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($car['loai_xe']) ?></td>
                                        <td><strong style="color: #22c55e;"><?= number_format($car['gia_ban'],0,',','.') ?> ₫</strong></td>
                                        <td>
                                            <?php 
                                            $status = $car['tinh_trang'];
                                            $badgeClass = $status === 'Moi' ? 'badge-new' : ($status === 'Da_qua_su_dung' ? 'badge-used' : 'badge-selling');
                                            ?>
                                            <span class="badge <?= $badgeClass ?>">
                                                <?= $tinh_trang_options[$status] ?? $status ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($car, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)" class="btn-warning btn-sm">✏️ Sửa</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tab: Quản lý hãng xe -->
        <div id="tab-brands" class="tab-content">
            <div class="card">
                <div class="card-title">➕ Thêm hãng xe mới</div>
                <form method="post">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Tên hãng xe *</label>
                            <input type="text" name="ten_hangxe" placeholder="Ví dụ: Toyota, Honda, Ford" required>
                        </div>
                    </div>
                    <button type="submit" name="add_brand" style="grid-column: 1 / -1;">➕ Thêm hãng</button>
                </form>
            </div>
            
            <div class="card">
                <div class="card-title">📋 Danh sách hãng xe (<?= count($brands) ?>)</div>
                <?php if(empty($brands)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🏭</div>
                        <p>Chưa có hãng xe nào trong hệ thống</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tên hãng</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($brands as $brand): ?>
                                    <tr>
                                        <td><strong>#<?= $brand['ma_hangxe'] ?></strong></td>
                                        <td><strong><?= htmlspecialchars($brand['ten_hangxe']) ?></strong></td>
                                        <td>
                                            <div class="actions">
                                                <button onclick="openEditBrandModal(<?= htmlspecialchars(json_encode($brand, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)" class="btn-warning btn-sm">✏️ Sửa</button>
                                                <form method="post" onsubmit="return confirm('Bạn có chắc muốn xóa hãng xe này?');" style="display: inline;">
                                                    <input type="hidden" name="ma_hangxe" value="<?= $brand['ma_hangxe'] ?>">
                                                    <button type="submit" name="delete_brand" class="btn-danger btn-sm">🗑️ Xóa</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Modal: Sửa xe -->
    <div id="editCarModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Sửa thông tin xe</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="post" id="editCarForm">
                <input type="hidden" name="ma_xe" id="edit_ma_xe">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Hãng xe *</label>
                        <select name="hang_xe_id" id="edit_hang_xe" required>
                            <option value="">-- Chọn hãng --</option>
                            <?php foreach($brands as $b): ?>
                                <option value="<?= htmlspecialchars($b['ma_hangxe']) ?>"><?= htmlspecialchars($b['ten_hangxe']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Loại xe *</label>
                        <input type="text" name="loai_xe" id="edit_loai_xe" required>
                    </div>
                    <div class="form-group">
                        <label>Giá bán (₫) *</label>
                        <input type="number" name="gia_ban" id="edit_gia_ban" step="1000" required>
                    </div>
                    <div class="form-group">
                        <label>Tình trạng *</label>
                        <select name="tinh_trang" id="edit_tinh_trang" required>
                            <?php foreach($tinh_trang_options as $k=>$v): ?>
                                <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Màu xe *</label>
                        <input type="text" name="mau_xe" id="edit_mau_xe" required>
                    </div>
                    <div class="form-group">
                        <label>Năm sản xuất *</label>
                        <input type="number" name="nam_san_xuat" id="edit_nam_san_xuat" min="1900" max="2100" required>
                    </div>
                    <div class="form-group full-width">
                        <label>Mô tả</label>
                        <textarea name="mo_ta" id="edit_mo_ta" placeholder="Mô tả chi tiết về xe..."></textarea>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px; grid-column: 1 / -1;">
                    <button type="submit" name="edit_car" class="btn-success">💾 Lưu thay đổi</button>
                    <button type="button" onclick="closeEditModal()" style="background: #64748b; color: white;">Hủy</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal: Sửa hãng xe -->
    <div id="editBrandModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Sửa hãng xe</h3>
                <button class="close-modal" onclick="closeEditBrandModal()">&times;</button>
            </div>
            <form method="post" id="editBrandForm">
                <input type="hidden" name="ma_hangxe" id="edit_brand_ma_hangxe">
                <div class="form-group">
                    <label>Tên hãng xe *</label>
                    <input type="text" name="ten_hangxe" id="edit_brand_ten_hangxe" placeholder="Ví dụ: Toyota, Honda, Ford" required>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="edit_brand" class="btn-success">💾 Lưu thay đổi</button>
                    <button type="button" onclick="closeEditBrandModal()" style="background: #64748b; color: white;">Hủy</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Chuyển đổi tab
        function switchTab(tabName, tabButton) {
            // Ẩn tất cả tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Xóa active từ tất cả tab buttons
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Hiển thị tab được chọn
            document.getElementById('tab-' + tabName).classList.add('active');
            
            // Thêm active cho tab button
            tabButton.classList.add('active');
        }
        
        // Mở modal sửa xe
        function openEditModal(car) {
            document.getElementById('edit_ma_xe').value = car.ma_xe;
            document.getElementById('edit_hang_xe').value = car.ma_hangxe || '';
            document.getElementById('edit_loai_xe').value = car.loai_xe;
            document.getElementById('edit_gia_ban').value = car.gia_ban;
            document.getElementById('edit_tinh_trang').value = car.tinh_trang;
            document.getElementById('edit_mau_xe').value = car.mau_xe;
            document.getElementById('edit_nam_san_xuat').value = car.nam_san_xuat;
            document.getElementById('edit_mo_ta').value = car.mo_ta || '';
            document.getElementById('editCarModal').classList.add('active');
        }
        
        // Đóng modal sửa xe
        function closeEditModal() {
            document.getElementById('editCarModal').classList.remove('active');
        }
        
        // Mở modal sửa hãng xe
        function openEditBrandModal(brand) {
            document.getElementById('edit_brand_ma_hangxe').value = brand.ma_hangxe;
            document.getElementById('edit_brand_ten_hangxe').value = brand.ten_hangxe;
            document.getElementById('editBrandModal').classList.add('active');
        }
        
        // Đóng modal sửa hãng xe
        function closeEditBrandModal() {
            document.getElementById('editBrandModal').classList.remove('active');
        }
        
        // Đóng modal khi click bên ngoài
        window.onclick = function(event) {
            const editCarModal = document.getElementById('editCarModal');
            const editBrandModal = document.getElementById('editBrandModal');
            if (event.target == editCarModal) {
                closeEditModal();
            }
            if (event.target == editBrandModal) {
                closeEditBrandModal();
            }
        }
        
        // Đóng modal bằng phím ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
                closeEditBrandModal();
            }
        });
        
        // Preview hình ảnh
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
        
        // Validate form
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
    </style>
</body>
</html>