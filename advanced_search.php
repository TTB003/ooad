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

$results = [];
$searchType = $_GET['type'] ?? 'all';
$keyword = trim($_GET['keyword'] ?? '');

if ($keyword && $_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($searchType) {
        case 'car':
            $query = pg_query_params($conn, 
                "SELECT ma_xe, hang_xe, loai_xe, mau_xe, gia_ban 
                 FROM xe 
                 WHERE LOWER(hang_xe) LIKE LOWER($1) 
                    OR LOWER(loai_xe) LIKE LOWER($1)
                    OR LOWER(mau_xe) LIKE LOWER($1)
                 LIMIT 50",
                ['%' . $keyword . '%']
            );
            if ($query) {
                while ($row = pg_fetch_assoc($query)) {
                    $results[] = ['type' => 'car', 'data' => $row];
                }
            }
            break;
        case 'customer':
            $query = pg_query_params($conn,
                "SELECT ma_khachhang, ho_ten, email, sdt 
                 FROM khach_hang 
                 WHERE LOWER(ho_ten) LIKE LOWER($1) 
                    OR LOWER(email) LIKE LOWER($1)
                    OR sdt LIKE $1
                 LIMIT 50",
                ['%' . $keyword . '%']
            );
            if ($query) {
                while ($row = pg_fetch_assoc($query)) {
                    $results[] = ['type' => 'customer', 'data' => $row];
                }
            }
            break;
        case 'order':
            $query = pg_query_params($conn,
                "SELECT dh.ma_donhang, dh.ngay_lap, dh.tong_tien, kh.ho_ten
                 FROM don_hang dh
                 LEFT JOIN khach_hang kh ON dh.ma_khachhang = kh.ma_khachhang
                 WHERE CAST(dh.ma_donhang AS TEXT) LIKE $1
                    OR LOWER(kh.ho_ten) LIKE LOWER($1)
                 LIMIT 50",
                ['%' . $keyword . '%']
            );
            if ($query) {
                while ($row = pg_fetch_assoc($query)) {
                    $results[] = ['type' => 'order', 'data' => $row];
                }
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tìm kiếm nâng cao | AutoLux</title>
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
        .search-form {
            background: #fff;
            padding: 24px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
            margin-bottom: 32px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }
        input, select {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid rgba(15,23,42,0.15);
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        button {
            padding: 12px 24px;
            border-radius: 999px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            color: #fff;
            background: linear-gradient(120deg, #06b6d4, #3b82f6);
        }
        .results {
            background: #fff;
            padding: 24px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
        }
        .result-item {
            padding: 16px;
            border-bottom: 1px solid rgba(15,23,42,0.06);
        }
        .result-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    <main>
        <div class="container">
            <a href="admin.php" class="btn-back">← Quay lại trang quản trị</a>
            
            <h2>Tìm kiếm nâng cao</h2>
            
            <div class="search-form">
                <form method="get" action="advanced_search.php">
                    <div class="form-group">
                        <label for="type">Loại tìm kiếm</label>
                        <select id="type" name="type">
                            <option value="car" <?= $searchType === 'car' ? 'selected' : '' ?>>Tìm kiếm xe</option>
                            <option value="customer" <?= $searchType === 'customer' ? 'selected' : '' ?>>Tìm kiếm khách hàng</option>
                            <option value="order" <?= $searchType === 'order' ? 'selected' : '' ?>>Tìm kiếm đơn hàng</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="keyword">Từ khóa</label>
                        <input id="keyword" name="keyword" type="text" value="<?= htmlspecialchars($keyword) ?>" placeholder="Nhập từ khóa tìm kiếm...">
                    </div>
                    <button type="submit">Tìm kiếm</button>
                </form>
            </div>
            
            <?php if (!empty($results)): ?>
                <div class="results">
                    <h3>Kết quả tìm kiếm (<?= count($results) ?> kết quả)</h3>
                    <?php foreach ($results as $result): ?>
                        <div class="result-item">
                            <?php if ($result['type'] === 'car'): ?>
                                <strong>Xe:</strong> <?= htmlspecialchars($result['data']['hang_xe']) ?> - 
                                <?= htmlspecialchars($result['data']['loai_xe'] ?? '') ?> - 
                                <?= htmlspecialchars($result['data']['mau_xe'] ?? '') ?> - 
                                <?= number_format($result['data']['gia_ban'], 0, ',', '.') ?> đ
                            <?php elseif ($result['type'] === 'customer'): ?>
                                <strong>Khách hàng:</strong> <?= htmlspecialchars($result['data']['ho_ten']) ?> - 
                                <?= htmlspecialchars($result['data']['email'] ?? '') ?> - 
                                <?= htmlspecialchars($result['data']['sdt'] ?? '') ?>
                            <?php elseif ($result['type'] === 'order'): ?>
                                <strong>Đơn hàng:</strong> #<?= htmlspecialchars($result['data']['ma_donhang']) ?> - 
                                <?= htmlspecialchars($result['data']['ho_ten'] ?? '') ?> - 
                                <?= number_format($result['data']['tong_tien'] ?? 0, 0, ',', '.') ?> đ
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($keyword): ?>
                <div class="results">
                    <p>Không tìm thấy kết quả nào.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

