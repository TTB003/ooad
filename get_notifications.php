<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$conn = db();
$notifications = [];
$countOnly = isset($_GET['count_only']) && $_GET['count_only'] == '1';

if (!empty($_SESSION['customer_id'])) {
    // Khách hàng
    if ($countOnly) {
        $result = pg_query_params($conn,
            'SELECT COUNT(*) as count FROM thong_bao WHERE ma_khachhang = $1 AND da_doc = FALSE',
            [$_SESSION['customer_id']]
        );
        if ($result && $row = pg_fetch_assoc($result)) {
            echo json_encode(['success' => true, 'count' => (int)$row['count']]);
        } else {
            echo json_encode(['success' => true, 'count' => 0]);
        }
        exit;
    }
    
    $result = pg_query_params($conn,
        "SELECT ma_thongbao, tieu_de, noi_dung, loai_thongbao, ma_donhang, da_doc,
                TO_CHAR(ngay_tao, 'DD/MM/YYYY HH24:MI') as ngay_tao
         FROM thong_bao
         WHERE ma_khachhang = $1
         ORDER BY ngay_tao DESC
         LIMIT 20",
        [$_SESSION['customer_id']]
    );
} elseif (!empty($_SESSION['staff_id'])) {
    // Nhân viên/Admin
    $isAdmin = ($_SESSION['staff_role'] ?? '') === 'Admin';
    
    if ($countOnly) {
        if ($isAdmin) {
            // Admin nhận tất cả thông báo hệ thống
            $result = pg_query($conn,
                "SELECT COUNT(*) as count FROM thong_bao WHERE (ma_nhanvien IS NULL OR ma_nhanvien = " . (int)$_SESSION['staff_id'] . ") AND da_doc = FALSE"
            );
        } else {
            // Nhân viên chỉ nhận thông báo của mình
            $result = pg_query_params($conn,
                'SELECT COUNT(*) as count FROM thong_bao WHERE ma_nhanvien = $1 AND da_doc = FALSE',
                [$_SESSION['staff_id']]
            );
        }
        if ($result && $row = pg_fetch_assoc($result)) {
            echo json_encode(['success' => true, 'count' => (int)$row['count']]);
        } else {
            echo json_encode(['success' => true, 'count' => 0]);
        }
        exit;
    }
    
    if ($isAdmin) {
        // Admin nhận tất cả thông báo hệ thống
        $result = pg_query($conn,
            "SELECT ma_thongbao, tieu_de, noi_dung, loai_thongbao, ma_donhang, da_doc,
                    TO_CHAR(ngay_tao, 'DD/MM/YYYY HH24:MI') as ngay_tao
             FROM thong_bao
             WHERE ma_nhanvien IS NULL OR ma_nhanvien = " . (int)$_SESSION['staff_id'] . "
             ORDER BY ngay_tao DESC
             LIMIT 20"
        );
    } else {
        // Nhân viên chỉ nhận thông báo của mình
        $result = pg_query_params($conn,
            "SELECT ma_thongbao, tieu_de, noi_dung, loai_thongbao, ma_donhang, da_doc,
                    TO_CHAR(ngay_tao, 'DD/MM/YYYY HH24:MI') as ngay_tao
             FROM thong_bao
             WHERE ma_nhanvien = $1
             ORDER BY ngay_tao DESC
             LIMIT 20",
            [$_SESSION['staff_id']]
        );
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

if ($result) {
    while ($row = pg_fetch_assoc($result)) {
        $notifications[] = $row;
    }
}

echo json_encode(['success' => true, 'notifications' => $notifications]);
?>

