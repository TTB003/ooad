<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php?type=staff');
    exit;
}

$conn = db();
$isAdmin = ($_SESSION['staff_role'] ?? '') === 'Admin';

// Lấy thông tin chức vụ của nhân viên hiện tại từ session hoặc database
$currentStaffPosition = $_SESSION['staff_position'] ?? '';
if (!$currentStaffPosition && isset($_SESSION['staff_id'])) {
    $staffInfo = pg_query_params($conn, 'SELECT chuc_vu FROM nhan_vien WHERE ma_nhanvien = $1', [$_SESSION['staff_id']]);
    if ($staffInfo && $row = pg_fetch_assoc($staffInfo)) {
        $currentStaffPosition = $row['chuc_vu'] ?? '';
        $_SESSION['staff_position'] = $currentStaffPosition;
    }
}

// Chỉ admin mới lấy danh sách nhân viên, và loại bỏ các tài khoản Admin
$employees = [];
if ($isAdmin) {
    $empResult = pg_query($conn, "SELECT nv.ma_nhanvien, nv.ho_ten, nv.chuc_vu, nv.email, nv.sdt, lk.ten_loai
                                  FROM nhan_vien nv
                                  LEFT JOIN loai_tai_khoan lk ON nv.ma_loaitk = lk.ma_loaitk
                                  WHERE lk.ten_loai != 'Admin'
                                  ORDER BY nv.ma_nhanvien DESC");
    if ($empResult) {
        while ($row = pg_fetch_assoc($empResult)) {
            $employees[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel | AutoLux</title>
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
        main {
            background: #f1f5f9;
            border-top-left-radius: 32px;
            border-top-right-radius: 32px;
            padding: 32px 5vw 64px;
            min-height: calc(100vh - 96px);
            color: #0f172a;
        }
        form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px 24px;
            max-width: 880px;
        }
        label { font-weight: 600; display: block; margin-bottom: 6px; }
        input, select {
            width: 100%;
            padding: 11px 12px;
            border-radius: 12px;
            border: 1px solid rgba(15,23,42,0.15);
            font-size: 0.95rem;
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
        a { color: #38bdf8; text-decoration: none; }
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
        .status {
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(34,197,94,0.18);
            color: #15803d;
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
        .role-section {
            background: #fff;
            border-radius: 18px;
            padding: 24px;
            margin-top: 32px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.1);
        }
        .role-section h3 {
            margin-top: 0;
            color: #0f172a;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
        }
        .function-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        .function-card {
            background: #f8fafc;
            border: 1px solid rgba(15,23,42,0.1);
            border-radius: 12px;
            padding: 18px;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        .function-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(15,23,42,0.15);
            border-color: #3b82f6;
        }
        .function-card h4 {
            margin: 0 0 8px 0;
            color: #0f172a;
        }
        .function-card p {
            margin: 0;
            color: #64748b;
            font-size: 0.9rem;
        }
        .position-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
            background: linear-gradient(120deg, #06b6d4, #3b82f6);
            color: #fff;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    
    <?php if (!empty($_SESSION['flash_welcome'])): ?>
        <div style="position: fixed; top: 90px; right: 40px; background: rgba(34,197,94,0.95); color: #0f172a; padding: 12px 20px; border-radius: 999px; box-shadow: 0 15px 35px rgba(34,197,94,0.35); opacity: 0; transform: translateY(-20px); animation: fadeSlide 3s forwards; z-index: 1000;">
            <?= htmlspecialchars($_SESSION['flash_welcome']) ?>
        </div>
        <style>
            @keyframes fadeSlide {
                0% { opacity: 0; transform: translateY(-20px); }
                10% { opacity: 1; transform: translateY(0); }
                70% { opacity: 1; }
                100% { opacity: 0; transform: translateY(-20px); }
            }
        </style>
        <?php unset($_SESSION['flash_welcome']); ?>
    <?php endif; ?>
    
    <main>
        <?php if ($isAdmin): ?>
            <div class="role-section">
                <h3>Chức năng quản trị</h3>
                <div class="function-grid">
                    <a href="create_employee.php" class="function-card">
                        <h4>➕ Tạo nhân viên</h4>
                        <p>Tạo tài khoản nhân viên mới, phân quyền và chức vụ cho nhân viên</p>
                    </a>
                    <a href="manage_accounts.php" class="function-card">
                        <h4>👥 Quản lý tài khoản & phân quyền</h4>
                        <p>Quản lý tài khoản nhân viên, phân quyền hệ thống, cấp quyền truy cập</p>
                    </a>
                    <a href="manage_cars.php" class="function-card">
                        <h4>🚗 Quản lý xe</h4>
                        <p>Thêm, sửa, xóa thông tin xe, quản lý danh mục xe, cập nhật giá bán</p>
                    </a>
                    <a href="manage_inventory.php" class="function-card">
                        <h4>📦 Quản lý kho</h4>
                        <p>Quản lý tồn kho, nhập/xuất hàng, kiểm kê, cảnh báo hàng sắp hết</p>
                    </a>
                    <a href="manage_employees.php" class="function-card">
                        <h4>👨‍💼 Quản lý nhân viên</h4>
                        <p>Xem danh sách nhân viên, thông tin chi tiết, phân công công việc</p>
                    </a>
                    <a href="manage_customers.php" class="function-card">
                        <h4>👤 Quản lý khách hàng</h4>
                        <p>Xem thông tin khách hàng, lịch sử mua hàng, hạng thành viên</p>
                    </a>
                    <a href="manage_orders.php" class="function-card">
                        <h4>📋 Quản lý đơn hàng</h4>
                        <p>Xem và xử lý đơn hàng, cập nhật trạng thái, duyệt/hủy đơn hàng</p>
                    </a>
                    <a href="manage_payments.php" class="function-card">
                        <h4>💳 Quản lý thanh toán</h4>
                        <p>Xem lịch sử thanh toán, xử lý giao dịch, quản lý phương thức thanh toán</p>
                    </a>
                    <a href="manage_warranty.php" class="function-card">
                        <h4>🔧 Quản lý bảo hành – bảo dưỡng</h4>
                        <p>Quản lý dịch vụ bảo hành, bảo dưỡng, lịch sử sửa chữa</p>
                    </a>
                    <a href="manage_promotions.php" class="function-card">
                        <h4>🎁 Quản lý khuyến mãi</h4>
                        <p>Tạo và quản lý chương trình khuyến mãi, giảm giá, ưu đãi</p>
                    </a>
                    <a href="advanced_search.php" class="function-card">
                        <h4>🔍 Tìm kiếm nâng cao</h4>
                        <p>Tìm kiếm xe, khách hàng, đơn hàng với nhiều tiêu chí lọc</p>
                    </a>
                    <a href="reports.php" class="function-card">
                        <h4>📊 Thống kê & báo cáo</h4>
                        <p>Xem báo cáo doanh số, tồn kho, khách hàng, xuất báo cáo</p>
                    </a>
                    <a href="system_settings.php" class="function-card">
                        <h4>⚙️ Quản trị hệ thống</h4>
                        <p>Cấu hình hệ thống, sao lưu dữ liệu, quản lý log, bảo mật</p>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <p>Liên hệ admin nếu bạn cần cấp thêm tài khoản.</p>
        <?php endif; ?>

        <?php
        // Hiển thị chức năng theo chức vụ
        if (!$isAdmin && $currentStaffPosition):
        ?>
            <div class="role-section">
                <h3>Chức năng dành cho <?= htmlspecialchars($currentStaffPosition) ?></h3>
                
                <?php if ($currentStaffPosition === 'Nhân viên bán hàng'): ?>
                    <div class="function-grid">
                        <a href="staff_sales.php?tab=orders" class="function-card">
                            <h4>📋 Quản lý đơn hàng</h4>
                            <p>Xem và xử lý đơn hàng của khách hàng, cập nhật trạng thái đơn hàng</p>
                        </a>
                        <a href="staff_sales.php?tab=cars" class="function-card">
                            <h4>🚗 Quản lý xe</h4>
                            <p>Xem danh sách xe, thông tin chi tiết, tình trạng bán hàng</p>
                        </a>
                        <a href="staff_sales.php?tab=customers" class="function-card">
                            <h4>👥 Quản lý khách hàng</h4>
                            <p>Xem thông tin khách hàng, lịch sử mua hàng, hỗ trợ tư vấn</p>
                        </a>
                        <a href="staff_sales.php?tab=reports" class="function-card">
                            <h4>💰 Báo cáo doanh số</h4>
                            <p>Xem báo cáo doanh số bán hàng theo ngày, tuần, tháng</p>
                        </a>
                    </div>
                <?php elseif ($currentStaffPosition === 'Nhân viên kho'): ?>
                    <div class="function-grid">
                        <a href="staff_warehouse.php?tab=inventory" class="function-card">
                            <h4>📦 Quản lý tồn kho</h4>
                            <p>Xem số lượng tồn kho, nhập/xuất hàng, cập nhật số lượng</p>
                        </a>
                        <a href="staff_warehouse.php?tab=import" class="function-card">
                            <h4>📥 Nhập hàng</h4>
                            <p>Ghi nhận hàng nhập vào kho, cập nhật số lượng tồn</p>
                        </a>
                        <a href="staff_warehouse.php?tab=export" class="function-card">
                            <h4>📤 Xuất hàng</h4>
                            <p>Ghi nhận hàng xuất kho, cập nhật số lượng sau khi bán</p>
                        </a>
                        <a href="staff_warehouse.php?tab=reports" class="function-card">
                            <h4>📊 Báo cáo tồn kho</h4>
                            <p>Xem báo cáo tồn kho, cảnh báo hàng sắp hết, thống kê nhập xuất</p>
                        </a>
                    </div>
                <?php elseif ($currentStaffPosition === 'Nhân viên bảo hành/bảo dưỡng'): ?>
                    <div class="function-grid">
                        <a href="staff_warranty.php?tab=warranty" class="function-card">
                            <h4>🔧 Quản lý bảo hành</h4>
                            <p>Xem danh sách xe đang bảo hành, lịch sử bảo hành</p>
                        </a>
                        <a href="staff_warranty.php?tab=create_warranty" class="function-card">
                            <h4>📝 Ghi nhận bảo hành</h4>
                            <p>Tạo phiếu bảo hành mới, ghi nhận dịch vụ bảo hành đã thực hiện</p>
                        </a>
                        <a href="staff_warranty.php?tab=maintenance" class="function-card">
                            <h4>🛠️ Quản lý bảo dưỡng</h4>
                            <p>Lên lịch bảo dưỡng định kỳ, ghi nhận công việc bảo dưỡng</p>
                        </a>
                        <a href="staff_warranty.php?tab=history" class="function-card">
                            <h4>📋 Lịch sử dịch vụ</h4>
                            <p>Xem lịch sử bảo hành/bảo dưỡng của từng xe, khách hàng</p>
                        </a>
                    </div>
                <?php elseif ($currentStaffPosition === 'Nhân viên chăm sóc khách hàng'): ?>
                    <div class="function-grid">
                        <a href="staff_customer_service.php?tab=customers" class="function-card">
                            <h4>👥 Quản lý khách hàng</h4>
                            <p>Xem thông tin chi tiết khách hàng, lịch sử tương tác</p>
                        </a>
                        <a href="staff_customer_service.php?tab=orders" class="function-card">
                            <h4>📋 Lịch sử đơn hàng</h4>
                            <p>Xem lịch sử đơn hàng của khách hàng để hỗ trợ tốt hơn</p>
                        </a>
                        <a href="staff_customer_service.php?tab=support" class="function-card">
                            <h4>📞 Hỗ trợ khách hàng</h4>
                            <p>Trả lời câu hỏi, tư vấn, hỗ trợ khách hàng qua điện thoại/email</p>
                        </a>
                        <a href="staff_customer_service.php?tab=reports" class="function-card">
                            <h4>📊 Báo cáo CSKH</h4>
                            <p>Xem báo cáo về tình trạng chăm sóc khách hàng, mức độ hài lòng</p>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <h2 style="margin-top:40px;">Danh sách nhân viên</h2>
            <?php if (empty($employees)): ?>
                <p>Chưa có nhân viên nào trong hệ thống.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Họ tên</th>
                            <th>Chức vụ</th>
                            <th>Email</th>
                            <th>Số điện thoại</th>
                            <th>Quyền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td><?= htmlspecialchars($employee['ho_ten']) ?></td>
                                <td><?= htmlspecialchars($employee['chuc_vu'] ?? 'Chưa xác định') ?></td>
                                <td><?= htmlspecialchars($employee['email']) ?></td>
                                <td><?= htmlspecialchars($employee['sdt']) ?></td>
                                <td><span class="status"><?= htmlspecialchars($employee['ten_loai'] ?? 'Không phân loại') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Load danh sách xe theo hãng khi hover (giống index.php)
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
        });
    </script>
</body>
</html>