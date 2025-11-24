<?php
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$conn = db();
$userId = $_SESSION['customer_id'];

$fullName = trim($_POST['fullname'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

$errors = [];

if ($fullName === '') {
    $errors[] = 'Họ tên không được bỏ trống.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email không hợp lệ.';
}
if (!preg_match('/^0\d{9}$/', $phone)) {
    $errors[] = 'Số điện thoại phải bắt đầu bằng 0 và gồm 10 chữ số.';
}

if (!$errors) {
    $dup = pg_query_params(
        $conn,
        'SELECT 1 FROM khach_hang WHERE email = $1 AND ma_khachhang <> $2',
        [$email, $userId]
    );
    if ($dup && pg_num_rows($dup) > 0) {
        $errors[] = 'Email đã được sử dụng bởi tài khoản khác.';
    }
}

if ($errors) {
    $_SESSION['flash_welcome'] = implode(' ', $errors);
    header('Location: index.php');
    exit;
}

$result = pg_query_params(
    $conn,
    'UPDATE khach_hang SET ho_ten=$1, email=$2, sdt=$3, diachi=$4 WHERE ma_khachhang=$5',
    [$fullName, $email, $phone, $address, $userId]
);

if ($result === false) {
    $_SESSION['flash_welcome'] = 'Không thể cập nhật: ' . pg_last_error($conn);
} else {
    $_SESSION['customer_name'] = $fullName;
    $_SESSION['flash_welcome'] = 'Thông tin đã được cập nhật!';
}

header('Location: index.php');
exit;

