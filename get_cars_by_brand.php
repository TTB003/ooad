<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$brand = $_GET['brand'] ?? '';

if (empty($brand)) {
    echo json_encode(['success' => false, 'message' => 'Brand not specified']);
    exit;
}

$conn = db();

$cars = [];
$carResult = pg_query_params($conn, 
    "SELECT x.ma_xe, x.hang_xe, x.loai_xe, x.gia_ban,
            (SELECT url FROM hinh_anh_xe WHERE ma_xe = x.ma_xe AND la_chinh = TRUE LIMIT 1) as hinh_anh
     FROM xe x
     WHERE x.hang_xe = $1 AND (x.tinh_trang != 'Dang_ban' OR x.tinh_trang IS NULL)
     ORDER BY x.ma_xe DESC
     LIMIT 10",
    [$brand]
);

if ($carResult) {
    while ($row = pg_fetch_assoc($carResult)) {
        $cars[] = [
            'ma_xe' => $row['ma_xe'],
            'hang_xe' => $row['hang_xe'],
            'loai_xe' => $row['loai_xe'],
            'gia_ban' => (float)($row['gia_ban'] ?? 0),
            'hinh_anh' => $row['hinh_anh'] ?: 'https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg'
        ];
    }
}

echo json_encode([
    'success' => true,
    'cars' => $cars
]);

