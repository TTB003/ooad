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

// Định nghĩa các chức vụ
$jobPositions = [
    'Nhân viên bán hàng',
    'Nhân viên kho',
    'Nhân viên bảo hành/bảo dưỡng',
    'Nhân viên chăm sóc khách hàng'
];

// Chỉ lấy role NhanVien cho việc tạo nhân viên (không cho tạo Admin)
$roles = [];
$roleResult = pg_query($conn, "SELECT ma_loaitk, ten_loai FROM loai_tai_khoan WHERE ten_loai = 'NhanVien' ORDER BY ten_loai");
if ($roleResult) {
    while ($row = pg_fetch_assoc($roleResult)) {
        $roles[] = $row;
    }
}

$message = '';
$errors = [];
$old = [
    'full_name' => '',
    'role_id' => $roles[0]['ma_loaitk'] ?? '',
    'chuc_vu' => '',
    'email' => '',
    'phone' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $roleId = (int)($_POST['role_id'] ?? 0);
    $chucVu = trim($_POST['chuc_vu'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $old = [
        'full_name' => $fullName,
        'role_id' => $roleId ?: '',
        'chuc_vu' => $chucVu,
        'email' => $email,
        'phone' => $phone,
    ];

    if ($fullName === '' || $email === '' || $phone === '' || $password === '' || !$roleId || $chucVu === '') {
        $errors[] = 'Vui lòng nhập đầy đủ thông tin bắt buộc.';
    }

    // Kiểm tra email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    } else {
        // Kiểm tra email đã tồn tại chưa
        $emailCheck = pg_query_params($conn, 'SELECT ma_nhanvien FROM nhan_vien WHERE LOWER(email) = LOWER($1)', [$email]);
        if ($emailCheck && pg_num_rows($emailCheck) > 0) {
            $errors[] = 'Email này đã được sử dụng. Vui lòng chọn email khác.';
        }
    }

    // Kiểm tra số điện thoại
    if ($phone !== '') {
        // Kiểm tra số điện thoại phải đúng 10 số và bắt đầu từ 0
        if (!preg_match('/^0\d{9}$/', $phone)) {
            $errors[] = 'Số điện thoại phải có đúng 10 số và bắt đầu từ số 0.';
        } else {
            // Kiểm tra số điện thoại đã tồn tại chưa
            $phoneCheck = pg_query_params($conn, 'SELECT ma_nhanvien FROM nhan_vien WHERE sdt = $1', [$phone]);
            if ($phoneCheck && pg_num_rows($phoneCheck) > 0) {
                $errors[] = 'Số điện thoại này đã được sử dụng. Vui lòng chọn số điện thoại khác.';
            }
        }
    }

    // Kiểm tra không cho phép tạo tài khoản Admin
    $roleCheck = pg_query_params($conn, 'SELECT ten_loai FROM loai_tai_khoan WHERE ma_loaitk = $1', [$roleId]);
    if ($roleCheck && $roleRow = pg_fetch_assoc($roleCheck)) {
        if ($roleRow['ten_loai'] === 'Admin') {
            $errors[] = 'Không được phép tạo tài khoản Admin. Chỉ có thể tạo tài khoản nhân viên.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $sql = 'INSERT INTO nhan_vien (ho_ten, chuc_vu, email, sdt, ma_donhang, ma_loaitk, mat_khau)
                VALUES ($1,$2,$3,$4,NULL,$5,$6)
                RETURNING ma_nhanvien';
        $result = pg_query_params($conn, $sql, [$fullName, $chucVu, $email, $phone, $roleId, $hash]);

        if ($result === false) {
            $dbError = pg_last_error($conn);
            // Kiểm tra lỗi unique constraint
            if (strpos($dbError, 'unique') !== false || strpos($dbError, 'duplicate') !== false) {
                if (strpos($dbError, 'email') !== false) {
                    $errors[] = 'Email này đã được sử dụng. Vui lòng chọn email khác.';
                } elseif (strpos($dbError, 'sdt') !== false) {
                    $errors[] = 'Số điện thoại này đã được sử dụng. Vui lòng chọn số điện thoại khác.';
                } else {
                    $errors[] = 'Thông tin đã tồn tại trong hệ thống. Vui lòng kiểm tra lại.';
                }
            } else {
                $errors[] = 'Không thể tạo nhân viên: ' . $dbError;
            }
        } else {
            $row = pg_fetch_assoc($result);
            $message = 'Tạo nhân viên mới thành công.';
            $old = [
                'full_name' => '',
                'role_id' => $roles[0]['ma_loaitk'] ?? '',
                'chuc_vu' => '',
                'email' => '',
                'phone' => ''
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tạo nhân viên | AutoLux</title>
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
            max-width: 900px;
            margin: 0 auto;
        }
        form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px 24px;
            background: #fff;
            padding: 32px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
        }
        label { font-weight: 600; display: block; margin-bottom: 6px; }
        input, select {
            width: 100%;
            padding: 11px 12px;
            border-radius: 12px;
            border: 1px solid rgba(15,23,42,0.15);
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        button {
            grid-column: 1 / -1;
            padding: 12px;
            border-radius: 999px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            color: #fff;
            background: linear-gradient(120deg, #06b6d4, #3b82f6);
        }
        button:hover {
            opacity: 0.9;
        }
        a { color: #38bdf8; text-decoration: none; }
        .alert {
            margin-bottom: 18px;
            padding: 12px 18px;
            border-radius: 14px;
        }
        .alert-success {
            background: rgba(34,197,94,0.18);
            border: 1px solid rgba(34,197,94,0.3);
            color: #166534;
        }
        .alert-error {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.3);
            color: #991b1b;
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
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    <main>
        <div class="container">
            <a href="admin.php" class="btn-back">← Quay lại trang quản trị</a>
            
            <h2>Tạo tài khoản nhân viên</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <ul style="margin:0;padding-left:18px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="create_employee.php" method="post" novalidate>
                <div>
                    <label for="full_name">Họ tên nhân viên</label>
                    <input id="full_name" name="full_name" type="text" value="<?= htmlspecialchars($old['full_name']) ?>" required>
                </div>
                <div>
                    <label for="role_id">Quyền hệ thống</label>
                    <select id="role_id" name="role_id">
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars($role['ma_loaitk']) ?>" <?= (string)$old['role_id'] === (string)$role['ma_loaitk'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['ten_loai']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="chuc_vu">Chức vụ</label>
                    <select id="chuc_vu" name="chuc_vu" required>
                        <option value="">-- Chọn chức vụ --</option>
                        <?php foreach ($jobPositions as $pos): ?>
                            <option value="<?= htmlspecialchars($pos) ?>" <?= $old['chuc_vu'] === $pos ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pos) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="email">Email công việc</label>
                    <input id="email" name="email" type="email" value="<?= htmlspecialchars($old['email']) ?>" required>
                </div>
                <div>
                    <label for="phone">Số điện thoại</label>
                    <input id="phone" name="phone" type="tel" value="<?= htmlspecialchars($old['phone']) ?>" placeholder="0123456789" required>
                    <small style="color: #64748b; font-size: 0.85rem;">Phải có 10 số và bắt đầu từ 0</small>
                </div>
                <div>
                    <label for="password">Mật khẩu tạm</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <button type="submit">Tạo tài khoản nhân viên</button>
            </form>
        </div>
    </main>
</body>
</html>

