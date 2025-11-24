<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

if (empty($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để đặt trước.']);
    exit;
}

$carId = (int)($_POST['car_id'] ?? 0);
if ($carId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Xe không hợp lệ.']);
    exit;
}

$conn = db();

$carResult = pg_query_params(
    $conn,
    "SELECT x.ma_xe, x.hang_xe, x.loai_xe, COALESCE(tk.so_luong_ton, 0) AS so_luong_ton
     FROM xe x
     LEFT JOIN ton_kho tk ON x.ma_baotri = tk.ma_baotri
     WHERE x.ma_xe = $1",
    [$carId]
);

if (!$carResult || !($car = pg_fetch_assoc($carResult))) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy xe.']);
    exit;
}

$stock = (int)($car['so_luong_ton'] ?? 0);
if ($stock > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Xe đã có sẵn. Bạn có thể đặt hàng trực tiếp.'
    ]);
    exit;
}

$customerResult = pg_query_params(
    $conn,
    'SELECT ma_khachhang, ho_ten, email, sdt FROM khach_hang WHERE ma_khachhang = $1 LIMIT 1',
    [$_SESSION['customer_id']]
);

if (!$customerResult || !($customer = pg_fetch_assoc($customerResult))) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy thông tin khách hàng.']);
    exit;
}

$insertResult = pg_query_params(
    $conn,
    "INSERT INTO dat_truoc_xe (ma_xe, ma_khachhang, trang_thai)
     VALUES ($1, $2, 'cho_hang')
     ON CONFLICT (ma_xe, ma_khachhang)
     DO UPDATE SET trang_thai = 'cho_hang',
                   da_thong_bao = FALSE,
                   ngay_dat = NOW(),
                   ngay_thong_bao = NULL",
    [$carId, $customer['ma_khachhang']]
);

if (!$insertResult) {
    echo json_encode(['success' => false, 'message' => 'Không thể ghi nhận yêu cầu đặt trước.']);
    exit;
}

$carName = trim(($car['hang_xe'] ?? '') . ' ' . ($car['loai_xe'] ?? ''));
$customerMessage = sprintf(
    'AutoLux đã ghi nhận yêu cầu đặt trước cho %s. Nhân viên sẽ liên hệ khi xe về hàng.',
    $carName
);

pg_query_params(
    $conn,
    'INSERT INTO thong_bao (tieu_de, noi_dung, loai_thongbao, ma_khachhang) VALUES ($1, $2, $3, $4)',
    [
        'Đã nhận đặt trước ' . $carName,
        $customerMessage,
        'dat_truoc',
        $customer['ma_khachhang']
    ]
);

$staffResult = pg_query(
    $conn,
    "SELECT ma_nhanvien FROM nhan_vien WHERE chuc_vu IN ('Nhân viên bán hàng', 'Administrator')"
);
if ($staffResult) {
    while ($staff = pg_fetch_assoc($staffResult)) {
        pg_query_params(
            $conn,
            'INSERT INTO thong_bao (tieu_de, noi_dung, loai_thongbao, ma_nhanvien) VALUES ($1, $2, $3, $4)',
            [
                'Yêu cầu đặt trước từ khách hàng',
                'Khách hàng ' . ($customer['ho_ten'] ?? 'Chưa xác định') . ' muốn đặt trước xe ' . $carName . '. Vui lòng chuẩn bị liên hệ khi xe về.',
                'dat_truoc',
                $staff['ma_nhanvien']
            ]
        );
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Đã ghi nhận yêu cầu đặt trước. Nhân viên sẽ liên hệ khi có hàng.'
]);

