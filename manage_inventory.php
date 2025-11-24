<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php?type=staff');
    exit;
}

$conn = db();
$isAdmin = ($_SESSION['staff_role'] ?? '') === 'Admin';

if (!$isAdmin) {
    header('Location: admin.php');
    exit;
}

// Lấy danh sách tồn kho
$inventory = [];
$invResult = pg_query($conn, "SELECT tk.ma_baotri, tk.so_luong_nhap, tk.so_luong_ban, tk.so_luong_ton, 
                                     tk.ngay_cap_nhat, nv.ho_ten as nhan_vien
                              FROM ton_kho tk
                              LEFT JOIN nhan_vien nv ON tk.ma_nhanvien = nv.ma_nhanvien
                              ORDER BY tk.ma_baotri DESC");
if ($invResult) {
    while ($row = pg_fetch_assoc($invResult)) {
        $inventory[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý kho | AutoLux</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            font-family: "Inter", Arial, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
        }
        header {
            padding: 28px 5vw;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header h1 { margin: 0; }
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
        .actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .btn-logout {
            padding: 8px 16px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.4);
            background: none;
            color: #fff;
            text-decoration: none;
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
        tr:hover {
            background: #f8fafc;
        }
        .low-stock {
            color: #ef4444;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    <main>
        <div class="container">
            <a href="admin.php" class="btn-back">← Quay lại trang quản trị</a>
            
            <h2>Quản lý kho</h2>
            
            <?php if (empty($inventory)): ?>
                <p>Chưa có dữ liệu tồn kho.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Mã bảo trì</th>
                            <th>Số lượng nhập</th>
                            <th>Số lượng bán</th>
                            <th>Số lượng tồn</th>
                            <th>Ngày cập nhật</th>
                            <th>Nhân viên</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['ma_baotri']) ?></td>
                                <td><?= htmlspecialchars($item['so_luong_nhap'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($item['so_luong_ban'] ?? 0) ?></td>
                                <td class="<?= ($item['so_luong_ton'] ?? 0) < 10 ? 'low-stock' : '' ?>">
                                    <?= htmlspecialchars($item['so_luong_ton'] ?? 0) ?>
                                </td>
                                <td><?= htmlspecialchars($item['ngay_cap_nhat'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['nhan_vien'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

