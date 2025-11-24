<?php
session_start();
require_once __DIR__ . '/config.php';

$conn = db();
$carId = (int)($_GET['id'] ?? 0);

if (!$carId) {
    header('Location: index.php');
    exit;
}

// Lấy thông tin xe
$car = null;
$carResult = pg_query_params($conn, 
    "SELECT x.ma_xe, x.hang_xe, x.loai_xe, x.mau_xe, x.nam_san_xuat, x.gia_ban, x.tinh_trang, x.mo_ta,
            COALESCE(tk.so_luong_ton, 0) as so_luong_ton
     FROM xe x
     LEFT JOIN ton_kho tk ON x.ma_baotri = tk.ma_baotri
     WHERE x.ma_xe = $1",
    [$carId]
);

if ($carResult && $row = pg_fetch_assoc($carResult)) {
    $car = $row;
} else {
    header('Location: index.php');
    exit;
}

// Lấy TẤT CẢ hình ảnh của xe
$images = [];
$imagesResult = pg_query_params($conn,
    'SELECT ma_anh, url, la_chinh FROM hinh_anh_xe WHERE ma_xe = $1 ORDER BY la_chinh DESC, ma_anh ASC',
    [$carId]
);
if ($imagesResult) {
    while ($img = pg_fetch_assoc($imagesResult)) {
        $images[] = $img;
    }
}

// Lấy thông tin người dùng
$loggedInUser = null;
if (!empty($_SESSION['customer_id'])) {
    $result = pg_query_params(
        $conn,
        'SELECT ma_khachhang, ho_ten, email, sdt, diachi FROM khach_hang WHERE ma_khachhang = $1 LIMIT 1',
        [$_SESSION['customer_id']]
    );
    if ($result) {
        $loggedInUser = pg_fetch_assoc($result);
    }
}

function formatCurrency($value) {
    return number_format($value, 0, ',', '.') . ' ₫';
}

