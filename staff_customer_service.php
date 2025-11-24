<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$conn = db();
$currentPosition = $_SESSION['staff_position'] ?? '';

// Kiểm tra quyền truy cập
if ($currentPosition !== 'Nhân viên chăm sóc khách hàng') {
    header('Location: admin.php');
    exit;
}

$message = '';
$errors = [];
$activeTab = $_GET['tab'] ?? 'customers';

// Lấy danh sách khách hàng
$customers = [];
$customersResult = pg_query($conn, "SELECT kh.ma_khachhang, kh.ho_ten, kh.email, kh.sdt, kh.diachi, kh.hang_thanh_vien,
                                           COUNT(dh.ma_donhang) as so_don_hang,
                                           SUM(CASE WHEN dh.trang_thai = 'Da_thanh_toan' THEN dh.tong_tien ELSE 0 END) as tong_chi_tieu
                                    FROM khach_hang kh
                                    LEFT JOIN don_hang dh ON kh.ma_khachhang = dh.ma_khachhang
                                    GROUP BY kh.ma_khachhang
                                    ORDER BY kh.ma_khachhang DESC
                                    LIMIT 100");
if ($customersResult) {
    while ($row = pg_fetch_assoc($customersResult)) {
        $customers[] = $row;
    }
}

// Lấy danh sách đơn hàng để xem lịch sử
$orders = [];
$ordersResult = pg_query($conn, "SELECT dh.ma_donhang, dh.ngay_lap, dh.tong_tien, dh.trang_thai,
                                         kh.ho_ten as ten_khachhang, kh.email, kh.sdt
                                  FROM don_hang dh
                                  INNER JOIN khach_hang kh ON dh.ma_khachhang = kh.ma_khachhang
                                  ORDER BY dh.ngay_lap DESC
                                  LIMIT 50");
if ($ordersResult) {
    while ($row = pg_fetch_assoc($ordersResult)) {
        $orders[] = $row;
    }
}

// Tính báo cáo CSKH
$totalCustomers = count($customers);
$totalOrders = count($orders);
$satisfiedCustomers = 0; // Giả định, có thể tính từ feedback nếu có bảng feedback
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Nhân viên chăm sóc khách hàng | AutoLux</title>
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
        .tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            border-bottom: 2px solid rgba(15,23,42,0.1);
        }
        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            color: rgba(15,23,42,0.6);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }
        .tab.active {
            color: #0f172a;
            border-bottom-color: #ff4d4f;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
            margin-top: 24px;
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
        .status-Cho_duyet {
            background: rgba(251,191,36,0.18);
            color: #92400e;
        }
        .status-Da_duyet {
            background: rgba(59,130,246,0.18);
            color: #1e40af;
        }
        .status-Da_thanh_toan {
            background: rgba(34,197,94,0.18);
            color: #15803d;
        }
        .status-Huy {
            background: rgba(239,68,68,0.18);
            color: #991b1b;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #fff;
            padding: 24px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
        }
        .stat-card h3 {
            margin: 0 0 12px 0;
            font-size: 0.9rem;
            color: rgba(15,23,42,0.6);
            text-transform: uppercase;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
        }
        .info-card {
            background: #fff;
            padding: 20px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
            margin-bottom: 24px;
        }
        .info-card h4 {
            margin-top: 0;
            color: #0f172a;
        }
        .info-card p {
            margin: 8px 0;
            color: rgba(15,23,42,0.7);
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    
    <main>
        <div class="container">
            <a href="admin.php" class="btn-back">← Quay lại trang quản trị</a>
            
            <h2>Chức năng dành cho Nhân viên chăm sóc khách hàng</h2>
            
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
            
            <div class="tabs">
                <button class="tab <?= $activeTab === 'customers' ? 'active' : '' ?>" onclick="switchTab('customers')">👥 Quản lý khách hàng</button>
                <button class="tab <?= $activeTab === 'orders' ? 'active' : '' ?>" onclick="switchTab('orders')">📋 Lịch sử đơn hàng</button>
                <button class="tab <?= $activeTab === 'support' ? 'active' : '' ?>" onclick="switchTab('support')">📞 Hỗ trợ khách hàng</button>
                <button class="tab <?= $activeTab === 'reports' ? 'active' : '' ?>" onclick="switchTab('reports')">📊 Báo cáo CSKH</button>
            </div>
            
            <!-- Tab Quản lý khách hàng -->
            <div id="tab-customers" class="tab-content <?= $activeTab === 'customers' ? 'active' : '' ?>">
                <h3>Danh sách khách hàng</h3>
                <?php if (empty($customers)): ?>
                    <p>Chưa có khách hàng nào.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Mã KH</th>
                                <th>Họ tên</th>
                                <th>Email</th>
                                <th>SĐT</th>
                                <th>Địa chỉ</th>
                                <th>Hạng thành viên</th>
                                <th>Số đơn hàng</th>
                                <th>Tổng chi tiêu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($customer['ma_khachhang']) ?></td>
                                    <td><?= htmlspecialchars($customer['ho_ten']) ?></td>
                                    <td><?= htmlspecialchars($customer['email'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($customer['sdt'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($customer['diachi'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($customer['hang_thanh_vien'] ?? 'Bronze') ?></td>
                                    <td><?= htmlspecialchars($customer['so_don_hang'] ?? 0) ?></td>
                                    <td><?= number_format($customer['tong_chi_tieu'] ?? 0, 0, ',', '.') ?> đ</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Tab Lịch sử đơn hàng -->
            <div id="tab-orders" class="tab-content <?= $activeTab === 'orders' ? 'active' : '' ?>">
                <h3>Lịch sử đơn hàng của khách hàng</h3>
                <?php if (empty($orders)): ?>
                    <p>Chưa có đơn hàng nào.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Ngày lập</th>
                                <th>Khách hàng</th>
                                <th>Email</th>
                                <th>SĐT</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($order['ma_donhang']) ?></td>
                                    <td><?= htmlspecialchars($order['ngay_lap']) ?></td>
                                    <td><?= htmlspecialchars($order['ten_khachhang']) ?></td>
                                    <td><?= htmlspecialchars($order['email'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($order['sdt'] ?? 'N/A') ?></td>
                                    <td><?= number_format($order['tong_tien'] ?? 0, 0, ',', '.') ?> đ</td>
                                    <td>
                                        <span class="status status-<?= htmlspecialchars($order['trang_thai']) ?>">
                                            <?php
                                            $statusMap = [
                                                'Cho_duyet' => 'Chờ duyệt',
                                                'Da_duyet' => 'Đã duyệt',
                                                'Da_thanh_toan' => 'Đã thanh toán',
                                                'Huy' => 'Hủy'
                                            ];
                                            echo htmlspecialchars($statusMap[$order['trang_thai']] ?? $order['trang_thai']);
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Tab Hỗ trợ khách hàng -->
            <div id="tab-support" class="tab-content <?= $activeTab === 'support' ? 'active' : '' ?>">
                <h3>Hỗ trợ khách hàng</h3>
                <div class="info-card">
                    <h4>Thông tin liên hệ hỗ trợ</h4>
                    <p><strong>Hotline:</strong> 0000000000</p>
                    <p><strong>Email hỗ trợ:</strong> support@autolux.com</p>
                    <p><strong>Giờ làm việc:</strong> 8:00 - 17:30 (Thứ 2 - Thứ 6)</p>
                </div>
                <div class="info-card">
                    <h4>Hướng dẫn hỗ trợ khách hàng</h4>
                    <p>1. Xem thông tin khách hàng trong tab "Quản lý khách hàng"</p>
                    <p>2. Xem lịch sử đơn hàng trong tab "Lịch sử đơn hàng"</p>
                    <p>3. Liên hệ trực tiếp với khách hàng qua email hoặc số điện thoại</p>
                    <p>4. Ghi nhận các phản hồi và khiếu nại của khách hàng</p>
                    <p>5. Theo dõi và cập nhật tình trạng xử lý</p>
                </div>
            </div>
            
            <!-- Tab Báo cáo CSKH -->
            <div id="tab-reports" class="tab-content <?= $activeTab === 'reports' ? 'active' : '' ?>">
                <h3>Báo cáo chăm sóc khách hàng</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Tổng số khách hàng</h3>
                        <div class="value"><?= $totalCustomers ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Tổng số đơn hàng</h3>
                        <div class="value"><?= $totalOrders ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Khách hàng hài lòng</h3>
                        <div class="value"><?= $satisfiedCustomers ?></div>
                    </div>
                </div>
                
                <h4 style="margin-top: 32px;">Top khách hàng</h4>
                <?php if (!empty($customers)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Mã KH</th>
                                <th>Họ tên</th>
                                <th>Email</th>
                                <th>Số đơn hàng</th>
                                <th>Tổng chi tiêu</th>
                                <th>Hạng thành viên</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $topCustomers = array_slice($customers, 0, 10);
                            foreach ($topCustomers as $customer): 
                            ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($customer['ma_khachhang']) ?></td>
                                    <td><?= htmlspecialchars($customer['ho_ten']) ?></td>
                                    <td><?= htmlspecialchars($customer['email'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($customer['so_don_hang'] ?? 0) ?></td>
                                    <td><?= number_format($customer['tong_chi_tieu'] ?? 0, 0, ',', '.') ?> đ</td>
                                    <td><?= htmlspecialchars($customer['hang_thanh_vien'] ?? 'Bronze') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
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
        
        function switchTab(tab) {
            window.location.href = '?tab=' + tab;
        }
    </script>
</body>
</html>

