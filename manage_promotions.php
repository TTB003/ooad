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

// Lấy danh sách khuyến mãi
$promotions = [];
$promoResult = pg_query($conn, "SELECT ma_khuyenmai, ten_khuyenmai, muc_giam, ngay_batdau, ngay_ketthuc, dieu_kien
                                FROM khuyen_mai
                                ORDER BY ma_khuyenmai DESC");
if ($promoResult) {
    while ($row = pg_fetch_assoc($promoResult)) {
        $promotions[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý khuyến mãi | AutoLux</title>
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
        .discount {
            font-weight: 600;
            color: #ef4444;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    <main>
        <div class="container">
            <a href="admin.php" class="btn-back">← Quay lại trang quản trị</a>
            
            <h2>Quản lý khuyến mãi</h2>
            
            <?php if (empty($promotions)): ?>
                <p>Chưa có chương trình khuyến mãi nào.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Mã KM</th>
                            <th>Tên khuyến mãi</th>
                            <th>Mức giảm</th>
                            <th>Ngày bắt đầu</th>
                            <th>Ngày kết thúc</th>
                            <th>Điều kiện</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promotions as $promo): ?>
                            <tr>
                                <td><?= htmlspecialchars($promo['ma_khuyenmai']) ?></td>
                                <td><?= htmlspecialchars($promo['ten_khuyenmai']) ?></td>
                                <td class="discount"><?= htmlspecialchars($promo['muc_giam'] ?? 0) ?>%</td>
                                <td><?= htmlspecialchars($promo['ngay_batdau'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($promo['ngay_ketthuc'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($promo['dieu_kien'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

