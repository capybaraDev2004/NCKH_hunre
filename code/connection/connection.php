<?php
// Kết nối cơ sở dữ liệu bằng PDO
try {
    $servername = "localhost:3306"; // Thay đổi port nếu cần
    $username_db = "root";          // Thay bằng username của bạn
    $password_db = "";              // Thay bằng password của bạn
    $dbname = "qlgv";               // Thay bằng tên database của bạn

    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Tăng cường an toàn
} catch (PDOException $e) {
    die("Kết nối thất bại: " . $e->getMessage());
}
?>