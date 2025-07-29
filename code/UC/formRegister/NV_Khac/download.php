<?php
// Bắt đầu session để kiểm tra đăng nhập
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login.php");
    exit();
}

$employeeID = $_SESSION['employeeID'];

// Lấy tham số folder và file từ URL
$folder = isset($_GET['folder']) ? trim($_GET['folder']) : '';
$file = isset($_GET['file']) ? trim($_GET['file']) : '';

// Kiểm tra tham số
if (empty($folder) || empty($file)) {
    http_response_code(400);
    echo "Lỗi: Thiếu tham số folder hoặc file.";
    exit();
}

// Chỉ cho phép các thư mục hợp lệ
$allowedFolders = ['NV_Khac_A', 'NV_Khac_B'];
if (!in_array($folder, $allowedFolders)) {
    http_response_code(403);
    echo "Lỗi: Thư mục không được phép.";
    exit();
}

// Xác định đường dẫn tới file minh chứng
$baseDir = __DIR__ . "/../uploads/";
$filePath = $baseDir . $folder . "/" . $file;

// Debug: Ghi log đường dẫn file
error_log("Download File Path: $filePath");

// Kiểm tra xem file có tồn tại không
if (!file_exists($filePath)) {
    http_response_code(404);
    echo "Lỗi: File không tồn tại.";
    exit();
}

// Kiểm tra quyền đọc file
if (!is_readable($filePath)) {
    http_response_code(403);
    echo "Lỗi: Không có quyền đọc file.";
    exit();
}

// Kiểm tra xem file có thuộc về employeeID không
require_once __DIR__ . '/../../../connection/connection.php';
if (!isset($conn)) {
    http_response_code(500);
    echo "Lỗi: Không thể kết nối cơ sở dữ liệu.";
    exit();
}

try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM task_registrations WHERE employee_id = ? AND evidence_path = ? AND section IN ('a', 'b1', 'b2_3', 'b456')");
    $stmt->execute([$employeeID, $file]);
    $fileCount = $stmt->fetchColumn();
    
    if ($fileCount == 0) {
        http_response_code(403);
        echo "Lỗi: Bạn không có quyền truy cập file này.";
        exit();
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo "Lỗi: Không thể kiểm tra quyền truy cập file.";
    exit();
}

// Xác định loại MIME của file
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

// Danh sách các loại MIME của hình ảnh
$imageMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];

// Kiểm tra xem file có phải là hình ảnh không
$isImage = in_array($mimeType, $imageMimeTypes);

// Thiết lập header
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Nếu là hình ảnh, hiển thị trực tiếp; nếu không, tải xuống
if ($isImage) {
    header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
} else {
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
}

// Đọc và xuất nội dung file
readfile($filePath);
exit();