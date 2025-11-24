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

$message = '';
$errors = [];

// Xử lý các action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $accountId = (int)($_POST['account_id'] ?? 0);
    
    if ($action === 'get_detail') {
        // Trả về JSON cho AJAX request
        header('Content-Type: application/json');
        $accountId = (int)($_POST['account_id'] ?? 0);
        $staffId = isset($_POST['staff_id']) && $_POST['staff_id'] !== 'null' ? (int)$_POST['staff_id'] : 0;
        $customerId = isset($_POST['customer_id']) && $_POST['customer_id'] !== 'null' ? (int)$_POST['customer_id'] : 0;
        
        $result = ['success' => false];
        
        // Lấy thông tin tài khoản
        $accResult = pg_query_params($conn,
            'SELECT tk.ma_taikhoan, tk.username, tk.trang_thai, lk.ten_loai FROM tai_khoan tk LEFT JOIN loai_tai_khoan lk ON tk.ma_loaitk = lk.ma_loaitk WHERE tk.ma_taikhoan = $1',
            [$accountId]
        );
        
        if ($accResult && $acc = pg_fetch_assoc($accResult)) {
            $result['account'] = $acc;
            $result['success'] = true;
            
            // Lấy thông tin người dùng
            if ($staffId) {
                $staffResult = pg_query_params($conn,
                    'SELECT ma_nhanvien, ho_ten, email, sdt, chuc_vu FROM nhan_vien WHERE ma_nhanvien = $1',
                    [$staffId]
                );
                if ($staffResult && $staff = pg_fetch_assoc($staffResult)) {
                    $result['user'] = array_merge($staff, ['type' => 'staff']);
                }
            } elseif ($customerId) {
                $customerResult = pg_query_params($conn,
                    'SELECT ma_khachhang, ho_ten, email, sdt, diachi FROM khach_hang WHERE ma_khachhang = $1',
                    [$customerId]
                );
                if ($customerResult && $customer = pg_fetch_assoc($customerResult)) {
                    $result['user'] = array_merge($customer, ['type' => 'customer']);
                    
                    // Lấy lịch sử đơn hàng
                    $ordersResult = pg_query_params($conn,
                        'SELECT ma_donhang, ngay_lap, tong_tien, trang_thai FROM don_hang WHERE ma_khachhang = $1 ORDER BY ngay_lap DESC LIMIT 20',
                        [$customerId]
                    );
                    $orders = [];
                    if ($ordersResult) {
                        while ($order = pg_fetch_assoc($ordersResult)) {
                            $orders[] = $order;
                        }
                    }
                    $result['orders'] = $orders;
                }
            }
        }
        
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'toggle_status' && $accountId) {
        // Khóa/mở khóa tài khoản
        $currentStatus = pg_fetch_assoc(pg_query_params($conn, 'SELECT trang_thai FROM tai_khoan WHERE ma_taikhoan = $1', [$accountId]));
        $newStatus = ($currentStatus['trang_thai'] ?? 'active') === 'active' ? 'disabled' : 'active';
        $result = pg_query_params($conn, 'UPDATE tai_khoan SET trang_thai = $1 WHERE ma_taikhoan = $2', [$newStatus, $accountId]);
        if ($result) {
            $message = $newStatus === 'disabled' ? 'Đã khóa tài khoản thành công.' : 'Đã mở khóa tài khoản thành công.';
        } else {
            $errors[] = 'Không thể cập nhật trạng thái tài khoản.';
        }
    } elseif ($action === 'delete' && $accountId) {
        // Xóa tài khoản
        $result = pg_query_params($conn, 'DELETE FROM tai_khoan WHERE ma_taikhoan = $1', [$accountId]);
        if ($result) {
            $message = 'Đã xóa tài khoản thành công.';
        } else {
            $errors[] = 'Không thể xóa tài khoản: ' . pg_last_error($conn);
        }
    } elseif ($action === 'edit' && $accountId) {
        // Sửa tài khoản
        $newUsername = trim($_POST['username'] ?? '');
        $newRoleId = (int)($_POST['role_id'] ?? 0);
        
        if ($newUsername === '') {
            $errors[] = 'Username không được để trống.';
        } else {
            $result = pg_query_params($conn, 
                'UPDATE tai_khoan SET username = $1, ma_loaitk = $2 WHERE ma_taikhoan = $3',
                [$newUsername, $newRoleId, $accountId]
            );
            if ($result) {
                $message = 'Đã cập nhật tài khoản thành công.';
            } else {
                $errors[] = 'Không thể cập nhật tài khoản: ' . pg_last_error($conn);
            }
        }
    } elseif ($action === 'create') {
        // Thêm tài khoản mới
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId = (int)($_POST['role_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $userType = $_POST['user_type'] ?? ''; // 'staff' or 'customer'
        
        if ($username === '' || $password === '' || !$roleId || !$userId || $userType === '') {
            $errors[] = 'Vui lòng nhập đầy đủ thông tin.';
        } else {
            // Kiểm tra username đã tồn tại chưa
            $checkUser = pg_query_params($conn, 'SELECT 1 FROM tai_khoan WHERE username = $1', [$username]);
            if ($checkUser && pg_num_rows($checkUser) > 0) {
                $errors[] = 'Username đã tồn tại.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                if ($userType === 'staff') {
                    $result = pg_query_params($conn,
                        'INSERT INTO tai_khoan (username, hash_matkhau, trang_thai, ma_loaitk, ma_nhanvien) VALUES ($1, $2, $3, $4, $5)',
                        [$username, $hash, 'active', $roleId, $userId]
                    );
                } else {
                    $result = pg_query_params($conn,
                        'INSERT INTO tai_khoan (username, hash_matkhau, trang_thai, ma_loaitk, ma_khachhang) VALUES ($1, $2, $3, $4, $5)',
                        [$username, $hash, 'active', $roleId, $userId]
                    );
                }
                
                if ($result) {
                    $message = 'Đã tạo tài khoản thành công.';
                } else {
                    $errors[] = 'Không thể tạo tài khoản: ' . pg_last_error($conn);
                }
            }
        }
    }
}

