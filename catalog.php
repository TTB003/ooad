<?php
session_start();
require_once __DIR__ . '/config.php';

$conn = db();
$selectedBrand = $_GET['brand'] ?? null;
$selectedColor = $_GET['color'] ?? null;
$priceMin = isset($_GET['price_min']) ? (int)$_GET['price_min'] : null;
$priceMax = isset($_GET['price_max']) ? (int)$_GET['price_max'] : null;

// Lấy danh sách hãng xe có xe
$brands = [];
$brandResult = pg_query($conn, "SELECT DISTINCT hx.ma_hangxe, hx.ten_hangxe, hx.logo_url,
                                       (SELECT COUNT(*) FROM xe WHERE hang_xe = hx.ten_hangxe) as so_xe
                                FROM hang_xe hx
                                INNER JOIN xe x ON x.hang_xe = hx.ten_hangxe
                                ORDER BY hx.ten_hangxe");
if ($brandResult) {
    while ($row = pg_fetch_assoc($brandResult)) {
        $brands[] = $row;
    }
}

// Lấy danh sách màu sắc có sẵn
$colors = [];
$colorResult = pg_query($conn, "SELECT DISTINCT mau_xe FROM xe WHERE mau_xe IS NOT NULL AND mau_xe != '' ORDER BY mau_xe");
if ($colorResult) {
    while ($row = pg_fetch_assoc($colorResult)) {
        $colors[] = $row['mau_xe'];
    }
}

// Lấy danh sách xe (theo hãng, màu, giá nếu có chọn)
$cars = [];
$carQuery = "SELECT x.ma_xe, x.hang_xe, x.loai_xe, x.mau_xe, x.nam_san_xuat, x.gia_ban, x.tinh_trang, x.mo_ta,
                    (SELECT url FROM hinh_anh_xe WHERE ma_xe = x.ma_xe AND la_chinh = TRUE LIMIT 1) as hinh_anh,
                    COALESCE(tk.so_luong_ton, 0) as so_luong_ton
             FROM xe x
             LEFT JOIN ton_kho tk ON x.ma_baotri = tk.ma_baotri
             WHERE (x.tinh_trang != 'Dang_ban' OR x.tinh_trang IS NULL)";

$params = [];
$paramCount = 0;

if ($selectedBrand) {
    $paramCount++;
    $carQuery .= " AND x.hang_xe = $" . $paramCount;
    $params[] = $selectedBrand;
}

if ($selectedColor) {
    $paramCount++;
    $carQuery .= " AND x.mau_xe = $" . $paramCount;
    $params[] = $selectedColor;
}

if ($priceMin !== null) {
    $carQuery .= " AND x.gia_ban >= " . (int)$priceMin;
}

if ($priceMax !== null) {
    $carQuery .= " AND x.gia_ban <= " . (int)$priceMax;
}

$carQuery .= " ORDER BY x.ma_xe DESC LIMIT 50";

if (!empty($params)) {
    $carResult = pg_query_params($conn, $carQuery, $params);
} else {
    $carResult = pg_query($conn, $carQuery);
}

if ($carResult) {
    while ($row = pg_fetch_assoc($carResult)) {
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
        
        $cars[] = [
            'ma_xe' => $row['ma_xe'],
            'brand' => $row['hang_xe'] ?? 'N/A',
            'model' => $row['loai_xe'] ?? 'N/A',
            'segment' => $row['tinh_trang'] ?? 'N/A',
            'price' => (float)($row['gia_ban'] ?? 0),
            'status' => $status,
            'statusClass' => $statusClass,
            'buttonText' => $buttonText,
            'color' => $row['mau_xe'] ?? 'N/A',
            'mo_ta' => $row['mo_ta'] ?? '',
            'image' => $row['hinh_anh'] ? htmlspecialchars($row['hinh_anh']) : 'https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg'
        ];
    }
}

function formatCurrency($value) {
    return number_format($value, 0, ',', '.') . ' ₫';
}

// Header common sẽ xử lý loggedInUser
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Danh mục xe | AutoLux</title>
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
            background: rgba(255,255,255,0.2);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
        }
        .section {
            background: var(--card);
            border-radius: 24px;
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 25px 70px rgba(15,23,42,0.2);
        }
        h1, h2 {
            margin-top: 0;
        }
        .brand-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 18px;
            margin-top: 24px;
        }
        .brand-card {
            background: #fff;
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .brand-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(255,77,79,0.2);
        }
        .brand-card.active {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(255,77,79,0.1), rgba(255,107,107,0.1));
        }
        .brand-card h3 {
            margin: 0 0 8px 0;
            color: var(--primary);
        }
        .brand-card .count {
            color: #64748b;
            font-size: 0.9rem;
        }
        .car-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 24px;
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
        .btn-primary:hover {
            opacity: 0.9;
        }
        .filter-section {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 16px;
            border-radius: 999px;
            border: 2px solid var(--border);
            background: #fff;
            color: #0f172a;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-btn:hover, .filter-btn.active {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        .empty-state h3 {
            color: #0f172a;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    
    <main>
        <div class="container">
            <a href="index.php" class="btn-back">← Quay lại trang chủ</a>
            
            <div class="section">
                <h1>Danh mục xe</h1>
                
                <div class="filter-section">
                    <a href="catalog.php" class="filter-btn <?= !$selectedBrand && !$selectedColor && $priceMin === null && $priceMax === null ? 'active' : '' ?>">
                        Tất cả hãng xe
                    </a>
                    <?php foreach ($brands as $brand): ?>
                        <a href="catalog.php?brand=<?= urlencode($brand['ten_hangxe']) ?><?= $selectedColor ? '&color=' . urlencode($selectedColor) : '' ?><?= $priceMin !== null ? '&price_min=' . $priceMin : '' ?><?= $priceMax !== null ? '&price_max=' . $priceMax : '' ?>" 
                           class="filter-btn <?= $selectedBrand === $brand['ten_hangxe'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($brand['ten_hangxe']) ?> (<?= htmlspecialchars($brand['so_xe']) ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <form method="get" action="catalog.php" class="filter-form" style="margin-top: 20px; padding: 20px; background: #f8fafc; border-radius: 12px;">
                    <?php if ($selectedBrand): ?>
                        <input type="hidden" name="brand" value="<?= htmlspecialchars($selectedBrand) ?>">
                    <?php endif; ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
                        <div>
                            <label for="color_filter" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem;">Lọc theo màu:</label>
                            <select id="color_filter" name="color" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border);">
                                <option value="">Tất cả màu</option>
                                <?php foreach ($colors as $color): ?>
                                    <option value="<?= htmlspecialchars($color) ?>" <?= $selectedColor === $color ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($color) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="price_min" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem;">Giá từ (₫):</label>
                            <input type="number" id="price_min" name="price_min" 
                                   value="<?= $priceMin !== null ? htmlspecialchars($priceMin) : '' ?>" 
                                   placeholder="0" 
                                   min="0"
                                   style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border);">
                        </div>
                        <div>
                            <label for="price_max" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem;">Giá đến (₫):</label>
                            <input type="number" id="price_max" name="price_max" 
                                   value="<?= $priceMax !== null ? htmlspecialchars($priceMax) : '' ?>" 
                                   placeholder="Không giới hạn" 
                                   min="0"
                                   style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border);">
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; margin: 0;">🔍 Lọc</button>
                        </div>
                    </div>
                    <?php if ($selectedColor || $priceMin !== null || $priceMax !== null): ?>
                        <div style="margin-top: 12px;">
                            <a href="catalog.php<?= $selectedBrand ? '?brand=' . urlencode($selectedBrand) : '' ?>" 
                               style="color: var(--primary); text-decoration: none; font-size: 0.9rem;">
                                ✕ Xóa bộ lọc
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
                
                <?php if (!$selectedBrand): ?>
                    <!-- Hiển thị danh sách hãng xe -->
                    <h2>Chọn hãng xe để xem danh sách</h2>
                    <?php if (empty($brands)): ?>
                        <div class="empty-state">
                            <h3>Chưa có hãng xe nào</h3>
                            <p>Vui lòng liên hệ admin để thêm hãng xe mới.</p>
                        </div>
                    <?php else: ?>
                        <div class="brand-grid">
                            <?php foreach ($brands as $brand): ?>
                                <a href="catalog.php?brand=<?= urlencode($brand['ten_hangxe']) ?>" class="brand-card">
                                    <?php if ($brand['logo_url']): ?>
                                        <img src="<?= htmlspecialchars($brand['logo_url']) ?>" 
                                             alt="<?= htmlspecialchars($brand['ten_hangxe']) ?>" 
                                             style="width: 120px; height: 80px; object-fit: contain; margin: 0 auto 16px; display: block;"
                                             onerror="this.style.display='none'">
                                    <?php else: ?>
                                        <div style="width: 120px; height: 80px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border-radius: 12px; font-size: 2rem;">
                                            🚗
                                        </div>
                                    <?php endif; ?>
                                    <h3><?= htmlspecialchars($brand['ten_hangxe']) ?></h3>
                                    <p class="count"><?= htmlspecialchars($brand['so_xe']) ?> xe</p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Hiển thị xe của hãng đã chọn -->
                    <h2>Xe của hãng: <strong><?= htmlspecialchars($selectedBrand) ?></strong></h2>
                    <?php if (empty($cars)): ?>
                        <div class="empty-state">
                            <h3>Chưa có xe nào của hãng này</h3>
                            <p>Vui lòng quay lại và chọn hãng xe khác.</p>
                            <a href="catalog.php" class="btn btn-primary" style="margin-top: 16px;">Xem tất cả hãng xe</a>
                        </div>
                    <?php else: ?>
                        <div class="car-grid">
                            <?php foreach ($cars as $car): ?>
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
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
</body>
</html>

