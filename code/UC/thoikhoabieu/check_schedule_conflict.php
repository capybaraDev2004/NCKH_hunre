<?php
require '../../connection/connection.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Nhận dữ liệu JSON từ request
$data = json_decode(file_get_contents('php://input'), true);
$employeeID = $_SESSION['employeeID'];

try {
    // Kiểm tra trùng lịch
    $stmt = $conn->prepare("
        SELECT cs.* 
        FROM create_schedules cs
        WHERE cs.employeeID = :employeeID 
        AND cs.weekday = :weekday
        AND cs.sessions LIKE :sessions
        AND (
            (:start_date BETWEEN cs.start_date AND cs.end_date)
            OR
            (:end_date BETWEEN cs.start_date AND cs.end_date)
        )
    ");

    $stmt->execute([
        ':employeeID' => $employeeID,
        ':weekday' => $data['weekday'],
        ':sessions' => '%' . $data['sessions'] . '%',
        ':start_date' => $data['start_date'],
        ':end_date' => $data['end_date']
    ]);

    $conflict = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($conflict) {
        // Nếu có trùng lịch, trả về thông tin chi tiết
        echo json_encode([
            'hasConflict' => true,
            'conflictDetails' => [
                'subject' => $conflict['subject'],
                'time' => $conflict['sessions'],
                'room' => $conflict['room']
            ]
        ]);
    } else {
        echo json_encode(['hasConflict' => false]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'hasConflict' => false,
        'error' => $e->getMessage()
    ]);
} 