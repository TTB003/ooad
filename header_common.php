<?php
if (!isset($_SESSION)) {
    session_start();
}
if (!function_exists('db')) {
    require_once __DIR__ . '/config.php';
}

// Lấy thông tin người dùng
$loggedInUser = null;
if (!empty($_SESSION['customer_id'])) {
    if (!isset($conn)) {
        $conn = db();
    }
    $result = pg_query_params(
        $conn,
        'SELECT ma_khachhang, ho_ten, email, sdt, diachi FROM khach_hang WHERE ma_khachhang = $1 LIMIT 1',
        [$_SESSION['customer_id']]
    );
    if ($result) {
        $loggedInUser = pg_fetch_assoc($result);
    }
}

// Tạo bảng thông báo nếu chưa có
if (!isset($conn)) {
    $conn = db();
}
pg_query($conn, "CREATE TABLE IF NOT EXISTS thong_bao (
    ma_thongbao SERIAL PRIMARY KEY,
    tieu_de VARCHAR(255) NOT NULL,
    noi_dung TEXT,
    loai_thongbao VARCHAR(50),
    ma_khachhang INT,
    ma_nhanvien INT,
    ma_donhang INT,
    da_doc BOOLEAN DEFAULT FALSE,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
pg_query($conn, "CREATE INDEX IF NOT EXISTS idx_thong_bao_khachhang ON thong_bao(ma_khachhang, da_doc)");
pg_query($conn, "CREATE INDEX IF NOT EXISTS idx_thong_bao_nhanvien ON thong_bao(ma_nhanvien, da_doc)");

// Lấy danh sách hãng xe cho dropdown menu
$brands = [];
$brandResult = pg_query($conn, "SELECT DISTINCT hx.ma_hangxe, hx.ten_hangxe, hx.logo_url,
                                       (SELECT COUNT(*) FROM xe WHERE hang_xe = hx.ten_hangxe AND (tinh_trang != 'Dang_ban' OR tinh_trang IS NULL)) as so_xe
                                FROM hang_xe hx
                                INNER JOIN xe x ON x.hang_xe = hx.ten_hangxe
                                WHERE x.tinh_trang != 'Dang_ban' OR x.tinh_trang IS NULL
                                ORDER BY hx.ten_hangxe");
if ($brandResult) {
    while ($row = pg_fetch_assoc($brandResult)) {
        $brands[] = $row;
    }
}
?>
<style>
    :root {
        --primary: #ff4d4f;
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
        flex-wrap: wrap;
        gap: 12px;
    }
    header .logo {
        color: #fff;
        font-weight: 700;
        letter-spacing: 0.6px;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    header nav {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        flex: 1;
        justify-content: flex-end;
    }
    header nav a {
        color: rgba(255,255,255,0.85);
        text-decoration: none;
        font-size: 0.95rem;
        white-space: nowrap;
    }
    header nav a:hover { color: #fff; }
    header nav a.cta {
        padding: 8px 18px;
        border-radius: 999px;
        background: var(--primary);
        color: #fff;
        font-weight: 600;
        white-space: nowrap;
    }
    header nav form {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
        position: relative;
    }
    header nav form input {
        padding: 8px 40px 8px 14px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,0.3);
        background: rgba(255,255,255,0.1);
        color: #fff;
        font-size: 0.9rem;
        width: 180px;
        min-width: 150px;
    }
    header nav form input::placeholder {
        color: rgba(255,255,255,0.6);
    }
    header nav form button {
        position: absolute;
        right: 4px;
        padding: 6px 10px;
        border-radius: 999px;
        border: none;
        background: transparent;
        color: rgba(255,255,255,0.8);
        cursor: pointer;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    header nav form button:hover {
        color: #fff;
    }
    .dropdown {
        position: relative;
        display: inline-block;
    }
    .dropdown-menu {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        min-width: 200px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.2s ease;
        z-index: 1000;
        padding: 8px 0;
    }
    .dropdown:hover .dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    .dropdown-item {
        display: block;
        padding: 10px 18px;
        color: #0f172a;
        text-decoration: none;
        font-size: 0.9rem;
        position: relative;
        cursor: pointer;
    }
    .dropdown-item:hover {
        background: #f8fafc;
    }
    .dropdown-item.has-submenu:hover .submenu {
        opacity: 1;
        visibility: visible;
        transform: translateX(0);
    }
    .submenu {
        position: absolute;
        left: 100%;
        top: 0;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        min-width: 300px;
        max-width: 400px;
        max-height: 500px;
        overflow-y: auto;
        opacity: 0;
        visibility: hidden;
        transform: translateX(-10px);
        transition: all 0.2s ease;
        padding: 12px;
        margin-left: 8px;
    }
    .submenu-item {
        display: block;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 8px;
        text-decoration: none;
        color: #0f172a;
        border: 1px solid rgba(15,23,42,0.1);
        transition: all 0.2s;
    }
    .submenu-item:hover {
        border-color: var(--primary);
        background: #fef2f2;
    }
    .submenu-item img {
        width: 60px;
        height: 45px;
        object-fit: cover;
        border-radius: 6px;
        float: left;
        margin-right: 12px;
    }
    .submenu-item-content {
        display: flex;
        flex-direction: column;
    }
    .submenu-item-title {
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 4px;
    }
    .submenu-item-price {
        font-size: 0.85rem;
        color: var(--primary);
        font-weight: 600;
    }
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
        white-space: nowrap;
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
    @media (max-width: 968px) {
        header {
            padding: 12px 3vw;
        }
        header nav {
            width: 100%;
            justify-content: space-between;
        }
        header nav form {
            order: 3;
            width: 100%;
            margin-top: 8px;
        }
        header nav form input {
            width: 100% !important;
        }
    }
    @media (max-width: 640px) {
        header {
            flex-direction: column;
            gap: 12px;
        }
        header nav {
            flex-direction: column;
            width: 100%;
        }
        header nav a {
            margin: 0;
        }
    }
    .profile-modal {
        position: fixed;
        inset: 0;
        background: rgba(2,8,23,0.65);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease;
        z-index: 2000;
    }
    .profile-modal.active {
        opacity: 1;
        visibility: visible;
    }
    .profile-card {
        background: #fff;
        border-radius: 24px;
        padding: 28px;
        width: min(420px, 100%);
        box-shadow: 0 30px 80px rgba(15,23,42,0.3);
        position: relative;
        max-height: 90vh;
        overflow-y: auto;
    }
    .profile-card h3 {
        margin-top: 0;
        margin-bottom: 16px;
    }
    .profile-card form {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .profile-card label {
        font-weight: 600;
        font-size: 0.9rem;
    }
    .profile-card input {
        border-radius: 12px;
        border: 1px solid rgba(15,23,42,0.15);
        padding: 10px 12px;
    }
    .profile-actions {
        margin-top: 10px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .profile-actions a,
    .profile-actions button {
        text-align: center;
        border-radius: 999px;
        padding: 10px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: block;
    }
    .btn-save {
        background: var(--primary);
        color: #fff;
    }
    .btn-logout {
        background: #1f2937;
        color: #fff;
    }
    .btn-password {
        background: #3b82f6;
        color: #fff;
    }
    .modal-close {
        position: absolute;
        top: 12px;
        right: 12px;
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
    }
    .notification-item {
        padding: 12px 18px;
        border-bottom: 1px solid rgba(15,23,42,0.06);
        cursor: pointer;
        transition: background 0.2s;
    }
    .notification-item:hover {
        background: #f8fafc;
    }
    .notification-item.unread {
        background: #f0f9ff;
        font-weight: 600;
    }
    .notification-item .notification-title {
        font-weight: 600;
        margin-bottom: 4px;
        font-size: 0.9rem;
    }
    .notification-item .notification-content {
        font-size: 0.85rem;
        color: #64748b;
        margin-bottom: 4px;
    }
    .notification-item .notification-time {
        font-size: 0.75rem;
        color: #94a3b8;
    }
</style>
<header>
    <div class="logo">AutoLux Dealer</div>
    <nav>
        <a href="index.php">Trang chủ</a>
        <div class="dropdown">
            <a href="catalog.php">Danh mục xe</a>
            <div class="dropdown-menu">
                <?php foreach ($brands as $brand): ?>
                    <div class="dropdown-item has-submenu" data-brand="<?= htmlspecialchars($brand['ten_hangxe']) ?>">
                        <?= htmlspecialchars($brand['ten_hangxe']) ?> (<?= htmlspecialchars($brand['so_xe']) ?>)
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="index.php#services">Dịch vụ hậu mãi</a>
        <a href="index.php#contact">Liên hệ</a>
        
        <!-- Tìm kiếm -->
        <form method="get" action="search.php">
            <input type="text" name="q" placeholder="Tìm kiếm xe..." 
                   value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            <button type="submit">🔍</button>
        </form>
        
        <?php 
        $isStaff = isset($_SESSION['staff_id']);
        $isStaffAdmin = $isStaff && ($_SESSION['staff_role'] ?? '') === 'Admin';
        if ($isStaff): 
            // Đếm số thông báo chưa đọc cho nhân viên/admin
            $staffUnreadCount = 0;
            if (!isset($conn)) {
                $conn = db();
            }
            if ($isStaffAdmin) {
                $notifResult = pg_query($conn,
                    "SELECT COUNT(*) as count FROM thong_bao WHERE (ma_nhanvien IS NULL OR ma_nhanvien = " . (int)$_SESSION['staff_id'] . ") AND da_doc = FALSE"
                );
            } else {
                $notifResult = pg_query_params($conn,
                    'SELECT COUNT(*) as count FROM thong_bao WHERE ma_nhanvien = $1 AND da_doc = FALSE',
                    [$_SESSION['staff_id']]
                );
            }
            if ($notifResult && $row = pg_fetch_assoc($notifResult)) {
                $staffUnreadCount = (int)$row['count'];
            }
        ?>
            <a href="admin.php" style="color: #fff; text-decoration: none; font-size: 0.95rem; font-weight: 600; white-space: nowrap;">⚙️ Quản lý</a>
            <div style="position: relative; flex-shrink: 0; display: flex; align-items: center; gap: 12px;">
                <div style="position: relative;">
                    <button id="staffNotificationBell" class="user-chip" type="button" style="background: rgba(255,255,255,0.18); padding: 8px 12px;">
                        🔔
                        <?php if ($staffUnreadCount > 0): ?>
                            <span id="staffNotificationBadge" style="position: absolute; top: -4px; right: -4px; background: var(--primary); color: #fff; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700;"><?= $staffUnreadCount > 9 ? '9+' : $staffUnreadCount ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="staffNotificationMenu" class="user-menu" style="min-width: 350px; max-width: 400px; max-height: 500px; overflow-y: auto; right: 0;">
                        <div style="padding: 12px 18px; border-bottom: 1px solid rgba(15,23,42,0.06); font-weight: 600;">Thông báo</div>
                        <div id="staffNotificationList" style="max-height: 400px; overflow-y: auto;">
                            <p style="padding: 20px; text-align: center; color: #64748b;">Đang tải...</p>
                        </div>
                    </div>
                </div>
                <div style="position: relative;">
                    <button id="staffChip" class="user-chip" type="button">👤 <?= htmlspecialchars($_SESSION['staff_name'] ?? 'Nhân viên') ?></button>
                    <div id="staffMenu" class="user-menu">
                        <?php if (!$isStaffAdmin): ?>
                            <a href="change_password.php">🔐 Đổi mật khẩu</a>
                        <?php endif; ?>
                        <a href="logout.php">🚪 Đăng xuất</a>
                    </div>
                </div>
            </div>
        <?php elseif ($loggedInUser): ?>
            <?php
            // Đếm số thông báo chưa đọc cho khách hàng
            $unreadCount = 0;
            if (!isset($conn)) {
                $conn = db();
            }
            $notifResult = pg_query_params($conn,
                'SELECT COUNT(*) as count FROM thong_bao WHERE ma_khachhang = $1 AND da_doc = FALSE',
                [$_SESSION['customer_id']]
            );
            if ($notifResult && $row = pg_fetch_assoc($notifResult)) {
                $unreadCount = (int)$row['count'];
            }
            ?>
            <div style="position: relative; flex-shrink: 0; display: flex; align-items: center; gap: 12px;">
                <div style="position: relative;">
                    <button id="notificationBell" class="user-chip" type="button" style="background: rgba(255,255,255,0.18); padding: 8px 12px;">
                        🔔
                        <?php if ($unreadCount > 0): ?>
                            <span id="notificationBadge" style="position: absolute; top: -4px; right: -4px; background: var(--primary); color: #fff; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700;"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="notificationMenu" class="user-menu" style="min-width: 350px; max-width: 400px; max-height: 500px; overflow-y: auto; right: 0;">
                        <div style="padding: 12px 18px; border-bottom: 1px solid rgba(15,23,42,0.06); font-weight: 600;">Thông báo</div>
                        <div id="notificationList" style="max-height: 400px; overflow-y: auto;">
                            <p style="padding: 20px; text-align: center; color: #64748b;">Đang tải...</p>
                        </div>
                    </div>
                </div>
                <div style="position: relative;">
                    <button id="userChip" class="user-chip" type="button">👤 <?= htmlspecialchars($loggedInUser['ho_ten']) ?></button>
                    <div id="userMenu" class="user-menu">
                        <a href="#" onclick="event.preventDefault(); openProfileModal(); return false;">Thông tin khách hàng</a>
                        <a href="change_password.php">🔐 Đổi mật khẩu</a>
                        <a href="logout.php">🚪 Đăng xuất</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <a href="register.php" style="color: #fff; text-decoration: none; font-size: 0.95rem; font-weight: 600; white-space: nowrap;">Đăng ký</a>
            <a class="cta" href="login.php" style="white-space: nowrap;">Đăng nhập</a>
        <?php endif; ?>
    </nav>
</header>

<?php if ($loggedInUser): ?>
    <div id="profileModal" class="profile-modal">
        <div class="profile-card">
            <button class="modal-close" type="button" aria-label="Đóng" onclick="closeProfileModal()">✕</button>
            <h3>Thông tin cá nhân</h3>
            <form action="profile_update.php" method="post">
                <label for="profile_name">Họ tên</label>
                <input id="profile_name" name="fullname" type="text" value="<?= htmlspecialchars($loggedInUser['ho_ten']) ?>" required>

                <label for="profile_email">Email</label>
                <input id="profile_email" name="email" type="email" value="<?= htmlspecialchars($loggedInUser['email']) ?>" required>

                <label for="profile_phone">Số điện thoại</label>
                <input id="profile_phone" name="phone" type="tel" pattern="0\d{9}" value="<?= htmlspecialchars($loggedInUser['sdt']) ?>" required>

                <label for="profile_address">Địa chỉ</label>
                <input id="profile_address" name="address" type="text" value="<?= htmlspecialchars($loggedInUser['diachi'] ?? '') ?>">

                <div class="profile-actions">
                    <button class="btn-save" type="submit">Lưu thay đổi</button>
                    <a class="btn-logout" href="logout.php">Đăng xuất</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    // Functions for profile modal (must be global for onclick)
    function openProfileModal() {
        const modal = document.getElementById('profileModal');
        if (modal) {
            modal.classList.add('active');
        }
    }
    
    function closeProfileModal() {
        const modal = document.getElementById('profileModal');
        if (modal) {
            modal.classList.remove('active');
        }
    }
    
    // Load danh sách xe theo hãng khi hover
    document.addEventListener('DOMContentLoaded', function() {
        const brandItems = document.querySelectorAll('.dropdown-item.has-submenu');
        
        brandItems.forEach(item => {
            const brandName = item.getAttribute('data-brand');
            let submenu = item.querySelector('.submenu');
            
            if (!submenu) {
                submenu = document.createElement('div');
                submenu.className = 'submenu';
                item.appendChild(submenu);
            }
            
            item.addEventListener('mouseenter', function() {
                if (submenu.innerHTML === '') {
                    submenu.innerHTML = '<p style="padding: 12px; color: #64748b;">Đang tải...</p>';
                    
                    fetch('get_cars_by_brand.php?brand=' + encodeURIComponent(brandName))
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.cars.length > 0) {
                                submenu.innerHTML = '';
                                data.cars.forEach(car => {
                                    const carItem = document.createElement('a');
                                    carItem.href = 'car_detail.php?id=' + car.ma_xe;
                                    carItem.className = 'submenu-item';
                                    carItem.innerHTML = `
                                        <img src="${car.hinh_anh || 'https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg'}" 
                                             alt="${car.loai_xe}" 
                                             onerror="this.src='https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg'">
                                        <div class="submenu-item-content">
                                            <div class="submenu-item-title">${car.hang_xe} ${car.loai_xe}</div>
                                            <div class="submenu-item-price">${new Intl.NumberFormat('vi-VN').format(car.gia_ban)} ₫</div>
                                        </div>
                                        <div style="clear: both;"></div>
                                    `;
                                    submenu.appendChild(carItem);
                                });
                            } else {
                                submenu.innerHTML = '<p style="padding: 12px; color: #64748b;">Chưa có xe nào</p>';
                            }
                        })
                        .catch(error => {
                            submenu.innerHTML = '<p style="padding: 12px; color: #991b1b;">Lỗi tải dữ liệu</p>';
                        });
                }
            });
        });
        
        // Staff menu toggle
        const staffChip = document.getElementById('staffChip');
        const staffMenu = document.getElementById('staffMenu');
        if (staffChip && staffMenu) {
            staffChip.addEventListener('click', function(e) {
                e.stopPropagation();
                staffMenu.classList.toggle('active');
            });
            document.addEventListener('click', function(e) {
                if (!staffChip.contains(e.target) && !staffMenu.contains(e.target)) {
                    staffMenu.classList.remove('active');
                }
            });
        }
        
        // Staff notification bell toggle
        const staffNotificationBell = document.getElementById('staffNotificationBell');
        const staffNotificationMenu = document.getElementById('staffNotificationMenu');
        if (staffNotificationBell && staffNotificationMenu) {
            staffNotificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                staffNotificationMenu.classList.toggle('active');
                loadStaffNotifications();
            });
            document.addEventListener('click', function(e) {
                if (!staffNotificationBell.contains(e.target) && !staffNotificationMenu.contains(e.target)) {
                    staffNotificationMenu.classList.remove('active');
                }
            });
        }
        
        function loadStaffNotifications() {
            const notificationList = document.getElementById('staffNotificationList');
            if (!notificationList) return;
            
            fetch('get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.notifications.length > 0) {
                        notificationList.innerHTML = '';
                        data.notifications.forEach(notif => {
                            const item = document.createElement('div');
                            item.className = 'notification-item' + (notif.da_doc ? '' : ' unread');
                            item.innerHTML = `
                                <div class="notification-title">${notif.tieu_de}</div>
                                <div class="notification-content">${notif.noi_dung || ''}</div>
                                <div class="notification-time">${notif.ngay_tao}</div>
                            `;
                            item.addEventListener('click', function() {
                                markAsRead(notif.ma_thongbao);
                                if (notif.ma_donhang) {
                                    window.location.href = 'manage_orders.php?order_id=' + notif.ma_donhang;
                                }
                            });
                            notificationList.appendChild(item);
                        });
                    } else {
                        notificationList.innerHTML = '<p style="padding: 20px; text-align: center; color: #64748b;">Không có thông báo nào</p>';
                    }
                })
                .catch(error => {
                    notificationList.innerHTML = '<p style="padding: 20px; text-align: center; color: #991b1b;">Lỗi tải thông báo</p>';
                });
        }
        
        function updateStaffNotificationBadge() {
            fetch('get_notifications.php?count_only=1')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('staffNotificationBadge');
                    if (data.count > 0) {
                        if (!badge) {
                            const bell = document.getElementById('staffNotificationBell');
                            if (bell) {
                                const newBadge = document.createElement('span');
                                newBadge.id = 'staffNotificationBadge';
                                newBadge.style.cssText = 'position: absolute; top: -4px; right: -4px; background: var(--primary); color: #fff; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700;';
                                newBadge.textContent = data.count > 9 ? '9+' : data.count;
                                bell.appendChild(newBadge);
                            }
                        } else {
                            badge.textContent = data.count > 9 ? '9+' : data.count;
                        }
                    } else {
                        if (badge) badge.remove();
                    }
                });
        }
        
        // Auto refresh staff notifications every 30 seconds
        <?php if ($isStaff): ?>
        setInterval(function() {
            updateStaffNotificationBadge();
        }, 30000);
        <?php endif; ?>
        
        // User menu toggle
        const userChip = document.getElementById('userChip');
        const userMenu = document.getElementById('userMenu');
        if (userChip && userMenu) {
            userChip.addEventListener('click', function(e) {
                e.stopPropagation();
                userMenu.classList.toggle('active');
            });
            document.addEventListener('click', function(e) {
                if (!userChip.contains(e.target) && !userMenu.contains(e.target)) {
                    userMenu.classList.remove('active');
                }
            });
        }
        
        // Customer notification bell toggle
        const notificationBell = document.getElementById('notificationBell');
        const notificationMenu = document.getElementById('notificationMenu');
        if (notificationBell && notificationMenu) {
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationMenu.classList.toggle('active');
                loadCustomerNotifications();
            });
            document.addEventListener('click', function(e) {
                if (!notificationBell.contains(e.target) && !notificationMenu.contains(e.target)) {
                    notificationMenu.classList.remove('active');
                }
            });
        }
        
        function loadCustomerNotifications() {
            const notificationList = document.getElementById('notificationList');
            if (!notificationList) return;
            
            fetch('get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.notifications.length > 0) {
                        notificationList.innerHTML = '';
                        data.notifications.forEach(notif => {
                            const item = document.createElement('div');
                            item.className = 'notification-item' + (notif.da_doc ? '' : ' unread');
                            item.innerHTML = `
                                <div class="notification-title">${notif.tieu_de}</div>
                                <div class="notification-content">${notif.noi_dung || ''}</div>
                                <div class="notification-time">${notif.ngay_tao}</div>
                            `;
                            item.addEventListener('click', function() {
                                markAsRead(notif.ma_thongbao);
                                if (notif.ma_donhang) {
                                    window.location.href = 'payment.php?order_id=' + notif.ma_donhang;
                                }
                            });
                            notificationList.appendChild(item);
                        });
                    } else {
                        notificationList.innerHTML = '<p style="padding: 20px; text-align: center; color: #64748b;">Không có thông báo nào</p>';
                    }
                })
                .catch(error => {
                    notificationList.innerHTML = '<p style="padding: 20px; text-align: center; color: #991b1b;">Lỗi tải thông báo</p>';
                });
        }
        
        function markAsRead(maThongBao) {
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ma_thongbao=' + maThongBao
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateNotificationBadge();
                        loadCustomerNotifications();
                    }
                });
        }
        
        function updateNotificationBadge() {
            fetch('get_notifications.php?count_only=1')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('notificationBadge');
                    if (data.count > 0) {
                        if (!badge) {
                            const bell = document.getElementById('notificationBell');
                            if (bell) {
                                const newBadge = document.createElement('span');
                                newBadge.id = 'notificationBadge';
                                newBadge.style.cssText = 'position: absolute; top: -4px; right: -4px; background: var(--primary); color: #fff; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700;';
                                newBadge.textContent = data.count > 9 ? '9+' : data.count;
                                bell.appendChild(newBadge);
                            }
                        } else {
                            badge.textContent = data.count > 9 ? '9+' : data.count;
                        }
                    } else {
                        if (badge) badge.remove();
                    }
                });
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProfileModal();
            }
        });
        
        // Close modal when clicking outside
        const profileModal = document.getElementById('profileModal');
        if (profileModal) {
            profileModal.addEventListener('click', function(e) {
                if (e.target === profileModal) {
                    closeProfileModal();
                }
            });
        }
        
        // Auto refresh customer notifications every 30 seconds
        <?php if ($loggedInUser): ?>
        setInterval(function() {
            updateNotificationBadge();
        }, 30000);
        <?php endif; ?>
    });
</script>
