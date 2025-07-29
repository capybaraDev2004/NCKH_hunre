<?php
require '../../connection/connection.php';

session_start();
if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login/login.php");
    exit();
}

$employeeID = $_SESSION['employeeID'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit();
}

$scheduleId = $_POST['scheduleId'] ?? '';

if (empty($scheduleId)) {
    echo json_encode(['success' => false, 'message' => 'ID lịch học không hợp lệ']);
    exit();
}

try {
    // Bắt đầu transaction
    $conn->beginTransaction();

    // Xóa môn học
    $stmt = $conn->prepare("DELETE FROM create_schedules WHERE id = :id AND employeeID = :employeeID");
    $stmt->execute([':id' => $scheduleId, ':employeeID' => $employeeID]);

    if ($stmt->rowCount() === 0) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy lịch học để xóa']);
        exit();
    }

    // Cập nhật cột total cho tất cả các bản ghi của giảng viên
    $stmt = $conn->prepare("
        UPDATE create_schedules
        SET total = (
            SELECT COALESCE(SUM(total_sessions), 0)
            FROM create_schedules
            WHERE employeeID = :employeeID
        )
        WHERE employeeID = :employeeID
    ");
    $stmt->execute([':employeeID' => $employeeID]);

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Xóa môn học thành công']);
} catch (PDOException $e) {
    // Rollback nếu có lỗi
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
