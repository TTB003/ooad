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

// Thống kê tổng quan
$stats = [];
$stats['total_cars'] = pg_fetch_assoc(pg_query($conn, "SELECT COUNT(*) as count FROM xe"))['count'] ?? 0;
$stats['total_customers'] = pg_fetch_assoc(pg_query($conn, "SELECT COUNT(*) as count FROM khach_hang"))['count'] ?? 0;
$stats['total_orders'] = pg_fetch_assoc(pg_query($conn, "SELECT COUNT(*) as count FROM don_hang"))['count'] ?? 0;
$stats['total_revenue'] = pg_fetch_assoc(pg_query($conn, "SELECT COALESCE(SUM(tong_tien), 0) as total FROM don_hang WHERE trang_thai = 'Da_thanh_toan'"))['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thống kê & báo cáo | AutoLux</title>
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-top: 32px;
        }
        .stat-card {
            background: #fff;
            padding: 24px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
        }
        .stat-card h3 {
            margin: 0 0 12px 0;
            color: #64748b;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    <main>
        <div class="container">
            <a href="admin.php" class="btn-back">← Quay lại trang quản trị</a>
            
            <h2>Thống kê & báo cáo</h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Tổng số xe</h3>
                    <p class="value"><?= number_format($stats['total_cars']) ?></p>
                </div>
                <div class="stat-card">
                    <h3>Tổng số khách hàng</h3>
                    <p class="value"><?= number_format($stats['total_customers']) ?></p>
                </div>
                <div class="stat-card">
                    <h3>Tổng số đơn hàng</h3>
                    <p class="value"><?= number_format($stats['total_orders']) ?></p>
                </div>
                <div class="stat-card">
                    <h3>Tổng doanh thu</h3>
                    <p class="value"><?= number_format($stats['total_revenue'], 0, ',', '.') ?> đ</p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>

