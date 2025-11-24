<?php
session_start();
require_once __DIR__ . '/config.php';

$errorMessage = '';
$successMessage = '';
$isStaff = isset($_SESSION['staff_id']);
$isCustomer = isset($_SESSION['customer_id']);
$isAdmin = $isStaff && ($_SESSION['staff_role'] ?? '') === 'Admin';

if (!$isStaff && !$isCustomer) {
    header('Location: login.php');
    exit;
}

// Chặn admin truy cập trang đổi mật khẩu
if ($isAdmin) {
    header('Location: admin.php');
    exit;
}

$conn = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $errorMessage = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = 'Mật khẩu mới và xác nhận không khớp.';
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d).{6,}$/', $newPassword)) {
        $errorMessage = 'Mật khẩu mới phải tối thiểu 6 ký tự, có ít nhất 1 chữ in hoa và 1 số.';
    } else {
        if ($isStaff) {
            // Kiểm tra mật khẩu hiện tại cho nhân viên
            $staffId = $_SESSION['staff_id'];
            $result = pg_query_params(
                $conn,
                "SELECT COALESCE(tk.hash_matkhau, nv.mat_khau) AS password_hash
                 FROM nhan_vien nv
                 LEFT JOIN tai_khoan tk ON tk.ma_nhanvien = nv.ma_nhanvien
                 WHERE nv.ma_nhanvien = $1",
                [$staffId]
            );
            
            if ($row = pg_fetch_assoc($result)) {
                if (password_verify($currentPassword, $row['password_hash'])) {
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                    // Cập nhật mật khẩu trong bảng nhan_vien
                    $update = pg_query_params(
                        $conn,
                        'UPDATE nhan_vien SET mat_khau = $1 WHERE ma_nhanvien = $2',
                        [$hash, $staffId]
                    );
                    // Cập nhật mật khẩu trong bảng tai_khoan nếu có
                    $updateAccount = pg_query_params(
                        $conn,
                        'UPDATE tai_khoan SET hash_matkhau = $1 WHERE ma_nhanvien = $2',
                        [$hash, $staffId]
                    );
                    
                    if ($update) {
                        $successMessage = 'Đổi mật khẩu thành công!';
                    } else {
                        $errorMessage = 'Không thể cập nhật mật khẩu: ' . pg_last_error($conn);
                    }
                } else {
                    $errorMessage = 'Mật khẩu hiện tại không đúng.';
                }
            }
        } elseif ($isCustomer) {
            // Kiểm tra mật khẩu hiện tại cho khách hàng
            $customerId = $_SESSION['customer_id'];
            $result = pg_query_params(
                $conn,
                'SELECT mat_khau FROM khach_hang WHERE ma_khachhang = $1',
                [$customerId]
            );
            
            if ($row = pg_fetch_assoc($result)) {
                if (password_verify($currentPassword, $row['mat_khau'])) {
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $update = pg_query_params(
                        $conn,
                        'UPDATE khach_hang SET mat_khau = $1 WHERE ma_khachhang = $2',
                        [$hash, $customerId]
                    );
                    
                    if ($update) {
                        $successMessage = 'Đổi mật khẩu thành công!';
                    } else {
                        $errorMessage = 'Không thể cập nhật mật khẩu: ' . pg_last_error($conn);
                    }
                } else {
                    $errorMessage = 'Mật khẩu hiện tại không đúng.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đổi mật khẩu | AutoLux</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        main {
            width: min(480px, 100%);
            background: #fff;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 30px 80px rgba(15,23,42,0.3);
        }
        h1 {
            margin-top: 0;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 24px;
            font-size: 0.9rem;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        input {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid rgba(15,23,42,0.15);
            font-size: 0.95rem;
            box-sizing: border-box;
            margin-bottom: 18px;
        }
        button {
            width: 100%;
            padding: 14px;
            border-radius: 999px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            background: linear-gradient(120deg, #ff4d4f, #f97316);
            color: #fff;
            cursor: pointer;
        }
        button:hover {
            opacity: 0.9;
        }
        a {
            display: inline-block;
            margin-top: 16px;
            color: #2563eb;
            text-decoration: none;
            font-size: 0.9rem;
        }
        a:hover {
            text-decoration: underline;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.3);
            color: #991b1b;
        }
        .alert-success {
            background: rgba(34,197,94,0.18);
            border: 1px solid rgba(34,197,94,0.3);
            color: #166534;
        }
    </style>
</head>
<body>
    <main>
        <h1>Đổi mật khẩu</h1>
        <p class="subtitle">Vui lòng nhập mật khẩu hiện tại và mật khẩu mới</p>
        
        <?php if ($errorMessage): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>
        
        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
        
        <form action="change_password.php" method="post">
            <label for="current_password">Mật khẩu hiện tại</label>
            <input id="current_password" name="current_password" type="password" required>
            
            <label for="new_password">Mật khẩu mới</label>
            <input id="new_password" name="new_password" type="password" required>
            <small style="color: #64748b; font-size: 0.85rem; display: block; margin-top: -16px; margin-bottom: 18px;">
                Tối thiểu 6 ký tự, có ít nhất 1 chữ in hoa và 1 số
            </small>
            
            <label for="confirm_password">Xác nhận mật khẩu mới</label>
            <input id="confirm_password" name="confirm_password" type="password" required>
            
            <button type="submit">Đổi mật khẩu</button>
        </form>
        
        <?php if ($isStaff): ?>
            <a href="admin.php">← Quay lại trang quản lý</a>
        <?php else: ?>
            <a href="index.php">← Quay lại trang chủ</a>
        <?php endif; ?>
    </main>
</body>
</html>

