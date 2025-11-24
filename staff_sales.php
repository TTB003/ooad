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
if ($currentPosition !== 'Nhân viên bán hàng') {
    header('Location: admin.php');
    exit;
}

$message = '';
$errors = [];
$activeTab = $_GET['tab'] ?? 'orders';

// Xử lý các action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_order_status') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        
        if ($orderId && in_array($newStatus, ['Cho_duyet', 'Da_duyet', 'Da_thanh_toan', 'Huy'])) {
            $result = pg_query_params($conn, 
                'UPDATE don_hang SET trang_thai = $1 WHERE ma_donhang = $2',
                [$newStatus, $orderId]
            );
            if ($result) {
                $message = 'Đã cập nhật trạng thái đơn hàng thành công.';
            } else {
                $errors[] = 'Không thể cập nhật trạng thái đơn hàng.';
            }
        }
    }
}

// Lấy danh sách đơn hàng
$orders = [];
$ordersResult = pg_query($conn, "SELECT dh.ma_donhang, dh.ngay_lap, dh.tong_tien, dh.trang_thai,
                                         kh.ho_ten as ten_khachhang, kh.email, kh.sdt
                                  FROM don_hang dh
                                  INNER JOIN khach_hang kh ON dh.ma_khachhang = kh.ma_khachhang
                                  ORDER BY dh.ngay_lap DESC, dh.ma_donhang DESC
                                  LIMIT 50");
if ($ordersResult) {
    while ($row = pg_fetch_assoc($ordersResult)) {
        $orders[] = $row;
    }
}

// Lấy danh sách xe
$cars = [];
$carsResult = pg_query($conn, "SELECT ma_xe, hang_xe, loai_xe, mau_xe, nam_san_xuat, gia_ban, tinh_trang
                                FROM xe
                                ORDER BY ma_xe DESC
                                LIMIT 50");
if ($carsResult) {
    while ($row = pg_fetch_assoc($carsResult)) {
        $cars[] = $row;
    }
}

// Lấy danh sách khách hàng
$customers = [];
$customersResult = pg_query($conn, "SELECT ma_khachhang, ho_ten, email, sdt, hang_thanh_vien, diachi
                                    FROM khach_hang
                                    ORDER BY ma_khachhang DESC
                                    LIMIT 50");
if ($customersResult) {
    while ($row = pg_fetch_assoc($customersResult)) {
        $customers[] = $row;
    }
}

// Tính báo cáo doanh số
$salesReport = [];
$reportResult = pg_query($conn, "SELECT 
                                    DATE_TRUNC('day', ngay_lap) as ngay,
                                    COUNT(*) as so_don,
                                    SUM(tong_tien) as tong_doanh_thu
                                  FROM don_hang
                                  WHERE trang_thai = 'Da_thanh_toan'
                                  GROUP BY DATE_TRUNC('day', ngay_lap)
                                  ORDER BY ngay DESC
                                  LIMIT 30");
if ($reportResult) {
    while ($row = pg_fetch_assoc($reportResult)) {
        $salesReport[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Nhân viên bán hàng | AutoLux</title>
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
        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            border: none;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            background: rgba(59,130,246,0.18);
            color: #1e40af;
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
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    
    <main>
        <div class="container">
            <a href="admin.php" class="btn-back">← Quay lại trang quản trị</a>
            
            <h2>Chức năng dành cho Nhân viên bán hàng</h2>
            
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
                <button class="tab <?= $activeTab === 'orders' ? 'active' : '' ?>" onclick="switchTab('orders')">📋 Quản lý đơn hàng</button>
                <button class="tab <?= $activeTab === 'cars' ? 'active' : '' ?>" onclick="switchTab('cars')">🚗 Quản lý xe</button>
                <button class="tab <?= $activeTab === 'customers' ? 'active' : '' ?>" onclick="switchTab('customers')">👥 Quản lý khách hàng</button>
                <button class="tab <?= $activeTab === 'reports' ? 'active' : '' ?>" onclick="switchTab('reports')">💰 Báo cáo doanh số</button>
            </div>
            
            <!-- Tab Quản lý đơn hàng -->
            <div id="tab-orders" class="tab-content <?= $activeTab === 'orders' ? 'active' : '' ?>">
                <h3>Danh sách đơn hàng</h3>
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
                                <th>Thao tác</th>
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
                                    <td>
                                        <button class="btn-action" onclick="openStatusModal(<?= htmlspecialchars($order['ma_donhang']) ?>, '<?= htmlspecialchars($order['trang_thai']) ?>')">Cập nhật</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Tab Quản lý xe -->
            <div id="tab-cars" class="tab-content <?= $activeTab === 'cars' ? 'active' : '' ?>">
                <h3>Danh sách xe</h3>
                <?php if (empty($cars)): ?>
                    <p>Chưa có xe nào trong hệ thống.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Mã xe</th>
                                <th>Hãng xe</th>
                                <th>Loại xe</th>
                                <th>Màu xe</th>
                                <th>Năm SX</th>
                                <th>Giá bán</th>
                                <th>Tình trạng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cars as $car): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($car['ma_xe']) ?></td>
                                    <td><?= htmlspecialchars($car['hang_xe']) ?></td>
                                    <td><?= htmlspecialchars($car['loai_xe'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($car['mau_xe'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($car['nam_san_xuat'] ?? 'N/A') ?></td>
                                    <td><?= number_format($car['gia_ban'] ?? 0, 0, ',', '.') ?> đ</td>
                                    <td>
                                        <?php
                                        $tinhTrangMap = [
                                            'Moi' => 'Mới',
                                            'Da_qua_su_dung' => 'Đã qua sử dụng',
                                            'Dang_ban' => 'Đang bán'
                                        ];
                                        echo htmlspecialchars($tinhTrangMap[$car['tinh_trang']] ?? $car['tinh_trang']);
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Tab Báo cáo doanh số -->
            <div id="tab-reports" class="tab-content <?= $activeTab === 'reports' ? 'active' : '' ?>">
                <h3>Báo cáo doanh số</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Tổng đơn hàng</h3>
                        <div class="value"><?= count($orders) ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Đơn đã thanh toán</h3>
                        <div class="value">
                            <?= count(array_filter($orders, fn($o) => $o['trang_thai'] === 'Da_thanh_toan')) ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <h3>Tổng doanh thu</h3>
                        <div class="value">
                            <?php
                            $totalRevenue = array_sum(array_map(
                                fn($o) => $o['tong_tien'] ?? 0,
                                array_filter($orders, fn($o) => $o['trang_thai'] === 'Da_thanh_toan')
                            ));
                            echo number_format($totalRevenue, 0, ',', '.') . ' đ';
                            ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($salesReport)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Ngày</th>
                                <th>Số đơn</th>
                                <th>Tổng doanh thu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salesReport as $report): ?>
                                <tr>
                                    <td><?= htmlspecialchars($report['ngay']) ?></td>
                                    <td><?= htmlspecialchars($report['so_don']) ?></td>
                                    <td><?= number_format($report['tong_doanh_thu'] ?? 0, 0, ',', '.') ?> đ</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Chưa có dữ liệu báo cáo.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Modal cập nhật trạng thái đơn hàng -->
    <div id="statusModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #fff; border-radius: 18px; padding: 24px; max-width: 400px; width: 100%;">
            <h3>Cập nhật trạng thái đơn hàng</h3>
            <form method="post" id="statusForm">
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="order_id" id="status_order_id">
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 6px;">Trạng thái</label>
                    <select name="status" id="status_select" required style="width: 100%; padding: 10px 12px; border-radius: 12px; border: 1px solid rgba(15,23,42,0.15);">
                        <option value="Cho_duyet">Chờ duyệt</option>
                        <option value="Da_duyet">Đã duyệt</option>
                        <option value="Da_thanh_toan">Đã thanh toán</option>
                        <option value="Huy">Hủy</option>
                    </select>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="submit" style="flex: 1; padding: 10px 20px; border-radius: 999px; border: none; background: linear-gradient(120deg, #06b6d4, #3b82f6); color: #fff; font-weight: 600; cursor: pointer;">Lưu</button>
                    <button type="button" onclick="closeStatusModal()" style="flex: 1; padding: 10px 20px; border-radius: 999px; border: none; background: #e2e8f0; color: #0f172a; font-weight: 600; cursor: pointer;">Hủy</button>
                </div>
            </form>
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
        
        function switchTab(tab) {
            window.location.href = '?tab=' + tab;
        }
        
        function openStatusModal(orderId, currentStatus) {
            document.getElementById('status_order_id').value = orderId;
            document.getElementById('status_select').value = currentStatus;
            document.getElementById('statusModal').style.display = 'flex';
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStatusModal();
            }
        });
    </script>
</body>
</html>

