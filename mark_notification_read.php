<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$notificationId = (int)($_POST['notification_id'] ?? 0);
if (!$notificationId) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit;
}

$conn = db();

// Kiểm tra quyền sở hữu thông báo
if (!empty($_SESSION['customer_id'])) {
    $check = pg_query_params($conn,
        'SELECT ma_thongbao FROM thong_bao WHERE ma_thongbao = $1 AND ma_khachhang = $2',
        [$notificationId, $_SESSION['customer_id']]
    );
} elseif (!empty($_SESSION['staff_id'])) {
    $isAdmin = ($_SESSION['staff_role'] ?? '') === 'Admin';
    if ($isAdmin) {
        $check = pg_query_params($conn,
            'SELECT ma_thongbao FROM thong_bao WHERE ma_thongbao = $1 AND (ma_nhanvien IS NULL OR ma_nhanvien = $2)',
            [$notificationId, $_SESSION['staff_id']]
        );
    } else {
        $check = pg_query_params($conn,
            'SELECT ma_thongbao FROM thong_bao WHERE ma_thongbao = $1 AND ma_nhanvien = $2',
            [$notificationId, $_SESSION['staff_id']]
        );
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($check && pg_num_rows($check) > 0) {
    $result = pg_query_params($conn,
        'UPDATE thong_bao SET da_doc = TRUE WHERE ma_thongbao = $1',
        [$notificationId]
    );
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Notification not found']);
}
?>
