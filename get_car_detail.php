<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$carId = (int)($_GET['id'] ?? $_GET['car_id'] ?? 0);

if (!$carId) {
    echo json_encode(['success' => false, 'message' => 'Car ID required']);
    exit;
}

$conn = db();

$carResult = pg_query_params($conn,
    'SELECT x.ma_xe, x.hang_xe, x.loai_xe, x.mau_xe, x.nam_san_xuat, x.mo_ta
     FROM xe x WHERE x.ma_xe = $1',
    [$carId]
);

if ($carResult && $car = pg_fetch_assoc($carResult)) {
    echo json_encode([
        'success' => true,
        'car' => $car
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Car not found'
    ]);
}

