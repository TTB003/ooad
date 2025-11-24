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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản trị hệ thống | AutoLux</title>
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
        .settings-section {
            background: #fff;
            padding: 24px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
            margin-top: 32px;
        }
        .settings-section h3 {
            margin-top: 0;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
        }
        .info-item {
            padding: 12px 0;
            border-bottom: 1px solid rgba(15,23,42,0.06);
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #64748b;
        }
        .info-value {
            color: #0f172a;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    <main>
        <div class="container">
            <a href="admin.php" class="btn-back">← Quay lại trang quản trị</a>
            
            <h2>Quản trị hệ thống</h2>
            
            <div class="settings-section">
                <h3>Thông tin hệ thống</h3>
                <div class="info-item">
                    <div class="info-label">Phiên bản PHP</div>
                    <div class="info-value"><?= phpversion() ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Cơ sở dữ liệu</div>
                    <div class="info-value">PostgreSQL</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Server</div>
                    <div class="info-value"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></div>
                </div>
            </div>
            
            <div class="settings-section">
                <h3>Cấu hình hệ thống</h3>
                <p>Các tính năng cấu hình hệ thống sẽ được phát triển trong tương lai.</p>
            </div>
        </div>
    </main>
</body>
</html>

