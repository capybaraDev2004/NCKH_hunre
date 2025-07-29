<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tổng Số Tiết Học</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        h2 {
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }

        th {
            background-color: #f2f2f2;
        }

        .total {
            font-size: 1.2em;
            font-weight: bold;
            margin-top: 20px;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #007bff;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Tổng Số Tiết Học</h2>
        <?php
        require '../../connection/connection.php';

        session_start();
        if (!isset($_SESSION['employeeID'])) {
            header("Location: ../../login/login.php");
            exit();
        }

        $employeeID = $_SESSION['employeeID'];
        $fullName = $_SESSION['fullName'];

        // Lấy tất cả các môn học của giảng viên
        $stmt = $conn->prepare("SELECT subject, total_sessions, total FROM create_schedules WHERE employeeID = :employeeID");
        $stmt->execute([':employeeID' => $employeeID]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Lấy tổng số tiết từ cột total (chỉ cần lấy từ bản ghi đầu tiên vì tất cả bản ghi của giảng viên có cùng giá trị total)
        $totalSessions = !empty($schedules) ? $schedules[0]['total'] : 0;
        ?>

        <p><strong>Giảng viên:</strong> <?php echo htmlspecialchars($fullName); ?></p>

        <?php if (empty($schedules)): ?>
            <p>Chưa có môn học nào trong thời khóa biểu.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Môn Học</th>
                        <th>Số Tiết</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $schedule): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($schedule['subject']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['total_sessions']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="total">Tổng số tiết: <?php echo $totalSessions; ?></p>
        <?php endif; ?>

        <a href="create_schedule.php" class="back-link">Quay lại Thời Khóa Biểu</a>
    </div>
</body>

</html>