<?php
session_start(); // Đảm bảo session được khởi tạo
require_once __DIR__ . '/../../../connection/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || !isset($_POST['id']) || !isset($_SESSION['employeeID'])) {
    error_log("Request invalid: Method=" . $_SERVER['REQUEST_METHOD'] . ", Action=" . (isset($_POST['action']) ? $_POST['action'] : 'not set') . ", ID=" . (isset($_POST['id']) ? $_POST['id'] : 'not set') . ", Session=" . (isset($_SESSION['employeeID']) ? $_SESSION['employeeID'] : 'not set'));
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ']);
    exit;
}

$action = $_POST['action'];
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$employeeID = $_SESSION['employeeID'];

$table_map = [
    'delete_nckh' => 'nckhcc_history',
    'delete_bai_bao' => 'bai_bao_history',
    'delete_huong_dan' => 'huongdansv_history',
    'delete_sach' => 'vietsach_history'
];

if (!isset($table_map[$action])) {
    echo json_encode(['success' => false, 'message' => 'Hành động không được hỗ trợ']);
    exit;
}

$table = $table_map[$action];

if ($id === false || $id === null) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit;
}

try {
    // Lấy thông tin teacherID và fullName từ bảng employee theo employeeID phiên hiện tại
    $stmtEmp = $conn->prepare("SELECT teacherID, fullName FROM employee WHERE employeeID = :employeeID");
    $stmtEmp->execute([':employeeID' => $employeeID]);
    $emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);

    if (!$emp) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy thông tin giảng viên.']);
        exit;
    }

    $teacherID = trim($emp['teacherID']);
    $fullName = trim($emp['fullName']);

    // Lấy thông tin bản ghi theo id
    $stmt = $conn->prepare("SELECT thanh_vien_chu_nhom, ten_san_pham FROM $table WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bản ghi để xóa']);
        exit;
    }

    $chu_nhom = $row['thanh_vien_chu_nhom'];
    $ten_san_pham = $row['ten_san_pham'];

    // Tách teacherID và fullName từ chuỗi
    preg_match('/^(.+?)\s*-\s*(.+)$/', $chu_nhom, $matches);
    $chu_nhom_teacherID = trim($matches[1] ?? '');
    $chu_nhom_fullName = trim($matches[2] ?? '');

    // So sánh không phân biệt hoa thường, loại bỏ khoảng trắng thừa
    if (
        mb_strtolower($teacherID) === mb_strtolower($chu_nhom_teacherID) &&
        mb_strtolower(trim(preg_replace('/\s+/', ' ', $fullName))) === mb_strtolower(trim(preg_replace('/\s+/', ' ', $chu_nhom_fullName)))
    ) {
        // Xóa tất cả các bản ghi có cùng thanh_vien_chu_nhom và ten_san_pham
        $stmt = $conn->prepare("DELETE FROM $table WHERE thanh_vien_chu_nhom = :chu_nhom AND ten_san_pham = :ten_san_pham");
        $stmt->execute([':chu_nhom' => $chu_nhom, ':ten_san_pham' => $ten_san_pham]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "Bạn không có quyền xóa, đề nghị liên hệ người tạo ($chu_nhom) để được hỗ trợ."
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
exit;
?>