<?php
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_PORT = '5432';
const DB_NAME = 'qlxe';
const DB_USER = 'postgres';
const DB_PASS = '123456';

/**
 * Trả về kết nối PostgreSQL dùng lại cho toàn bộ request.
 */
function db()
{
    static $conn = null;

    if ($conn instanceof PgSql\Connection) {
        return $conn;
    }

    $connString = sprintf(
        'host=%s port=%s dbname=%s user=%s password=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_USER,
        DB_PASS
    );

    $conn = pg_connect($connString);

    if (!$conn) {
        error_log('Không thể kết nối database: ' . pg_last_error());
        http_response_code(500);
        exit('Hệ thống đang bảo trì, vui lòng quay lại sau.');
    }

    pg_set_client_encoding($conn, 'UTF8');

    return $conn;
}

/**
 * Đảm bảo bảng dat_truoc_xe tồn tại để xử lý đặt trước.
 */