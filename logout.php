<?php
session_start();
$name = $_SESSION['customer_name'] ?? null;
session_unset();
session_destroy();
session_start();
if ($name) {
    $_SESSION['flash_welcome'] = 'Hẹn gặp lại ' . $name . '!';
}
header('Location: index.php');
exit;

