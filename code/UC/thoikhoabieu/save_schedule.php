<?php
require '../../connection/connection.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $employeeID = $_SESSION['employeeID'];
    $overwrite = isset($data['overwrite']) ? $data['overwrite'] : false;

    if ($overwrite) {
        // Tìm lịch học cũ bị trùng
        $stmt = $conn->prepare("
            SELECT * FROM create_schedules 
            WHERE employeeID = :employeeID 
            AND weekday = :weekday 
            AND sessions LIKE :sessions
            AND end_date >= :start_date
        ");
        
        $stmt->execute([
            ':employeeID' => $employeeID,
            ':weekday' => $data['weekday'],
            ':sessions' => '%' . $data['sessions'] . '%',
            ':start_date' => $data['start_date']
        ]);

        $existingSchedule = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingSchedule) {
            // Cập nhật ngày kết thúc của lịch cũ đến trước ngày bắt đầu lịch mới
            $newEndDate = date('Y-m-d', strtotime($data['start_date'] . ' -1 day'));
            
            if ($existingSchedule['start_date'] < $data['start_date']) {
                $stmt = $conn->prepare("
                    UPDATE create_schedules 
                    SET end_date = :new_end_date
                    WHERE id = :id
                ");
                
                $stmt->execute([
                    ':new_end_date' => $newEndDate,
                    ':id' => $existingSchedule['id']
                ]);
            }
        }
    }

    // Thêm lịch mới
    $stmt = $conn->prepare("
        INSERT INTO create_schedules 
        (employeeID, subject, start_date, end_date, weekday, sessions, room)
        VALUES 
        (:employeeID, :subject, :start_date, :end_date, :weekday, :sessions, :room)
    ");

    $stmt->execute([
        ':employeeID' => $employeeID,
        ':subject' => $data['subject'],
        ':start_date' => $data['start_date'],
        ':end_date' => $data['end_date'],
        ':weekday' => $data['weekday'],
        ':sessions' => $data['sessions'],
        ':room' => $data['room']
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
