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

// Lấy danh sách thanh toán
$payments = [];
$payResult = pg_query($conn, "SELECT tt.ma_thanhtoan, tt.ngay, tt.so_lien, tt.phuong_thuc, tt.trang_thai_tt,
                                     dh.ma_donhang
                              FROM thanh_toan tt
                              LEFT JOIN don_hang dh ON tt.ma_donhang = dh.ma_donhang
                              ORDER BY tt.ma_thanhtoan DESC");
if ($payResult) {
    while ($row = pg_fetch_assoc($payResult)) {
        $payments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý thanh toán | AutoLux</title>
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
        .status {
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-Thanh_cong { background: rgba(34,197,94,0.18); color: #15803d; }
        .status-Cho { background: rgba(251,191,36,0.18); color: #92400e; }
        .status-That_bai { background: rgba(239,68,68,0.18); color: #991b1b; }
        .price {
            font-weight: 600;
            color: #3b82f6;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    <main>
        <div class="container">
            <a href="admin.php" class="btn-back">← Quay lại trang quản trị</a>
            
            <h2>Quản lý thanh toán</h2>
            
            <?php if (empty($payments)): ?>
                <p>Chưa có giao dịch thanh toán nào.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Mã thanh toán</th>
                            <th>Mã đơn hàng</th>
                            <th>Ngày</th>
                            <th>Số tiền</th>
                            <th>Phương thức</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= htmlspecialchars($payment['ma_thanhtoan']) ?></td>
                                <td><?= htmlspecialchars($payment['ma_donhang']) ?></td>
                                <td><?= htmlspecialchars($payment['ngay'] ?? 'N/A') ?></td>
                                <td class="price"><?= number_format($payment['so_lien'] ?? 0, 0, ',', '.') ?> đ</td>
                                <td><?= htmlspecialchars($payment['phuong_thuc'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status status-<?= htmlspecialchars($payment['trang_thai_tt'] ?? '') ?>">
                                        <?= htmlspecialchars($payment['trang_thai_tt'] ?? 'N/A') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

