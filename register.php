<?php
session_start();
require_once __DIR__ . '/config.php';

$errors = [];
$old = ['fullname' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $old = ['fullname' => $fullName, 'email' => $email, 'phone' => $phone, 'address' => $address];

    if ($password !== $confirm) {
        $errors[] = 'Mật khẩu xác nhận không khớp.';
    }

    if (!preg_match('/^(?=.*[A-Z])(?=.*\d).{6,}$/', $password)) {
        $errors[] = 'Mật khẩu phải tối thiểu 6 ký tự, có ít nhất 1 chữ in hoa và 1 số.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }

    if (!preg_match('/^0\d{9}$/', $phone)) {
        $errors[] = 'Số điện thoại phải bắt đầu bằng 0 và gồm 10 chữ số.';
    }

    if (empty($errors)) {
        $conn = db();

        $dupCheck = pg_query_params(
            $conn,
            'SELECT 1 FROM khach_hang WHERE email = $1',
            [$email]
        );

        if ($dupCheck && pg_num_rows($dupCheck) > 0) {
            $errors[] = 'Email đã tồn tại trong hệ thống.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $result = pg_query_params(
                $conn,
                'INSERT INTO khach_hang (ho_ten, email, sdt, diachi, mat_khau) VALUES ($1,$2,$3,$4,$5) RETURNING ma_khachhang',
                [$fullName, $email, $phone, $address, $hash]
            );

            if ($result === false) {
                $errors[] = 'Có lỗi khi lưu dữ liệu: ' . pg_last_error($conn);
            } else {
                $user = pg_fetch_assoc($result);
                $_SESSION['customer_id'] = $user['ma_khachhang'];
                $_SESSION['customer_name'] = $fullName;
                $_SESSION['flash_welcome'] = 'Chào mừng ' . $fullName . '!';
                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký tài khoản AutoLux</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            font-family: "Inter", Arial, sans-serif;
            background: #020617;
            color: #0f172a;
            min-height: 100vh;
        }
        header {
            padding: 28px;
            text-align: center;
            color: #fff;
        }
        main {
            background: #fff;
            border-top-left-radius: 36px;
            border-top-right-radius: 36px;
            padding: 32px min(5vw, 64px) 64px;
            min-height: calc(100vh - 120px);
        }
        form {
            max-width: 640px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px 24px;
        }
        label {
            font-weight: 600;
            display: block;
            margin-bottom: 6px;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        input, select {
            width: 100%;
            padding: 11px 12px;
            border-radius: 12px;
            border: 1px solid rgba(15,23,42,0.12);
            font-size: 0.95rem;
        }
        .hint {
            font-size: 0.8rem;
            color: #64748b;
            line-height: 1.3;
            opacity: 0;
            max-height: 0;
            transition: opacity .2s ease;
        }
        .field:focus-within .hint {
            opacity: 1;
            max-height: 200px;
        }
        .password-row {
            grid-column: 1 / -1;
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
        }
        .password-row > div {
            flex: 1 1 220px;
        }
        .password-wrapper {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: #475569;
        }
        button {
            grid-column: 1 / -1;
            padding: 14px;
            border-radius: 999px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            background: linear-gradient(120deg, #ff4d4f, #f97316);
            color: #fff;
            cursor: pointer;
        }
        a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
    <header>
        <h1>Tạo tài khoản AutoLux</h1>
        <p>Dữ liệu đăng ký sẽ ghi vào bảng `khach_hang` trong cơ sở dữ liệu.</p>
    </header>
    <main>
        <p style="text-align:center;max-width:600px;margin:0 auto 28px;">
            Trang này chỉ dành cho khách hàng tạo tài khoản, dữ liệu sẽ map với bảng `khach_hang`.
            Nhân viên muốn tạo tài khoản cần được admin mở trong khu vực quản trị.
        </p>
        <?php if (!empty($errors)): ?>
            <ul style="max-width:640px;margin:0 auto 16px;padding:12px 18px;border-radius:14px;background:rgba(255,77,79,0.15);border:1px solid rgba(255,77,79,0.4);color:#b91c1c;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form action="register.php" method="post" novalidate>
            <div class="field">
                <label for="fullname">Họ tên</label>
                <input id="fullname" name="fullname" type="text" value="<?= htmlspecialchars($old['fullname']) ?>" required>
                <small class="hint">Nhập họ và tên đầy đủ, ví dụ: Trần Minh Đức.</small>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= htmlspecialchars($old['email']) ?>" required>
                <small class="hint">Email hợp lệ dạng ten@domain.com. Hệ thống không chấp nhận email trùng.</small>
            </div>
            <div class="field">
                <label for="phone">Số điện thoại</label>
                <input id="phone" name="phone" type="tel" pattern="0\d{9}" title="Bắt đầu bằng 0 và gồm 10 số" value="<?= htmlspecialchars($old['phone']) ?>" required>
                <small class="hint">Số phải bắt đầu bằng 0 và gồm đúng 10 chữ số.</small>
            </div>
            <div class="field">
                <label for="address">Địa chỉ (không bắt buộc)</label>
                <input id="address" name="address" type="text" value="<?= htmlspecialchars($old['address']) ?>">
                <small class="hint">Có thể bỏ trống hoặc nhập địa chỉ giao dịch để tiện tư vấn.</small>
            </div>
            <div class="password-row">
                <div class="field">
                    <label for="password">Mật khẩu</label>
                    <div class="password-wrapper">
                        <input id="password" name="password" type="password" required>
                        <button type="button" class="toggle-password" data-target="password">👁</button>
                    </div>
                    <small class="hint">Tối thiểu 6 ký tự, chứa ít nhất 1 chữ in hoa và 1 chữ số.</small>
                </div>
                <div class="field">
                    <label for="confirm_password">Nhập lại mật khẩu</label>
                    <div class="password-wrapper">
                        <input id="confirm_password" name="confirm_password" type="password" required>
                        <button type="button" class="toggle-password" data-target="confirm_password">👁</button>
                    </div>
                    <small class="hint">Nhập giống mật khẩu bên cạnh để xác nhận.</small>
                </div>
            </div>
            <button type="submit">Đăng ký ngay</button>
        </form>
        <p style="text-align:center;margin-top:18px;">Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
        <p style="text-align:center;"><a href="index.php">← Về trang chủ</a></p>
    </main>
    <script>
document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        btn.textContent = type === 'password' ? '👁' : '🙈';
    });
});
    </script>
</body>
</html>

