<?php
session_start();
require_once __DIR__ . '/config.php';

$title = 'Đăng nhập hệ thống AutoLux';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errorMessage = 'Vui lòng nhập đầy đủ thông tin.';
    } else {
        $conn = db();
        $loggedIn = false;
        
        // Thử đăng nhập với nhân viên/admin trước
        $sql = "SELECT nv.ma_nhanvien,
                       nv.ho_ten,
                       nv.chuc_vu,
                       lk.ten_loai,
                       COALESCE(tk.hash_matkhau, nv.mat_khau) AS password_hash,
                       COALESCE(tk.trang_thai, 'active') AS trang_thai
                FROM nhan_vien nv
                INNER JOIN loai_tai_khoan lk ON nv.ma_loaitk = lk.ma_loaitk
                LEFT JOIN tai_khoan tk ON tk.ma_nhanvien = nv.ma_nhanvien
                WHERE (LOWER(nv.email) = LOWER($1) OR LOWER(tk.username) = LOWER($1))
                  AND lk.ten_loai IN ('Admin','NhanVien')
                LIMIT 1";
        $result = pg_query_params($conn, $sql, [$username]);

        if ($row = pg_fetch_assoc($result)) {
            // Kiểm tra trạng thái tài khoản
            if (($row['trang_thai'] ?? 'active') === 'disabled') {
                $errorMessage = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ 0000000000 để được giải quyết.';
            } else {
                $hash = $row['password_hash'] ?? '';
                if ($hash && password_verify($password, $hash)) {
                    $_SESSION['staff_id'] = $row['ma_nhanvien'];
                    $_SESSION['staff_name'] = $row['ho_ten'];
                    $_SESSION['staff_role'] = $row['ten_loai'];
                    $_SESSION['staff_position'] = $row['chuc_vu'] ?? '';
                    $_SESSION['flash_welcome'] = 'Xin chào ' . $row['ho_ten'] . '!';
                    header('Location: index.php');
                    exit;
                }
            }
        }
        
        // Nếu không phải nhân viên/admin, thử đăng nhập với khách hàng
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            // Kiểm tra tài khoản khách hàng có bị khóa không
            $customerCheck = pg_query_params(
                $conn,
                "SELECT kh.ma_khachhang, kh.ho_ten, kh.mat_khau, COALESCE(tk.trang_thai, 'active') AS trang_thai
                 FROM khach_hang kh
                 LEFT JOIN tai_khoan tk ON tk.ma_khachhang = kh.ma_khachhang
                 WHERE kh.email = $1 LIMIT 1",
                [$username]
            );

            if ($row = pg_fetch_assoc($customerCheck)) {
                // Kiểm tra trạng thái tài khoản
                if (($row['trang_thai'] ?? 'active') === 'disabled') {
                    $errorMessage = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ 0000000000 để được giải quyết.';
                } elseif (password_verify($password, $row['mat_khau'])) {
                    $_SESSION['customer_id'] = $row['ma_khachhang'];
                    $_SESSION['customer_name'] = $row['ho_ten'];
                    $_SESSION['flash_welcome'] = 'Xin chào ' . $row['ho_ten'] . '!';
                    header('Location: index.php');
                    exit;
                }
            }
        }
        
        if (!$errorMessage) {
            $errorMessage = 'Thông tin đăng nhập không đúng.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: linear-gradient(120deg, #020617, #1e293b);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        main {
            width: min(420px, 100%);
            background: rgba(15,23,42,0.85);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 20px 45px rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.1);
        }
        h1 { margin-top: 0; }
        label { font-weight: 600; font-size: 0.9rem; }
        input {
            width: 100%;
            margin: 8px 0 16px;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(15,23,42,0.6);
            color: #fff;
        }
        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 999px;
            background: #ff4d4f;
            color: #fff;
            font-size: 1rem;
            cursor: pointer;
            font-weight: 600;
        }
        p { margin: 12px 0 0; }
        a { color: #38bdf8; text-decoration: none; }
        .error {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.5);
            color: #fecaca;
            padding: 10px 12px;
            border-radius: 12px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
<main>
    <h1>Đăng nhập</h1>
    <p>Nhập email/username và mật khẩu để đăng nhập. Hệ thống sẽ tự động phân biệt loại tài khoản.</p>
    
    <?php if ($errorMessage): ?>
        <div class="error"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>
    
    <form action="login.php" method="post">
        <label for="username">Email hoặc username</label>
        <input id="username" name="username" type="text" placeholder="email@example.com hoặc username" required>

        <label for="password">Mật khẩu</label>
        <input id="password" name="password" type="password" placeholder="••••••••" required>

        <button type="submit">Đăng nhập</button>
    </form>
    
    <p>Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
    
    <p><a href="index.php">← Quay lại trang chủ</a></p>
</main>
</body>
</html>