// Lấy danh sách tài khoản
$accounts = [];
$accResult = pg_query($conn, "SELECT tk.ma_taikhoan, tk.username, tk.trang_thai, lk.ten_loai, lk.ma_loaitk,
                                     COALESCE(nv.ho_ten, kh.ho_ten) as ho_ten,
                                     COALESCE(nv.email, kh.email) as email,
                                     tk.ma_nhanvien, tk.ma_khachhang
                              FROM tai_khoan tk
                              LEFT JOIN loai_tai_khoan lk ON tk.ma_loaitk = lk.ma_loaitk
                              LEFT JOIN nhan_vien nv ON tk.ma_nhanvien = nv.ma_nhanvien
                              LEFT JOIN khach_hang kh ON tk.ma_khachhang = kh.ma_khachhang
                              ORDER BY tk.ma_taikhoan DESC");
if ($accResult) {
    while ($row = pg_fetch_assoc($accResult)) {
        $accounts[] = $row;
    }
}

// Lấy danh sách roles
$roles = [];
$roleResult = pg_query($conn, 'SELECT ma_loaitk, ten_loai FROM loai_tai_khoan ORDER BY ten_loai');
if ($roleResult) {
    while ($row = pg_fetch_assoc($roleResult)) {
        $roles[] = $row;
    }
}

// Lấy danh sách nhân viên chưa có tài khoản
$staffWithoutAccount = [];
$staffResult = pg_query($conn, "SELECT nv.ma_nhanvien, nv.ho_ten, nv.email
                                 FROM nhan_vien nv
                                 LEFT JOIN tai_khoan tk ON nv.ma_nhanvien = tk.ma_nhanvien
                                 WHERE tk.ma_taikhoan IS NULL
                                 ORDER BY nv.ma_nhanvien DESC");
if ($staffResult) {
    while ($row = pg_fetch_assoc($staffResult)) {
        $staffWithoutAccount[] = $row;
    }
}

// Lấy danh sách khách hàng chưa có tài khoản
$customersWithoutAccount = [];
$customerResult = pg_query($conn, "SELECT kh.ma_khachhang, kh.ho_ten, kh.email
                                    FROM khach_hang kh
                                    LEFT JOIN tai_khoan tk ON kh.ma_khachhang = tk.ma_khachhang
                                    WHERE tk.ma_taikhoan IS NULL
                                    ORDER BY kh.ma_khachhang DESC");
if ($customerResult) {
    while ($row = pg_fetch_assoc($customerResult)) {
        $customersWithoutAccount[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý tài khoản & phân quyền | AutoLux</title>
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
        }
        header {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(2, 8, 23, 0.9);
            backdrop-filter: blur(14px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 5vw;
        }
        header .logo {
            color: #fff;
            font-weight: 700;
            letter-spacing: 0.6px;
            font-size: 1.2rem;
        }
        nav {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        nav a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 0.95rem;
        }
        nav a:hover { color: #fff; }
        .user-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.18);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid rgba(255,255,255,0.25);
            position: relative;
        }
        .user-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            min-width: 180px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            z-index: 100;
        }
        .user-menu.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .user-menu a {
            display: block;
            padding: 12px 18px;
            color: #0f172a;
            text-decoration: none;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(15,23,42,0.06);
        }
        .user-menu a:last-child {
            border-bottom: none;
        }
        .user-menu a:hover {
            background: #f8fafc;
        }
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
        .status-active {
            background: rgba(34,197,94,0.18);
            color: #15803d;
        }
        .status-disabled {
            background: rgba(239,68,68,0.18);
            color: #991b1b;
        }
        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            border: none;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        .btn-edit {
            background: rgba(59,130,246,0.18);
            color: #1e40af;
        }
        .btn-toggle {
            background: rgba(251,191,36,0.18);
            color: #92400e;
        }
        .btn-delete {
            background: rgba(239,68,68,0.18);
            color: #991b1b;
        }
        .btn-action:hover {
            opacity: 0.8;
        }
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
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: #fff;
            border-radius: 18px;
            padding: 24px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-content h3 {
            margin-top: 0;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(15,23,42,0.15);
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 999px;
            border: none;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(120deg, #06b6d4, #3b82f6);
            color: #fff;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #0f172a;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    
    <main>
        <div class="container">
            <a href="admin.php" class="btn-back">← Quay lại trang quản trị</a>
            
            <h2>Quản lý tài khoản & phân quyền</h2>
            
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
            
            <div style="margin-bottom: 24px;">
                <button class="btn-action btn-edit" onclick="openCreateModal()" style="background: rgba(34,197,94,0.18); color: #15803d;">+ Thêm tài khoản</button>
            </div>
            
            <?php if (empty($accounts)): ?>
                <p>Chưa có tài khoản nào trong hệ thống.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Mã TK</th>
                            <th>Username</th>
                            <th>Họ tên</th>
                            <th>Email</th>
                            <th>Loại tài khoản</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $account): ?>
                            <tr>
                                <td><?= htmlspecialchars($account['ma_taikhoan']) ?></td>
                                <td><?= htmlspecialchars($account['username']) ?></td>
                                <td><?= htmlspecialchars($account['ho_ten'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($account['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($account['ten_loai']) ?></td>
                                <td>
                                    <span class="status <?= $account['trang_thai'] === 'active' ? 'status-active' : 'status-disabled' ?>">
                                        <?= htmlspecialchars($account['trang_thai'] === 'active' ? 'Hoạt động' : 'Đã khóa') ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-action btn-edit" onclick="openDetailModal(<?= htmlspecialchars($account['ma_taikhoan']) ?>, <?= htmlspecialchars($account['ma_nhanvien'] ?? 'null') ?>, <?= htmlspecialchars($account['ma_khachhang'] ?? 'null') ?>)">Chi tiết</button>
                                    <button class="btn-action btn-edit" onclick="openEditModal(<?= htmlspecialchars($account['ma_taikhoan']) ?>, '<?= htmlspecialchars($account['username']) ?>', <?= htmlspecialchars($account['ma_loaitk']) ?>)">Sửa</button>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Bạn có chắc muốn <?= $account['trang_thai'] === 'active' ? 'khóa' : 'mở khóa' ?> tài khoản này?');">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="account_id" value="<?= htmlspecialchars($account['ma_taikhoan']) ?>">
                                        <button type="submit" class="btn-action btn-toggle"><?= $account['trang_thai'] === 'active' ? 'Khóa' : 'Mở khóa' ?></button>
                                    </form>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Bạn có chắc muốn XÓA tài khoản này? Dữ liệu sẽ bị mất vĩnh viễn!');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="account_id" value="<?= htmlspecialchars($account['ma_taikhoan']) ?>">
                                        <button type="submit" class="btn-action btn-delete">Xóa</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Modal thêm tài khoản -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <h3>Thêm tài khoản mới</h3>
            <form method="post" id="createForm">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label for="create_user_type">Loại người dùng</label>
                    <select name="user_type" id="create_user_type" required onchange="updateUserList()">
                        <option value="">-- Chọn loại --</option>
                        <option value="staff">Nhân viên</option>
                        <option value="customer">Khách hàng</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="create_user_id">Chọn người dùng</label>
                    <select name="user_id" id="create_user_id" required>
                        <option value="">-- Chọn người dùng --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="create_username">Username</label>
                    <input type="text" id="create_username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="create_password">Mật khẩu</label>
                    <input type="password" id="create_password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="create_role_id">Loại tài khoản</label>
                    <select id="create_role_id" name="role_id" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars($role['ma_loaitk']) ?>"><?= htmlspecialchars($role['ten_loai']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">Tạo</button>
                    <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Hủy</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal sửa tài khoản -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Sửa tài khoản</h3>
            <form method="post" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="account_id" id="edit_account_id">
                <div class="form-group">
                    <label for="edit_username">Username</label>
                    <input type="text" id="edit_username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="edit_role_id">Loại tài khoản</label>
                    <select id="edit_role_id" name="role_id" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars($role['ma_loaitk']) ?>"><?= htmlspecialchars($role['ten_loai']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">Lưu</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Hủy</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal chi tiết người dùng -->
    <div id="detailModal" class="modal">
        <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
            <h3>Chi tiết người dùng</h3>
            <div id="detailContent">
                <p>Đang tải...</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDetailModal()">Đóng</button>
            </div>
        </div>
    </div>
    
    <script>
        const chip = document.getElementById('userChip');
        const menu = document.getElementById('userMenu');
        
        if (chip && menu) {
            chip.addEventListener('click', function(e) {
                e.stopPropagation();
                menu.classList.toggle('active');
            });
            
            document.addEventListener('click', function(e) {
                if (!chip.contains(e.target) && !menu.contains(e.target)) {
                    menu.classList.remove('active');
                }
            });
        }
        
        function openEditModal(accountId, username, roleId) {
            document.getElementById('edit_account_id').value = accountId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_role_id').value = roleId;
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        function openDetailModal(accountId, staffId, customerId) {
            const modal = document.getElementById('detailModal');
            const content = document.getElementById('detailContent');
            content.innerHTML = '<p>Đang tải...</p>';
            modal.classList.add('active');
            
            // Gửi request để lấy thông tin chi tiết
            const formData = new FormData();
            formData.append('action', 'get_detail');
            formData.append('account_id', accountId);
            if (staffId) formData.append('staff_id', staffId);
            if (customerId) formData.append('customer_id', customerId);
            
            fetch('manage_accounts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '<div style="margin-bottom: 24px;">';
                    html += '<h4>Thông tin tài khoản</h4>';
                    html += '<p><strong>Username:</strong> ' + data.account.username + '</p>';
                    html += '<p><strong>Loại tài khoản:</strong> ' + data.account.ten_loai + '</p>';
                    html += '<p><strong>Trạng thái:</strong> ' + (data.account.trang_thai === 'active' ? 'Hoạt động' : 'Đã khóa') + '</p>';
                    html += '</div>';
                    
                    if (data.user) {
                        html += '<div style="margin-bottom: 24px;">';
                        html += '<h4>Thông tin ' + (data.user.type === 'staff' ? 'nhân viên' : 'khách hàng') + '</h4>';
                        html += '<p><strong>Họ tên:</strong> ' + data.user.ho_ten + '</p>';
                        html += '<p><strong>Email:</strong> ' + (data.user.email || 'N/A') + '</p>';
                        html += '<p><strong>Số điện thoại:</strong> ' + (data.user.sdt || 'N/A') + '</p>';
                        if (data.user.type === 'customer' && data.user.diachi) {
                            html += '<p><strong>Địa chỉ:</strong> ' + data.user.diachi + '</p>';
                        }
                        if (data.user.type === 'staff' && data.user.chuc_vu) {
                            html += '<p><strong>Chức vụ:</strong> ' + data.user.chuc_vu + '</p>';
                        }
                        html += '</div>';
                    }
                    
                    if (data.orders && data.orders.length > 0) {
                        html += '<div style="margin-bottom: 24px;">';
                        html += '<h4>Lịch sử mua hàng (' + data.orders.length + ' đơn)</h4>';
                        html += '<table style="width: 100%; border-collapse: collapse; margin-top: 12px;">';
                        html += '<thead><tr style="background: #f8fafc;"><th style="padding: 8px; text-align: left;">Mã đơn</th><th style="padding: 8px; text-align: left;">Ngày</th><th style="padding: 8px; text-align: left;">Tổng tiền</th><th style="padding: 8px; text-align: left;">Trạng thái</th></tr></thead>';
                        html += '<tbody>';
                        data.orders.forEach(order => {
                            html += '<tr><td style="padding: 8px;">#' + order.ma_donhang + '</td>';
                            html += '<td style="padding: 8px;">' + order.ngay_lap + '</td>';
                            html += '<td style="padding: 8px;">' + new Intl.NumberFormat('vi-VN').format(order.tong_tien) + ' ₫</td>';
                            html += '<td style="padding: 8px;">' + order.trang_thai + '</td></tr>';
                        });
                        html += '</tbody></table>';
                        html += '</div>';
                    } else if (data.user && data.user.type === 'customer') {
                        html += '<div style="margin-bottom: 24px;"><p>Chưa có đơn hàng nào.</p></div>';
                    }
                    
                    content.innerHTML = html;
                } else {
                    content.innerHTML = '<p style="color: #991b1b;">Không thể tải thông tin.</p>';
                }
            })
            .catch(error => {
                content.innerHTML = '<p style="color: #991b1b;">Có lỗi xảy ra: ' + error + '</p>';
            });
        }
        
        function closeDetailModal() {
            document.getElementById('detailModal').classList.remove('active');
        }
        
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetailModal();
            }
        });
        
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
        
        function openCreateModal() {
            document.getElementById('createModal').classList.add('active');
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('active');
            document.getElementById('createForm').reset();
            document.getElementById('create_user_id').innerHTML = '<option value="">-- Chọn người dùng --</option>';
        }
        
        document.getElementById('createModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateModal();
            }
        });
        
        const staffList = <?= json_encode($staffWithoutAccount) ?>;
        const customerList = <?= json_encode($customersWithoutAccount) ?>;
        
        function updateUserList() {
            const userType = document.getElementById('create_user_type').value;
            const userSelect = document.getElementById('create_user_id');
            userSelect.innerHTML = '<option value="">-- Chọn người dùng --</option>';
            
            if (userType === 'staff') {
                staffList.forEach(function(staff) {
                    const option = document.createElement('option');
                    option.value = staff.ma_nhanvien;
                    option.textContent = staff.ho_ten + ' (' + staff.email + ')';
                    userSelect.appendChild(option);
                });
            } else if (userType === 'customer') {
                customerList.forEach(function(customer) {
                    const option = document.createElement('option');
                    option.value = customer.ma_khachhang;
                    option.textContent = customer.ho_ten + ' (' + customer.email + ')';
                    userSelect.appendChild(option);
                });
            }
        }
    </script>
</body>
</html>
