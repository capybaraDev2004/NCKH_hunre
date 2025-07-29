<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo Thời Khóa Biểu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/style_create_schedule.css">
    <link rel="stylesheet" href="../../assets/css/style_schedule.css">
    <link rel="stylesheet" href="../../assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="../../assets/js/create.js"></script>
</head>

<body>
    <?php
    require '../../connection/connection.php';

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['employeeID'])) {
        header("Location: ../../login/login.php");
        exit();
    }

    $employeeID = $_SESSION['employeeID'];
    $fullName = $_SESSION['fullName'];
    $role = $_SESSION['role'];

    if (isset($_GET['selected_date'])) {
        $selectedDateStr = $_GET['selected_date'];
        $_SESSION['selected_date'] = $selectedDateStr;
    } elseif (isset($_SESSION['selected_date'])) {
        $selectedDateStr = $_SESSION['selected_date'];
    } else {
        $selectedDateStr = date('Y-m-d');
    }
    $selectedDate = new DateTime($selectedDateStr);

    $dayOfWeek = (int)$selectedDate->format('N');
    $startOfWeek = clone $selectedDate;
    $startOfWeek->modify('-' . ($dayOfWeek - 1) . ' days');
    $endOfWeek = clone $startOfWeek;
    $endOfWeek->modify('+6 days');

    $stmt = $conn->prepare("SELECT id, subject, start_date, end_date, weekday, sessions, room FROM create_schedules WHERE employeeID = :employeeID");
    $stmt->execute([':employeeID' => $employeeID]);
    $scheduleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $scheduleGrid = [];
    $timeSlots = [
        "07:00-07:50" => 0,
        "07:55-08:45" => 1,
        "08:50-09:40" => 2,
        "09:50-10:40" => 3,
        "10:45-11:35" => 4,
        "12:30-13:20" => 5,
        "13:25-14:15" => 6,
        "14:20-15:10" => 7,
        "15:20-16:10" => 8,
        "16:15-17:05" => 9,
        "17:30-18:20" => 10,
        "18:25-19:15" => 11,
        "19:20-20:10" => 12,
        "20:15-21:05" => 13
    ];
    $sessionMap = [
        "Tiết 1 (07:00 - 07:50)" => "07:00-07:50",
        "Tiết 2 (07:55 - 08:45)" => "07:55-08:45",
        "Tiết 3 (08:50 - 09:40)" => "08:50-09:40",
        "Tiết 4 (09:50 - 10:40)" => "09:50-10:40",
        "Tiết 5 (10:45 - 11:35)" => "10:45-11:35",
        "Tiết 6 (12:30 - 13:20)" => "12:30-13:20",
        "Tiết 7 (13:25 - 14:15)" => "13:25-14:15",
        "Tiết 8 (14:20 - 15:10)" => "14:20-15:10",
        "Tiết 9 (15:20 - 16:10)" => "15:20-16:10",
        "Tiết 10 (16:15 - 17:05)" => "16:15-17:05",
        "Tiết 11 (17:30 - 18:20)" => "17:30-18:20",
        "Tiết 12 (18:25 - 19:15)" => "18:25-19:15",
        "Tiết 13 (19:20 - 20:10)" => "19:20-20:10",
        "Tiết 14 (20:15 - 21:05)" => "20:15-21:05"
    ];

    $holidays = isset($_POST['holidays']) ? json_decode($_POST['holidays'], true) : [];
    if (!is_array($holidays)) {
        $holidays = [];
    }

    if (!empty($scheduleData)) {
        foreach ($scheduleData as $row) {
            $subject = $row['subject'];
            $weekday = $row['weekday'];
            $sessions = explode(', ', $row['sessions']);
            $room = $row['room'];
            $startDate = new DateTime($row['start_date']);
            $endDate = new DateTime($row['end_date']);
            $scheduleId = $row['id'];

            $dayIndex = (int)$weekday - 2;
            if ($dayIndex < 0) $dayIndex = 6;

            $targetDate = clone $startOfWeek;
            $targetDate->modify('+' . $dayIndex . ' days');
            $targetDateStr = $targetDate->format('Y-m-d');

            if (in_array($targetDateStr, $holidays)) {
                continue;
            }

            if ($targetDate >= $startDate && $targetDate <= $endDate) {
                foreach ($sessions as $session) {
                    if (isset($sessionMap[$session])) {
                        $timeSlot = $sessionMap[$session];
                        $index = $timeSlots[$timeSlot];
                        $scheduleGrid[$index][$dayIndex] = [
                            'content' => "$subject - ($room)",
                            'subject' => $subject,
                            'room' => $room,
                            'time' => $timeSlot,
                            'session' => $session,
                            'scheduleId' => $scheduleId,
                            'startDate' => $row['start_date'], // Thêm start_date
                            'endDate' => $row['end_date'] // Thêm end_date
                        ];
                    }
                }
            }
        }
    }
    ?>

    <header class="header">
        <nav class="nav">
            <ul class="nav-list">
                <li class="nav-item"><a href="../../trangchu.php" class="nav-link">Trang chủ</a></li>
                <li class="nav-item"><a href="../formRegister/formRegister.php" class="nav-link">Đăng ký giảng dạy</a></li>
                <li class="nav-item"><a href="../thoikhoabieu/thoikhoabieu.php" class="nav-link">Thời khóa biểu</a></li>
                <li class="nav-item"><a href="#" class="nav-link">Báo cáo</a></li>
            </ul>
            <div class="account-settings">
                <span class="account-icon">👤</span>
                <span class="account-name"><?php echo htmlspecialchars($fullName); ?></span>
                <div class="account-menu">
                    <a href="../../login/logout.php" class="account-menu-item">Đăng xuất</a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="schedule">
            <h2>Lịch cá nhân</h2>
            <div>
                <label>Giảng viên:</label>
                <input type="text" id="teacherName" value="<?php echo htmlspecialchars($fullName); ?>" readonly>
            </div>
            <table id="scheduleTable">
                <thead>
                    <tr>
                        <th>Thời gian</th>
                        <th class="weekday-cell">Thứ 2</th>
                        <th class="weekday-cell">Thứ 3</th>
                        <th class="weekday-cell">Thứ 4</th>
                        <th class="weekday-cell">Thứ 5</th>
                        <th class="weekday-cell">Thứ 6</th>
                        <th class="weekday-cell">Thứ 7</th>
                        <th class="weekday-cell">Chủ Nhật</th>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="date-cell"></th>
                        <th class="date-cell"></th>
                        <th class="date-cell"></th>
                        <th class="date-cell"></th>
                        <th class="date-cell"></th>
                        <th class="date-cell"></th>
                        <th class="date-cell"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $displayTimeSlots = [
                        "07:00 - 07:50",
                        "07:55 - 08:45",
                        "08:50 - 09:40",
                        "09:50 - 10:40",
                        "10:45 - 11:35",
                        "12:30 - 13:20",
                        "13:25 - 14:15",
                        "14:20 - 15:10",
                        "15:20 - 16:10",
                        "16:15 - 17:05",
                        "17:30 - 18:20",
                        "18:25 - 19:15",
                        "19:20 - 20:10",
                        "20:15 - 21:05"
                    ];

                    foreach ($displayTimeSlots as $index => $slot) {
                        echo "<tr>";
                        echo "<td>$slot</td>";
                        for ($day = 0; $day < 7; $day++) {
                            if (isset($scheduleGrid[$index][$day])) {
                                $cellData = $scheduleGrid[$index][$day];
                                $cellContent = htmlspecialchars($cellData['content']);
                                $scheduleId = $cellData['scheduleId'];
                                $startDate = htmlspecialchars($cellData['startDate']);
                                $endDate = htmlspecialchars($cellData['endDate']);
                                echo "<td class='subject' data-schedule-id='$scheduleId' data-subject='{$cellData['subject']}' data-room='{$cellData['room']}' data-time='{$cellData['time']}' data-session='{$cellData['session']}' data-start-date='$startDate' data-end-date='$endDate'>$cellContent</td>";
                            } else {
                                echo "<td class='empty'></td>";
                            }
                        }
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="sidebar">
            <h3 id="currentMonthYear"></h3>
            <div class="calendar-box">
                <button id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                <button id="nextMonth"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="calendar-days">
                <table>
                    <thead>
                        <tr>
                            <th>T2</th>
                            <th>T3</th>
                            <th>T4</th>
                            <th>T5</th>
                            <th>T6</th>
                            <th>T7</th>
                            <th>CN</th>
                        </tr>
                    </thead>
                    <tbody id="calendarTable"></tbody>
                </table>
            </div>

            <div class="form-add-schedule">
                <h3>Thêm thời khóa biểu</h3>
                <div>
                    <label for="searchSubject">Môn học:</label>
                    <input type="text" id="searchSubject" placeholder="Nhập 3 chữ cái đầu">
                    <ul id="subjectList"></ul>
                </div>
                <div>
                    <label for="sessionSelect">Tiết học:</label>
                    <select id="sessionSelect" multiple></select>
                    <p>Nhấn giữ `Ctrl` để chọn nhiều tiết</p>
                </div>
                <div>
                    <label for="daySelect">Chọn thứ:</label>
                    <select id="daySelect">
                        <option value="">Chọn thứ</option>
                    </select>
                </div>
                <div>
                    <label for="roomSelect">Chọn phòng học:</label>
                    <select id="roomSelect">
                        <option value="">Chọn phòng</option>
                    </select>
                </div>
                <div>
                    <label for="startDate">Ngày bắt đầu:</label>
                    <input type="date" id="startDate">
                </div>
                <button id="addSchedule">Thêm vào TKB</button>
            </div>
        </div>
    </div>

    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <span class="close">×</span>
            <h2>Thông tin môn học</h2>
            <p><strong>Tên môn học:</strong> <span id="modalSubject"></span></p>
            <p><strong>Thời gian học:</strong> <span id="modalTime"></span></p>
            <p><strong>Tiết học:</strong> <span id="modalSession"></span></p>
            <p><strong>Phòng học:</strong> <span id="modalRoom"></span></p>
            <a href="total_sessions.php"><button>Xem Tổng Số Tiết</button></a>
            <button class="delete-btn" id="deleteScheduleBtn">Xóa môn học</button>

        </div>
    </div>
    <script>
        window.scheduleGrid = <?php echo json_encode($scheduleGrid); ?>;
        window.role = "<?php echo htmlspecialchars($role); ?>";
        window.holidays = <?php echo json_encode($holidays); ?>;
    </script>
</body>
</html>