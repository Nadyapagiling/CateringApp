<?php
// Kredensial disimpan di luar repo (repo public), lihat /etc/catering/db.php
require '/etc/catering/db.php';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_errno) {
    error_log('DB connection failed: ' . $conn->connect_error);
    http_response_code(500);
    die('Terjadi kesalahan sistem. Silakan coba lagi.');
}
$conn->set_charset('utf8mb4');
