<?php
session_start();
require_once __DIR__ . '/config.php';


$conn = db();
$keyword = trim($_GET['q'] ?? '');
$results = [];

if ($keyword) {
    $searchResult = pg_query_params($conn, 
        "SELECT x.ma_xe, x.hang_xe, x.loai_xe, x.mau_xe, x.nam_san_xuat, x.gia_ban, x.tinh_trang, x.mo_ta,
                (SELECT url FROM hinh_anh_xe WHERE ma_xe = x.ma_xe AND la_chinh = TRUE LIMIT 1) as hinh_anh,
                COALESCE(tk.so_luong_ton, 0) as so_luong_ton
         FROM xe x
         LEFT JOIN ton_kho tk ON x.ma_baotri = tk.ma_baotri
         WHERE (x.tinh_trang != 'Dang_ban' OR x.tinh_trang IS NULL)
           AND (LOWER(x.hang_xe) LIKE LOWER($1) 
                OR LOWER(x.loai_xe) LIKE LOWER($1)
                OR LOWER(x.mau_xe) LIKE LOWER($1)
                OR LOWER(x.loai_xe || ' ' || x.hang_xe) LIKE LOWER($1))
         ORDER BY x.ma_xe DESC
         LIMIT 50",
        ['%' . $keyword . '%']
    );
    
    if ($searchResult) {
        while ($row = pg_fetch_assoc($searchResult)) {
            $soLuongTon = (int)($row['so_luong_ton'] ?? 0);
            if ($soLuongTon > 0) {
                $status = 'Có sẵn';
                $statusClass = 'available';
                $buttonText = 'Đặt ngay';
            } else {
                $status = 'Đang hết hàng';
                $statusClass = 'preorder';
                $buttonText = 'Đặt hàng trước';
            }
            
            $results[] = [
                'ma_xe' => $row['ma_xe'],
                'brand' => $row['hang_xe'] ?? 'N/A',
                'model' => $row['loai_xe'] ?? 'N/A',
                'color' => $row['mau_xe'] ?? 'N/A',
                'price' => (float)($row['gia_ban'] ?? 0),
                'status' => $status,
                'statusClass' => $statusClass,
                'buttonText' => $buttonText,
                'mo_ta' => $row['mo_ta'] ?? '',
                'image' => $row['hinh_anh'] ? htmlspecialchars($row['hinh_anh']) : 'https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg'
            ];
        }
    }
}

function formatCurrency($value) {
    return number_format($value, 0, ',', '.') . ' ₫';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tìm kiếm xe | AutoLux</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #ff4d4f;
            --dark: #0f172a;
            --card: rgba(255,255,255,0.9);
            --border: rgba(15,23,42,0.1);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(145deg, #020817, #0f172a 70%);
            color: #0f172a;
        }
        main {
            padding: 40px 5vw 80px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .search-section {
            background: var(--card);
            border-radius: 24px;
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 25px 70px rgba(15,23,42,0.2);
        }
        .search-form {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }
        .search-form input {
            flex: 1;
            padding: 14px 20px;
            border-radius: 999px;
            border: 2px solid var(--border);
            font-size: 1rem;
        }
        .search-form button {
            padding: 14px 28px;
            border-radius: 999px;
            border: none;
            background: linear-gradient(120deg, #ff4d4f, #ff6b6b);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
        .results-count {
            color: #64748b;
            margin-bottom: 24px;
        }
        .car-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }
        .car-card {
            border-radius: 20px;
            background: #fff;
            border: 1px solid var(--border);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }
        .car-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(15,23,42,0.15);
        }
        .car-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .car-card .info {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .badge {
            display: inline-flex;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge.available { background: rgba(34,197,94,0.18); color: #15803d; }
        .badge.preorder { background: rgba(251,191,36,0.2); color: #92400e; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 18px;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    
    <main>
        <div class="search-section">
            <h1 style="margin-top: 0;">🔍 Tìm kiếm xe</h1>
            <form method="get" action="search.php" class="search-form">
                <input type="text" name="q" placeholder="Nhập tên hãng xe, loại xe, màu sắc..." 
                       value="<?= htmlspecialchars($keyword) ?>" required>
                <button type="submit">Tìm kiếm</button>
            </form>
            
            <?php if ($keyword): ?>
                <div class="results-count">
                    <?php if (empty($results)): ?>
                        <p>Không tìm thấy kết quả nào cho "<strong><?= htmlspecialchars($keyword) ?></strong>"</p>
                    <?php else: ?>
                        <p>Tìm thấy <strong><?= count($results) ?></strong> kết quả cho "<strong><?= htmlspecialchars($keyword) ?></strong>"</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($results)): ?>
            <div class="car-grid">
                <?php foreach ($results as $car): ?>
                    <article class="car-card">
                        <img src="<?= htmlspecialchars($car['image']) ?>" 
                             alt="<?= htmlspecialchars($car['model']) ?>" 
                             onerror="this.src='https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg'">
                        <div class="info">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <strong><?= htmlspecialchars($car['brand']) ?></strong>
                                <span class="badge <?= htmlspecialchars($car['statusClass']) ?>">
                                    <?= htmlspecialchars($car['status']) ?>
                                </span>
                            </div>
                            <h3 style="margin:8px 0;"><?= htmlspecialchars($car['model']) ?></h3>
                            <p style="margin:0;color:#64748b;font-size:0.9rem;">Màu: <?= htmlspecialchars($car['color']) ?></p>
                            <?php if ($car['mo_ta']): ?>
                                <p style="margin:8px 0;color:#64748b;font-size:0.85rem;line-height:1.4;">
                                    <?= htmlspecialchars(substr($car['mo_ta'], 0, 100)) ?><?= strlen($car['mo_ta']) > 100 ? '...' : '' ?>
                                </p>
                            <?php endif; ?>
                            <p style="font-weight:700;font-size:1.2rem;color:var(--primary);margin:12px 0;">
                                <?= formatCurrency((int)$car['price']) ?>
                            </p>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <a href="car_detail.php?id=<?= htmlspecialchars($car['ma_xe']) ?>" 
                                   class="btn" style="background: #e2e8f0; color: #0f172a; text-decoration: none; flex: 1; min-width: 100px;">
                                    Xem chi tiết
                                </a>
                                <a href="payment.php?car_id=<?= htmlspecialchars($car['ma_xe']) ?>" 
                                   class="btn btn-primary" style="text-decoration: none; flex: 1; min-width: 100px;">
                                    <?= htmlspecialchars($car['buttonText']) ?>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php elseif ($keyword): ?>
            <div class="empty-state">
                <h3>Không tìm thấy kết quả</h3>
                <p>Vui lòng thử lại với từ khóa khác.</p>
                <a href="catalog.php" class="btn btn-primary" style="margin-top: 16px; text-decoration: none;">Xem tất cả xe</a>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
        // Load danh sách xe theo hãng khi hover
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
    </script>
</body>
</html>

