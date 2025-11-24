<?php
session_start();
require_once __DIR__ . '/config.php';

// Lấy danh sách xe BÁN CHẠY (dựa vào so_luong_ban) và random
$conn = db();

$carCatalog = [];
$carResult = pg_query($conn, "SELECT x.ma_xe, x.hang_xe, x.loai_xe, x.mau_xe, x.nam_san_xuat, x.gia_ban, x.tinh_trang, x.mo_ta,
                                     (SELECT url FROM hinh_anh_xe WHERE ma_xe = x.ma_xe AND la_chinh = TRUE LIMIT 1) as hinh_anh,
                                     COALESCE(tk.so_luong_ton, 0) as so_luong_ton,
                                     COALESCE(tk.so_luong_ban, 0) as so_luong_ban
                              FROM xe x
                              LEFT JOIN ton_kho tk ON x.ma_baotri = tk.ma_baotri
                              WHERE (x.tinh_trang != 'Dang_ban' OR x.tinh_trang IS NULL)
                              ORDER BY COALESCE(tk.so_luong_ban, 0) DESC, RANDOM()
                              LIMIT 8");
if ($carResult) {
    while ($row = pg_fetch_assoc($carResult)) {
        // Xác định trạng thái dựa trên tồn kho
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
        
        $carCatalog[] = [
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

function formatCurrency(int $value): string
{
    return number_format($value, 0, ',', '.') . ' ₫';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Showroom AutoLux | Hệ thống quản lý xe hơi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
        .flash-welcome {
            position: fixed;
            top: 90px;
            right: 40px;
            background: rgba(34,197,94,0.95);
            color: #0f172a;
            padding: 12px 20px;
            border-radius: 999px;
            box-shadow: 0 15px 35px rgba(34,197,94,0.35);
            opacity: 0;
            transform: translateY(-20px);
            animation: fadeSlide 3s forwards;
        }
        @keyframes fadeSlide {
            0% { opacity: 0; transform: translateY(-20px); }
            10% { opacity: 1; transform: translateY(0); }
            70% { opacity: 1; }
            100% { opacity: 0; transform: translateY(-20px); }
        }
        main {
            padding: 40px 5vw 80px;
            display: flex;
            flex-direction: column;
            gap: 48px;
        }
        section {
            border-radius: 24px;
            padding: 32px;
            background: var(--card);
            border: 1px solid var(--border);
            box-shadow: 0 25px 70px rgba(15,23,42,0.2);
        }
        #hero {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 32px;
            background: linear-gradient(120deg, #111827, #1f2937);
            color: #fff;
        }
        #hero img {
            width: 100%;
            border-radius: 18px;
            object-fit: cover;
            max-height: 320px;
        }
        h1, h2 {
            margin: 0 0 12px;
        }
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
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        .car-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 18px;
        }
        .car-card {
            border-radius: 20px;
            background: #fff;
            border: 1px solid var(--border);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .car-card img {
            width: 100%;
            height: 170px;
            object-fit: cover;
        }
        .car-card .info {
            padding: 18px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
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
        .badge.deal { background: rgba(248,113,113,0.2); color: #b91c1c; }
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }
        .service-card {
            padding: 20px;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: #fff;
        }
        footer {
            text-align: center;
            color: rgba(255,255,255,0.7);
            padding-bottom: 40px;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        label { font-weight: 600; font-size: 0.9rem; }
        input, select, textarea {
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 12px;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header_common.php'; ?>

    <?php if (!empty($_SESSION['flash_welcome'])): ?>
        <div class="flash-welcome"><?= htmlspecialchars($_SESSION['flash_welcome']) ?></div>
        <?php unset($_SESSION['flash_welcome']); ?>
    <?php endif; ?>

    <main>
        <section id="hero">
            <div>
                <p style="letter-spacing: 0.6px; opacity: 0.85;">SHOWROOM PREMIUM 2025</p>
                <h1>Quản lý và bán xe hơi toàn diện</h1>
                <p>Đồng bộ đơn hàng, kho xe, bảo hiểm - bảo hành và thanh toán chỉ trong một nền tảng. Sẵn sàng kết nối PostgreSQL hoặc MySQL bằng PHP.</p>
                <div style="margin-top: 18px;">
                    <a class="btn btn-primary" href="catalog.php">Khám phá bộ sưu tập</a>
                    <a class="btn" style="color:#fff;border:1px solid rgba(255,255,255,0.3);" href="register.php">Đăng ký đại lý</a>
                </div>
            </div>
            <img src="https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg" alt="Xe sang">
        </section>

        <section id="catalog">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">Danh mục xe nổi bật</h2>
                <a href="catalog.php" class="btn btn-primary" style="text-decoration: none;">Xem tất cả →</a>
            </div>
            <div class="car-grid">
                <?php if (empty($carCatalog)): ?>
                    <p style="text-align: center; color: #64748b; padding: 40px;">Chưa có xe nào trong hệ thống.</p>
                <?php else: ?>
                    <?php foreach ($carCatalog as $car): ?>
                        <article class="car-card">
                            <img src="<?= htmlspecialchars($car['image']) ?>" alt="<?= htmlspecialchars($car['model']) ?>" onerror="this.src='https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg'">
                            <div class="info">
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <strong><?= htmlspecialchars($car['brand']) ?></strong>
                                    <span class="badge <?= htmlspecialchars($car['statusClass']) ?>">
                                        <?= htmlspecialchars($car['status']) ?>
                                    </span>
                                </div>
                                <h3 style="margin:6px 0;"><?= htmlspecialchars($car['model']) ?></h3>
                                <p style="margin:0;color:#64748b;">Màu: <?= htmlspecialchars($car['color']) ?></p>
                                <?php if ($car['mo_ta']): ?>
                                    <p style="margin:8px 0;color:#64748b;font-size:0.85rem;line-height:1.4;"><?= htmlspecialchars(substr($car['mo_ta'], 0, 100)) ?><?= strlen($car['mo_ta']) > 100 ? '...' : '' ?></p>
                                <?php endif; ?>
                                <p style="font-weight:700;font-size:1.1rem;margin:8px 0;"><?= formatCurrency((int)$car['price']) ?></p>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <a href="car_detail.php?id=<?= htmlspecialchars($car['ma_xe']) ?>" class="btn" style="background: #e2e8f0; color: #0f172a; text-decoration: none; flex: 1; min-width: 120px;">Xem chi tiết</a>
                                    <a href="payment.php?car_id=<?= htmlspecialchars($car['ma_xe']) ?>" class="btn btn-primary" style="text-decoration:none;flex: 1; min-width: 120px;"><?= htmlspecialchars($car['buttonText']) ?></a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section id="services">
            <h2>Dịch vụ hậu mãi</h2>
            <div class="service-grid">
                <div class="service-card">
                    <h3>Bảo hiểm xe</h3>
                    <p>Quản lý gói bảo hiểm từ bảng `bao_hiem_xe`. Theo dõi phí, ngày mua và khách sở hữu.</p>
                </div>
                <div class="service-card">
                    <h3>Bảo hành dịch vụ</h3>
                    <p>Đồng bộ dữ liệu bảng `bao_hanh_dich_vu` để nhắc lịch hết hạn cho từng khách hàng.</p>
                </div>
                <div class="service-card">
                    <h3>Khuyến mãi & thanh toán</h3>
                    <p>Tự động áp dụng bảng `khuyen_mai` và xác nhận `thanh_toan` theo trạng thái đơn hàng.</p>
                </div>
                <div class="service-card">
                    <h3>Báo cáo thống kê</h3>
                    <p>Chuẩn hóa dữ liệu từ `bao_cao_thong_ke` và `tim_kiem` để phân tích nhu cầu thị trường.</p>
                </div>
            </div>
        </section>

        <section id="contact">
            <h2>Đăng ký tư vấn</h2>
            <form action="#" method="post">
                <label for="fullname">Họ tên</label>
                <input id="fullname" name="fullname" type="text" placeholder="Trần Minh Đức">
                <label for="phone">Số điện thoại</label>
                <input id="phone" name="phone" type="tel" placeholder="09xxxxxxxx">
                <label for="car_interest">Dòng xe quan tâm</label>
                <select id="car_interest" name="car_interest">
                    <?php foreach ($carCatalog as $car): ?>
                        <option value="<?= htmlspecialchars($car['ma_xe']) ?>"><?= htmlspecialchars($car['brand']) ?> <?= htmlspecialchars($car['model']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="note">Nhu cầu cụ thể</label>
                <textarea id="note" name="note" rows="4"></textarea>
                <button class="btn btn-primary" type="submit">Gửi yêu cầu</button>
            </form>
        </section>
    </main>

    <footer>
        &copy; <?= date('Y') ?> AutoLux Dealer · Xây dựng trên PHP + PostgreSQL/MySQL
    </footer>
</body>
</html>