$soLuongTon = (int)($car['so_luong_ton'] ?? 0);
$status = $soLuongTon > 0 ? 'Có sẵn' : 'Đang hết hàng';
$buttonText = $soLuongTon > 0 ? 'Đặt ngay' : 'Đặt hàng trước';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($car['hang_xe'] . ' ' . $car['loai_xe']) ?> | AutoLux</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #ff4d4f;
            --dark: #0f172a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(145deg, #020817, #0f172a 70%);
            color: #0f172a;
        }
        main {
            padding: 40px 5vw;
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
        .car-detail {
            background: rgba(255,255,255,0.95);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 70px rgba(15,23,42,0.2);
        }
        .car-header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        @media (max-width: 968px) {
            .car-header {
                grid-template-columns: 1fr;
            }
        }
        .image-gallery {
            position: relative;
        }
        .main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 16px;
            margin-bottom: 16px;
        }
        .thumbnail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 12px;
        }
        .thumbnail {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }
        .thumbnail:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        .thumbnail.active {
            border-color: var(--primary);
        }
        .car-info h1 {
            margin-top: 0;
            color: var(--primary);
        }
        .price {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin: 20px 0;
        }
        .specs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 24px 0;
        }
        .spec-item {
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
        }
        .spec-item strong {
            display: block;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }
        .spec-item span {
            font-size: 1.1rem;
            font-weight: 600;
        }
        .badge {
            display: inline-flex;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .badge.available {
            background: rgba(34,197,94,0.18);
            color: #15803d;
        }
        .badge.out-of-stock {
            background: rgba(251,191,36,0.2);
            color: #92400e;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 28px;
            border-radius: 999px;
            border: none;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            margin-top: 24px;
        }
        .btn-primary {
            background: linear-gradient(120deg, #ff4d4f, #ff6b6b);
            color: #fff;
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .description {
            margin-top: 32px;
            padding-top: 32px;
            border-top: 2px solid rgba(15,23,42,0.1);
        }
        .description h3 {
            margin-top: 0;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>
    
    <main>
        <a href="catalog.php" class="btn-back">← Quay lại danh mục</a>
        
        <div class="car-detail">
            <div class="car-header">
                <div class="image-gallery">
                    <?php if (!empty($images)): ?>
                        <img id="mainImage" src="<?= htmlspecialchars($images[0]['url']) ?>" 
                             alt="<?= htmlspecialchars($car['hang_xe'] . ' ' . $car['loai_xe']) ?>" 
                             class="main-image"
                             onerror="this.src='https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg'">
                        <?php if (count($images) > 1): ?>
                            <div class="thumbnail-grid">
                                <?php foreach ($images as $index => $img): ?>
                                    <img src="<?= htmlspecialchars($img['url']) ?>" 
                                         alt="Hình <?= $index + 1 ?>"
                                         class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                                         onclick="changeMainImage('<?= htmlspecialchars($img['url']) ?>', this)"
                                         onerror="this.src='https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg'">
                                <?php endforeach; ?>
                            </div>
                            <p style="text-align: center; color: #64748b; margin-top: 12px; font-size: 0.9rem;">
                                <?= count($images) ?> hình ảnh - Click để xem
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <img src="https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg" 
                             alt="Xe" class="main-image">
                    <?php endif; ?>
                </div>
                
                <div class="car-info">
                    <h1><?= htmlspecialchars($car['hang_xe']) ?> <?= htmlspecialchars($car['loai_xe']) ?></h1>
                    <span class="badge <?= $soLuongTon > 0 ? 'available' : 'out-of-stock' ?>">
                        <?= htmlspecialchars($status) ?>
                    </span>
                    
                    <div class="price"><?= formatCurrency($car['gia_ban']) ?></div>
                    
                    <div class="specs">
                        <div class="spec-item">
                            <strong>Hãng xe</strong>
                            <span><?= htmlspecialchars($car['hang_xe']) ?></span>
                        </div>
                        <div class="spec-item">
                            <strong>Loại xe</strong>
                            <span><?= htmlspecialchars($car['loai_xe'] ?? 'N/A') ?></span>
                        </div>
                        <div class="spec-item">
                            <strong>Màu sắc</strong>
                            <span><?= htmlspecialchars($car['mau_xe'] ?? 'N/A') ?></span>
                        </div>
                        <div class="spec-item">
                            <strong>Năm sản xuất</strong>
                            <span><?= htmlspecialchars($car['nam_san_xuat'] ?? 'N/A') ?></span>
                        </div>
                        <div class="spec-item">
                            <strong>Tình trạng</strong>
                            <span><?= htmlspecialchars($car['tinh_trang'] ?? 'N/A') ?></span>
                        </div>
                        <div class="spec-item">
                            <strong>Tồn kho</strong>
                            <span><?= $soLuongTon ?> xe</span>
                        </div>
                    </div>
                    
                    <?php if ($loggedInUser): ?>
                        <a href="payment.php?car_id=<?= htmlspecialchars($car['ma_xe']) ?>" class="btn btn-primary">
                            <?= htmlspecialchars($buttonText) ?>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">Đăng nhập để đặt hàng</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($car['mo_ta'])): ?>
                <div class="description">
                    <h3>Mô tả chi tiết</h3>
                    <p style="line-height: 1.8; color: #475569;">
                        <?= nl2br(htmlspecialchars($car['mo_ta'])) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php
        // Lấy xe liên quan (theo màu hoặc hãng xe, ngẫu nhiên)
        $relatedCars = [];
        $relatedQuery = "SELECT x.ma_xe, x.hang_xe, x.loai_xe, x.mau_xe, x.gia_ban,
                                (SELECT url FROM hinh_anh_xe WHERE ma_xe = x.ma_xe AND la_chinh = TRUE LIMIT 1) as hinh_anh,
                                COALESCE(tk.so_luong_ton, 0) as so_luong_ton
                         FROM xe x
                         LEFT JOIN ton_kho tk ON x.ma_baotri = tk.ma_baotri
                         WHERE x.ma_xe != $1 
                           AND (x.tinh_trang != 'Dang_ban' OR x.tinh_trang IS NULL)
                           AND (x.mau_xe = $2 OR x.hang_xe = $3)
                         ORDER BY RANDOM()
                         LIMIT 4";
        $relatedResult = pg_query_params($conn, $relatedQuery, [
            $carId,
            $car['mau_xe'],
            $car['hang_xe']
        ]);
        if ($relatedResult) {
            while ($row = pg_fetch_assoc($relatedResult)) {
                $relatedCars[] = $row;
            }
        }
        ?>
        
        <?php if (!empty($relatedCars)): ?>
            <div class="car-detail" style="margin-top: 40px;">
                <h2 style="margin-top: 0; margin-bottom: 24px;">Xe liên quan</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 24px;">
                    <?php foreach ($relatedCars as $relatedCar): ?>
                        <a href="car_detail.php?id=<?= htmlspecialchars($relatedCar['ma_xe']) ?>" 
                           style="text-decoration: none; color: inherit; display: block;">
                            <div style="background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid var(--border); transition: transform 0.3s ease;">
                                <img src="<?= htmlspecialchars($relatedCar['hinh_anh'] ?: 'https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg') ?>" 
                                     alt="<?= htmlspecialchars($relatedCar['loai_xe']) ?>"
                                     style="width: 100%; height: 180px; object-fit: cover;"
                                     onerror="this.src='https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg'">
                                <div style="padding: 16px;">
                                    <h3 style="margin: 0 0 8px 0; font-size: 1rem; color: var(--primary);">
                                        <?= htmlspecialchars($relatedCar['hang_xe']) ?> <?= htmlspecialchars($relatedCar['loai_xe']) ?>
                                    </h3>
                                    <p style="margin: 0 0 8px 0; color: #64748b; font-size: 0.9rem;">
                                        Màu: <?= htmlspecialchars($relatedCar['mau_xe']) ?>
                                    </p>
                                    <p style="margin: 0; font-weight: 700; color: var(--primary); font-size: 1.1rem;">
                                        <?= formatCurrency($relatedCar['gia_ban']) ?>
                                    </p>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
        function changeMainImage(url, thumbnail) {
            document.getElementById('mainImage').src = url;
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            thumbnail.classList.add('active');
        }
    </script>
</body>
</html>

