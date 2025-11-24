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

// Lấy danh sách bảo hành
$warranties = [];
$warrResult = pg_query($conn, "SELECT bh.ma_baohanh, bh.ngay_baohanh, bh.ngay_het_bh, bh.noi_dung,
                                      kh.ho_ten as khach_hang
                               FROM bao_hanh_dich_vu bh
                               LEFT JOIN khach_hang kh ON bh.ma_khachhang = kh.ma_khachhang
                               ORDER BY bh.ma_baohanh DESC");
if ($warrResult) {
    while ($row = pg_fetch_assoc($warrResult)) {
        $warranties[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý bảo hành – bảo dưỡng | AutoLux</title>
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
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    <main>
        <div class="container">
            <a href="admin.php" class="btn-back">← Quay lại trang quản trị</a>
            
            <h2>Quản lý bảo hành – bảo dưỡng</h2>
            
            <?php if (empty($warranties)): ?>
                <p>Chưa có dịch vụ bảo hành nào.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Mã bảo hành</th>
                            <th>Khách hàng</th>
                            <th>Ngày bảo hành</th>
                            <th>Ngày hết bảo hành</th>
                            <th>Nội dung</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($warranties as $warranty): ?>
                            <tr>
                                <td><?= htmlspecialchars($warranty['ma_baohanh']) ?></td>
                                <td><?= htmlspecialchars($warranty['khach_hang'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($warranty['ngay_baohanh'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($warranty['ngay_het_bh'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($warranty['noi_dung'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

