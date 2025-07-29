<?php
if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login/login.php");
    exit();
}

// Kết nối CSDL sử dụng PDO
$host = "localhost:3306";
$username = "root";
$password = "";
$dbname = "qlgv";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Kết nối thất bại: " . $e->getMessage());
}

// Lấy năm từ GET hoặc POST, mặc định là năm hiện tại
$selected_year = $_POST['year'] ?? $_GET['year'] ?? date('Y');
$employee_id = $_SESSION['employeeID'];

// Lấy chức danh nghề nghiệp và tính tổng số giờ NV3
$academicTitle = 'Giảng viên'; // Giá trị mặc định
$stmt = $conn->prepare("SELECT academicTitle FROM employee WHERE employeeID = ?");
$stmt->execute([$employee_id]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $academicTitle = $row['academicTitle'];
}

$total_nv3_hours = 180; // Giá trị mặc định cho Giảng viên
if ($academicTitle === 'Giảng viên (tập sự)') {
    $total_nv3_hours = 1265;
} elseif ($academicTitle === 'Trợ giảng') {
    $total_nv3_hours = 675;
} elseif ($academicTitle === 'Trợ giảng (tập sự)') {
    $total_nv3_hours = 1340;
}

$stmt = $conn->prepare("SELECT total_nv3_hours FROM nv3_hours WHERE employee_id = ?");
$stmt->execute([$employee_id]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $total_nv3_hours = $row['total_nv3_hours'];
}

// Hàm lấy dữ liệu đã đăng ký
function fetchRegisteredTasks($conn, $employee_id, $selected_year) {
    $registered_tasks = [];
    $stmt = $conn->prepare("SELECT task_id, quantity, total_hours, evidence_path 
                           FROM task_registrations 
                           WHERE employee_id = ? AND year = ? AND section = 'a'");
    $stmt->execute([$employee_id, $selected_year]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $registered_tasks[$row['task_id']] = [
            'quantity' => $row['quantity'],
            'total_hours' => $row['total_hours'],
            'evidence_path' => $row['evidence_path']
        ];
    }
    return $registered_tasks;
}

// Lấy dữ liệu đã đăng ký ban đầu
$registered_tasks = fetchRegisteredTasks($conn, $employee_id, $selected_year);

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Xóa toàn bộ dữ liệu cũ trước khi lưu mới
    $delete_sql = "DELETE FROM task_registrations WHERE employee_id = ? AND year = ? AND section = 'a'";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->execute([$employee_id, $selected_year]);

    $selected_tasks = $_POST['selected_tasks'] ?? [];
    
    // Thêm dữ liệu mới
    foreach ($selected_tasks as $original_task_id) {
        $task_id = trim($original_task_id);
        $normalized_task_id = str_replace('.', '_', $task_id);
        
        $quantity_field = "quantity_" . $normalized_task_id;
        $total_hours_field = "total_hours_" . $normalized_task_id;
        
        $quantity = (int)($_POST[$quantity_field] ?? 0);
        $total_hours = (float)($_POST[$total_hours_field] ?? 0);

        if ($quantity > 0 && $total_hours > 0) {
            $evidence_path = $registered_tasks[$task_id]['evidence_path'] ?? null;
            $sql = "INSERT INTO task_registrations 
                    (employee_id, task_id, quantity, total_hours, section, year, evidence_path) 
                    VALUES (?, ?, ?, ?, 'a', ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$employee_id, $task_id, $quantity, $total_hours, $selected_year, $evidence_path]);
        }
    }

    // Xử lý upload file minh chứng
    if (isset($_FILES['evidence']) && is_array($_FILES['evidence']['name'])) {
        $uploadDir = 'uploads/NV_Khac_A/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['evidence']['name'] as $original_task_id => $fileName) {
            if ($_FILES['evidence']['error'][$original_task_id] === UPLOAD_ERR_OK && !empty($fileName)) {
                $task_id = trim($original_task_id);
                $normalized_task_id = str_replace('.', '_', $task_id);
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (in_array($fileExt, ['pdf', 'doc', 'docx', 'jpg', 'png'])) {
                    $newFileName = "{$employee_id}_{$normalized_task_id}_{$selected_year}_" . time() . ".{$fileExt}";
                    $destination = $uploadDir . $newFileName;

                    if (move_uploaded_file($_FILES['evidence']['tmp_name'][$original_task_id], $destination)) {
                        $update_sql = "UPDATE task_registrations 
                                      SET evidence_path = ? 
                                      WHERE employee_id = ? AND task_id = ? AND year = ? AND section = 'a'";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->execute([$newFileName, $employee_id, $task_id, $selected_year]);
                    }
                }
            }
        }
    }

    // Cập nhật tổng số giờ hoàn thành theo năm
    try {
        $sql = "SELECT SUM(total_hours) as total FROM task_registrations WHERE employee_id = ? AND year = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id, $selected_year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = $result['total'] ?? 0;

        $check_sql = "SELECT id FROM total_hours WHERE employee_id = ? AND year = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$employee_id, $selected_year]);
        
        if ($check_stmt->rowCount() > 0) {
            $update_sql = "UPDATE total_hours SET total_completed_hours = ? WHERE employee_id = ? AND year = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([$total, $employee_id, $selected_year]);
        } else {
            $insert_sql = "INSERT INTO total_hours (employee_id, total_completed_hours, year) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->execute([$employee_id, $total, $selected_year]);
        }

        $message = "Dữ liệu đã được lưu thành công cho năm $selected_year!";
    } catch (PDOException $e) {
        error_log("Error updating total hours: " . $e->getMessage());
        $message = "Có lỗi xảy ra khi cập nhật tổng số giờ!";
    }

    // Làm mới dữ liệu đã đăng ký sau khi lưu
    $registered_tasks = fetchRegisteredTasks($conn, $employee_id, $selected_year);
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký nhiệm vụ khác - Tham gia các hội đồng, công tác đoàn thể</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/Style_NV_Khac.css">
    <style>
    .year-selector {
        margin-bottom: 20px;
        text-align: center;
    }

    .year-selector label {
        margin-right: 10px;
        font-weight: bold;
    }

    .year-selector select {
        padding: 5px;
        font-size: 16px;
    }

    .dialog-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    .dialog-box {
        background: white;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
        max-width: 400px;
        width: 90%;
    }

    .dialog-box p {
        margin: 0 0 20px;
        font-size: 16px;
    }

    .dialog-box button {
        padding: 10px 20px;
        background: #4CAF50;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    }

    .dialog-box button:hover {
        background: #45a049;
    }

    .nv_a>a {
        color: #f8843d;
    }

    /* Custom file input styling */
    .evidence-cell {
        position: relative;
    }

    .evidence-input {
        opacity: 0;
        width: 0.1px;
        height: 0.1px;
        position: absolute;
    }

    .evidence-cell label {
        display: inline-block;
        padding: 8px 12px;
        background-color: #4CAF50;
        color: white;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s ease;
        margin-bottom: 5px;
    }

    .evidence-cell label:hover {
        background-color: #45a049;
    }

    .evidence-cell label i {
        margin-right: 5px;
    }

    /* Evidence link styling */
    .evidence-link {
        display: inline-block;
        padding: 6px 10px;
        background-color: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 4px;
        color: #007bff;
        text-decoration: none;
        font-size: 14px;
        transition: all 0.3s ease;
        margin-top: 5px;
    }

    .evidence-link:hover {
        background-color: #e9ecef;
        color: #0056b3;
    }

    .evidence-link i {
        margin-right: 5px;
    }

    /* File name display */
    .file-name {
        display: block;
        font-size: 12px;
        color: #666;
        margin-top: 4px;
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .main-content{
        max-width: 600px
        display: flex;  
        margin: 100px;
        margin-top: 0;
    }
    </style>
</head>

<body>
    <div class="main-content">
        <div class="research-form">
            <h2 class="form-title">Đăng ký nhiệm vụ khác - Tham gia các hội đồng, công tác đoàn thể</h2>
            <div class="year-selector">
                <label for="year">Năm học được điều chỉnh:</label>
                <select name="year" id="year">
                    <?php
                    $current_year = date('Y');
                    for ($y = 2020; $y <= $current_year + 1; $y++) {
                        $selected = ($y == $selected_year) ? 'selected' : '';
                        echo "<option value='$y' $selected>$y</option>";
                    }
                    ?>
                </select>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                <table class="teaching-registration-table">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Nội dung công việc</th>
                            <th>Tham gia</th>
                            <th>Số lượng</th>
                            <th>Giờ làm việc</th>
                            <th>Thống kê số giờ</th>
                            <th>Minh chứng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="sub-title">
                            <td colspan="7">A. Tham gia các hội đồng, công tác đoàn thể chưa được giảm trừ khối lượng
                                hoặc chưa được hưởng phụ cấp (chỉ tính với các CBVC hưởng ngạch giảng viên)</td>
                        </tr>
                        <!-- Nhóm 1: Hội đồng Khoa học và Đào tạo -->
                        <tr class="task-group" data-group="group-1">
                            <td>1</td>
                            <td>Hội đồng Khoa học và Đào tạo (tính khoản cho cả năm)</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-1"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_1 = [
                            '1_chu_tich_thu_ky_hoi_dong_khoa_hoc_dao_tao' => ['- Chủ tịch, thư ký Hội đồng', 'Hoàn thành 15% khối lượng NV3'],
                            '1_uy_vien_hoi_dong_khoa_hoc_dao_tao' => ['- Ủy viên Hội đồng', 'Hoàn thành 8% khối lượng NV3']
                        ];
                        foreach ($tasks_1 as $id => $details):
                            $task_id = str_replace('.', '_', $id);
                        ?>
                        <tr class="sub-task" data-group="group-1">
                            <td></td>
                            <td><?php echo $details[0]; ?></td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="selected_tasks[]" value="<?php echo $id; ?>"
                                    class="sub-task-checkbox"
                                    <?php echo isset($registered_tasks[$id]) ? 'checked' : ''; ?>>
                            </td>
                            <td><input type="number" name="quantity_<?php echo $task_id; ?>" min="0"
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['quantity'] : '0'; ?>"
                                    class="quantity-input" style="width: 60px;"></td>
                            <td class="hours faded"><?php echo $details[1]; ?></td>
                            <td class="total-hours faded">
                                <?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>
                            </td>
                            <td class="evidence-cell">
                                <input type="file" name="evidence[<?php echo $id; ?>]" id="evidence_<?php echo $task_id; ?>" 
                                    class="evidence-input" accept=".pdf,.doc,.docx,.jpg,.png">
                                <label for="evidence_<?php echo $task_id; ?>">
                                    <i class="fas fa-upload"></i> Chọn file
                                </label>
                                <span class="file-name" id="fileName_<?php echo $task_id; ?>"></span>
                                <input type="hidden" name="total_hours_<?php echo $task_id; ?>" 
                                    class="total-hours-input" 
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>">
                                <?php if (isset($registered_tasks[$id]['evidence_path'])): ?>
                                    <a href="uploads/NV_Khac_A/<?php echo $registered_tasks[$id]['evidence_path']; ?>" 
                                       class="evidence-link" target="_blank">
                                        <i class="fas fa-file-alt"></i> Xem minh chứng
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Nhóm 2: Hội đồng Khoa -->
                        <tr class="task-group" data-group="group-2">
                            <td>2</td>
                            <td>Hội đồng Khoa</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-2"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_2 = [
                            '2_chu_tich_thu_ky_hoi_dong_khoa' => ['- Chủ tịch, thư ký Hội đồng', 'Hoàn thành 12% khối lượng NV3'],
                            '2_uy_vien_hoi_dong_khoa' => ['- Ủy viên Hội đồng', 'Hoàn thành 8% khối lượng NV3']
                        ];
                        foreach ($tasks_2 as $id => $details):
                            $task_id = str_replace('.', '_', $id);
                        ?>
                        <tr class="sub-task" data-group="group-2">
                            <td></td>
                            <td><?php echo $details[0]; ?></td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="selected_tasks[]" value="<?php echo $id; ?>"
                                    class="sub-task-checkbox"
                                    <?php echo isset($registered_tasks[$id]) ? 'checked' : ''; ?>>
                            </td>
                            <td><input type="number" name="quantity_<?php echo $task_id; ?>" min="0"
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['quantity'] : '0'; ?>"
                                    class="quantity-input" style="width: 60px;"></td>
                            <td class="hours faded"><?php echo $details[1]; ?></td>
                            <td class="total-hours faded">
                                <?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>
                            </td>
                            <td class="evidence-cell">
                                <input type="file" name="evidence[<?php echo $id; ?>]" id="evidence_<?php echo $task_id; ?>" 
                                    class="evidence-input" accept=".pdf,.doc,.docx,.jpg,.png">
                                <label for="evidence_<?php echo $task_id; ?>">
                                    <i class="fas fa-upload"></i> Chọn file
                                </label>
                                <span class="file-name" id="fileName_<?php echo $task_id; ?>"></span>
                                <input type="hidden" name="total_hours_<?php echo $task_id; ?>" 
                                    class="total-hours-input" 
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>">
                                <?php if (isset($registered_tasks[$id]['evidence_path'])): ?>
                                    <a href="uploads/NV_Khac_A/<?php echo $registered_tasks[$id]['evidence_path']; ?>" 
                                       class="evidence-link" target="_blank">
                                        <i class="fas fa-file-alt"></i> Xem minh chứng
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Nhóm 3: Hội đồng Đảm bảo chất lượng trường -->
                        <tr class="task-group" data-group="group-3">
                            <td>3</td>
                            <td>Hội đồng Đảm bảo chất lượng trường</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-3"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_3 = [
                            '3_chu_tich_thu_ky_hoi_dong_dam_bao_chat_luong' => ['- Chủ tịch, thư ký Hội đồng', 'Hoàn thành 10% khối lượng NV3'],
                            '3_uy_vien_hoi_dong_dam_bao_chat_luong' => ['- Ủy viên Hội đồng', 'Hoàn thành 5% khối lượng NV3']
                        ];
                        foreach ($tasks_3 as $id => $details):
                            $task_id = str_replace('.', '_', $id);
                        ?>
                        <tr class="sub-task" data-group="group-3">
                            <td></td>
                            <td><?php echo $details[0]; ?></td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="selected_tasks[]" value="<?php echo $id; ?>"
                                    class="sub-task-checkbox"
                                    <?php echo isset($registered_tasks[$id]) ? 'checked' : ''; ?>>
                            </td>
                            <td><input type="number" name="quantity_<?php echo $task_id; ?>" min="0"
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['quantity'] : '0'; ?>"
                                    class="quantity-input" style="width: 60px;"></td>
                            <td class="hours faded"><?php echo $details[1]; ?></td>
                            <td class="total-hours faded">
                                <?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>
                            </td>
                            <td class="evidence-cell">
                                <input type="file" name="evidence[<?php echo $id; ?>]" id="evidence_<?php echo $task_id; ?>" 
                                    class="evidence-input" accept=".pdf,.doc,.docx,.jpg,.png">
                                <label for="evidence_<?php echo $task_id; ?>">
                                    <i class="fas fa-upload"></i> Chọn file
                                </label>
                                <span class="file-name" id="fileName_<?php echo $task_id; ?>"></span>
                                <input type="hidden" name="total_hours_<?php echo $task_id; ?>" 
                                    class="total-hours-input" 
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>">
                                <?php if (isset($registered_tasks[$id]['evidence_path'])): ?>
                                    <a href="uploads/NV_Khac_A/<?php echo $registered_tasks[$id]['evidence_path']; ?>" 
                                       class="evidence-link" target="_blank">
                                        <i class="fas fa-file-alt"></i> Xem minh chứng
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Nhóm 4: Hội đồng Đánh giá chương trình đào tạo -->
                        <tr class="task-group" data-group="group-4">
                            <td>4</td>
                            <td>Hội đồng Đánh giá chương trình đào tạo</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-4"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_4 = [
                            '4_chu_tich_pho_chu_tich_hoi_dong_danh_gia_ctdt' => ['- Chủ tịch, Phó Chủ tịch', 'Hoàn thành 30% khối lượng NV3'],
                            '4_thu_ky_hoi_dong_danh_gia_ctdt' => ['- Thư ký Hội đồng', 'Hoàn thành 50% khối lượng NV3'],
                            '4_uy_vien_hoi_dong_danh_gia_ctdt' => ['- Ủy viên Hội đồng', 'Hoàn thành 10% khối lượng NV3']
                        ];
                        foreach ($tasks_4 as $id => $details):
                            $task_id = str_replace('.', '_', $id);
                        ?>
                        <tr class="sub-task" data-group="group-4">
                            <td></td>
                            <td><?php echo $details[0]; ?></td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="selected_tasks[]" value="<?php echo $id; ?>"
                                    class="sub-task-checkbox"
                                    <?php echo isset($registered_tasks[$id]) ? 'checked' : ''; ?>>
                            </td>
                            <td><input type="number" name="quantity_<?php echo $task_id; ?>" min="0"
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['quantity'] : '0'; ?>"
                                    class="quantity-input" style="width: 60px;"></td>
                            <td class="hours faded"><?php echo $details[1]; ?></td>
                            <td class="total-hours faded">
                                <?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>
                            </td>
                            <td class="evidence-cell">
                                <input type="file" name="evidence[<?php echo $id; ?>]" id="evidence_<?php echo $task_id; ?>" 
                                    class="evidence-input" accept=".pdf,.doc,.docx,.jpg,.png">
                                <label for="evidence_<?php echo $task_id; ?>">
                                    <i class="fas fa-upload"></i> Chọn file
                                </label>
                                <span class="file-name" id="fileName_<?php echo $task_id; ?>"></span>
                                <input type="hidden" name="total_hours_<?php echo $task_id; ?>" 
                                    class="total-hours-input" 
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>">
                                <?php if (isset($registered_tasks[$id]['evidence_path'])): ?>
                                    <a href="uploads/NV_Khac_A/<?php echo $registered_tasks[$id]['evidence_path']; ?>" 
                                       class="evidence-link" target="_blank">
                                        <i class="fas fa-file-alt"></i> Xem minh chứng
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Nhóm 5: Tham gia công tác đảng, công đoàn, nữ công, hội cựu chiến binh, đoàn thanh niên -->
                        <tr class="task-group" data-group="group-5">
                            <td>5</td>
                            <td>Tham gia công tác đảng, công đoàn, nữ công, hội cựu chiến binh, đoàn thanh niên</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-5"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <!-- 5.1: Ủy ban kiểm tra Đảng ủy Trường -->
                        <tr data-group="group-5.1">
                            <td>5.1</td>
                            <td>Ủy ban kiểm tra Đảng ủy Trường</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-5.1"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_5_1 = [
                            '5.1_chu_nhiem_uy_ban_kiem_tra_dang_uy_truong' => ['- Chủ nhiệm', 'Hoàn thành 30% khối lượng NV3'],
                            '5.1_pho_chu_nhiem_uy_ban_kiem_tra_dang_uy_truong' => ['- Phó Chủ nhiệm', 'Hoàn thành 25% khối lượng NV3'],
                            '5.1_uy_vien_uy_ban_kiem_tra_dang_uy_truong' => ['- Ủy viên', 'Hoàn thành 20% khối lượng NV3']
                        ];
                        foreach ($tasks_5_1 as $id => $details):
                            $task_id = str_replace('.', '_', $id);
                        ?>
                        <tr class="sub-task" data-group="group-5.1">
                            <td></td>
                            <td><?php echo $details[0]; ?></td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="selected_tasks[]" value="<?php echo $id; ?>"
                                    class="sub-task-checkbox"
                                    <?php echo isset($registered_tasks[$id]) ? 'checked' : ''; ?>>
                            </td>
                            <td><input type="number" name="quantity_<?php echo $task_id; ?>" min="0"
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['quantity'] : '0'; ?>"
                                    class="quantity-input" style="width: 60px;"></td>
                            <td class="hours faded"><?php echo $details[1]; ?></td>
                            <td class="total-hours faded">
                                <?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>
                            </td>
                            <td class="evidence-cell">
                                <input type="file" name="evidence[<?php echo $id; ?>]" id="evidence_<?php echo $task_id; ?>" 
                                    class="evidence-input" accept=".pdf,.doc,.docx,.jpg,.png">
                                <label for="evidence_<?php echo $task_id; ?>">
                                    <i class="fas fa-upload"></i> Chọn file
                                </label>
                                <span class="file-name" id="fileName_<?php echo $task_id; ?>"></span>
                                <input type="hidden" name="total_hours_<?php echo $task_id; ?>" 
                                    class="total-hours-input" 
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>">
                                <?php if (isset($registered_tasks[$id]['evidence_path'])): ?>
                                    <a href="uploads/NV_Khac_A/<?php echo $registered_tasks[$id]['evidence_path']; ?>" 
                                       class="evidence-link" target="_blank">
                                        <i class="fas fa-file-alt"></i> Xem minh chứng
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- 5.2: Chi ủy viên Chi bộ trực thuộc -->
                        <tr data-group="group-5.2">
                            <td>5.2</td>
                            <td>Chi ủy viên Chi bộ trực thuộc</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-5.2"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_5_2 = [
                            '5.2_chi_bo_duoi_20_dang_vien' => ['- Chi bộ dưới 20 Đảng viên', 'Hoàn thành 3% khối lượng NV3'],
                            '5.2_chi_bo_20_dang_vien_tro_len' => ['- Chi bộ từ 20 Đảng viên trở lên', 'Hoàn thành 5% khối lượng NV3'],
                            '5.2_chi_bo_40_dang_vien_tro_len' => ['- Chi bộ từ 40 Đảng viên trở lên', 'Hoàn thành 10% khối lượng NV3']
                        ];
                        foreach ($tasks_5_2 as $id => $details):
                            $task_id = str_replace('.', '_', $id);
                        ?>
                        <tr class="sub-task" data-group="group-5.2">
                            <td></td>
                            <td><?php echo $details[0]; ?></td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="selected_tasks[]" value="<?php echo $id; ?>"
                                    class="sub-task-checkbox"
                                    <?php echo isset($registered_tasks[$id]) ? 'checked' : ''; ?>>
                            </td>
                            <td><input type="number" name="quantity_<?php echo $task_id; ?>" min="0"
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['quantity'] : '0'; ?>"
                                    class="quantity-input" style="width: 60px;"></td>
                            <td class="hours faded"><?php echo $details[1]; ?></td>
                            <td class="total-hours faded">
                                <?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>
                            </td>
                            <td class="evidence-cell">
                                <input type="file" name="evidence[<?php echo $id; ?>]" id="evidence_<?php echo $task_id; ?>" 
                                    class="evidence-input" accept=".pdf,.doc,.docx,.jpg,.png">
                                <label for="evidence_<?php echo $task_id; ?>">
                                    <i class="fas fa-upload"></i> Chọn file
                                </label>
                                <span class="file-name" id="fileName_<?php echo $task_id; ?>"></span>
                                <input type="hidden" name="total_hours_<?php echo $task_id; ?>" 
                                    class="total-hours-input" 
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>">
                                <?php if (isset($registered_tasks[$id]['evidence_path'])): ?>
                                    <a href="uploads/NV_Khac_A/<?php echo $registered_tasks[$id]['evidence_path']; ?>" 
                                       class="evidence-link" target="_blank">
                                        <i class="fas fa-file-alt"></i> Xem minh chứng
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- 5.3: Ủy ban kiểm tra Công đoàn Trường -->
                        <tr data-group="group-5.3">
                            <td>5.3</td>
                            <td>Ủy ban kiểm tra Công đoàn Trường</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-5.3"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_5_3 = [
                            '5.3_chu_nhiem_uy_ban_kiem_tra_cong_doan_truong' => ['- Chủ nhiệm', 'Hoàn thành 25% khối lượng NV3'],
                            '5.3_pho_chu_nhiem_uy_ban_kiem_tra_cong_doan_truong' => ['- Phó Chủ nhiệm', 'Hoàn thành 20% khối lượng NV3'],
                            '5.3_uy_vien_uy_ban_kiem_tra_cong_doan_truong' => ['- Ủy viên', 'Hoàn thành 15% khối lượng NV3']
                        ];
                        foreach ($tasks_5_3 as $id => $details):
                            $task_id = str_replace('.', '_', $id);
                        ?>
                        <tr class="sub-task" data-group="group-5.3">
                            <td></td>
                            <td><?php echo $details[0]; ?></td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="selected_tasks[]" value="<?php echo $id; ?>"
                                    class="sub-task-checkbox"
                                    <?php echo isset($registered_tasks[$id]) ? 'checked' : ''; ?>>
                            </td>
                            <td><input type="number" name="quantity_<?php echo $task_id; ?>" min="0"
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['quantity'] : '0'; ?>"
                                    class="quantity-input" style="width: 60px;"></td>
                            <td class="hours faded"><?php echo $details[1]; ?></td>
                            <td class="total-hours faded">
                                <?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>
                            </td>
                            <td class="evidence-cell">
                                <input type="file" name="evidence[<?php echo $id; ?>]" id="evidence_<?php echo $task_id; ?>" 
                                    class="evidence-input" accept=".pdf,.doc,.docx,.jpg,.png">
                                <label for="evidence_<?php echo $task_id; ?>">
                                    <i class="fas fa-upload"></i> Chọn file
                                </label>
                                <span class="file-name" id="fileName_<?php echo $task_id; ?>"></span>
                                <input type="hidden" name="total_hours_<?php echo $task_id; ?>" 
                                    class="total-hours-input" 
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>">
                                <?php if (isset($registered_tasks[$id]['evidence_path'])): ?>
                                    <a href="uploads/NV_Khac_A/<?php echo $registered_tasks[$id]['evidence_path']; ?>" 
                                       class="evidence-link" target="_blank">
                                        <i class="fas fa-file-alt"></i> Xem minh chứng
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- 5.4: Ban Thanh tra Nhân dân -->
                        <tr data-group="group-5.4">
                            <td>5.4</td>
                            <td>Ban Thanh tra Nhân dân</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-5.4"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_5_4 = [
                            '5.4_pho_chu_nhiem_ban_thanh_tra_nhan_dan' => ['- Phó Chủ nhiệm', 'Hoàn thành 15% khối lượng NV3'],
                            '5.4_uy_vien_ban_thanh_tra_nhan_dan' => ['- Ủy viên', 'Hoàn thành 10% khối lượng NV3']
                        ];
                        foreach ($tasks_5_4 as $id => $details):
                            $task_id = str_replace('.', '_', $id);
                        ?>
                        <tr class="sub-task" data-group="group-5.4">
                            <td></td>
                            <td><?php echo $details[0]; ?></td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="selected_tasks[]" value="<?php echo $id; ?>"
                                    class="sub-task-checkbox"
                                    <?php echo isset($registered_tasks[$id]) ? 'checked' : ''; ?>>
                            </td>
                            <td><input type="number" name="quantity_<?php echo $task_id; ?>" min="0"
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['quantity'] : '0'; ?>"
                                    class="quantity-input" style="width: 60px;"></td>
                            <td class="hours faded"><?php echo $details[1]; ?></td>
                            <td class="total-hours faded">
                                <?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>
                            </td>
                            <td class="evidence-cell">
                                <input type="file" name="evidence[<?php echo $id; ?>]" id="evidence_<?php echo $task_id; ?>" 
                                    class="evidence-input" accept=".pdf,.doc,.docx,.jpg,.png">
                                <label for="evidence_<?php echo $task_id; ?>">
                                    <i class="fas fa-upload"></i> Chọn file
                                </label>
                                <span class="file-name" id="fileName_<?php echo $task_id; ?>"></span>
                                <input type="hidden" name="total_hours_<?php echo $task_id; ?>" 
                                    class="total-hours-input" 
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>">
                                <?php if (isset($registered_tasks[$id]['evidence_path'])): ?>
                                    <a href="uploads/NV_Khac_A/<?php echo $registered_tasks[$id]['evidence_path']; ?>" 
                                       class="evidence-link" target="_blank">
                                        <i class="fas fa-file-alt"></i> Xem minh chứng
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- 5.5: Ban Nữ công Trường -->
                        <tr data-group="group-5.5">
                            <td>5.5</td>
                            <td>Ban Nữ công Trường</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-5.5"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_5_5 = [
                            '5.5_pho_chu_nhiem_ban_nu_cong_truong' => ['- Phó Chủ nhiệm', 'Hoàn thành 15% khối lượng NV3'],
                            '5.5_uy_vien_ban_nu_cong_truong' => ['- Ủy viên', 'Hoàn thành 10% khối lượng NV3']
                        ];
                        foreach ($tasks_5_5 as $id => $details):
                            $task_id = str_replace('.', '_', $id);
                        ?>
                        <tr class="sub-task" data-group="group-5.5">
                            <td></td>
                            <td><?php echo $details[0]; ?></td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="selected_tasks[]" value="<?php echo $id; ?>"
                                    class="sub-task-checkbox"
                                    <?php echo isset($registered_tasks[$id]) ? 'checked' : ''; ?>>
                            </td>
                            <td><input type="number" name="quantity_<?php echo $task_id; ?>" min="0"
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['quantity'] : '0'; ?>"
                                    class="quantity-input" style="width: 60px;"></td>
                            <td class="hours faded"><?php echo $details[1]; ?></td>
                            <td class="total-hours faded">
                                <?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>
                            </td>
                            <td class="evidence-cell">
                                <input type="file" name="evidence[<?php echo $id; ?>]" id="evidence_<?php echo $task_id; ?>" 
                                    class="evidence-input" accept=".pdf,.doc,.docx,.jpg,.png">
                                <label for="evidence_<?php echo $task_id; ?>">
                                    <i class="fas fa-upload"></i> Chọn file
                                </label>
                                <span class="file-name" id="fileName_<?php echo $task_id; ?>"></span>
                                <input type="hidden" name="total_hours_<?php echo $task_id; ?>" 
                                    class="total-hours-input" 
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>">
                                <?php if (isset($registered_tasks[$id]['evidence_path'])): ?>
                                    <a href="uploads/NV_Khac_A/<?php echo $registered_tasks[$id]['evidence_path']; ?>" 
                                       class="evidence-link" target="_blank">
                                        <i class="fas fa-file-alt"></i> Xem minh chứng
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- 5.6: Tổ trưởng Tổ Nữ công -->
                        <tr class="task-group" data-group="group-5.6">
                            <td>5.6</td>
                            <td>Tổ trưởng Tổ Nữ công</td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="selected_tasks[]" value="5.6_to_truong_to_nu_cong"
                                    class="sub-task-checkbox"
                                    <?php echo isset($registered_tasks['5.6_to_truong_to_nu_cong']) ? 'checked' : ''; ?>>
                            </td>
                            <td><input type="number" name="quantity_5_6_to_truong_to_nu_cong" min="0"
                                    value="<?php echo isset($registered_tasks['5.6_to_truong_to_nu_cong']) ? $registered_tasks['5.6_to_truong_to_nu_cong']['quantity'] : '0'; ?>"
                                    class="quantity-input" style="width: 60px;"></td>
                            <td class="hours faded">Hoàn thành 5% khối lượng NV3</td>
                            <td class="total-hours faded">
                                <?php echo isset($registered_tasks['5.6_to_truong_to_nu_cong']) ? $registered_tasks['5.6_to_truong_to_nu_cong']['total_hours'] : '0'; ?>
                            </td>
                            <td class="evidence-cell">
                                <input type="file" name="evidence[5.6_to_truong_to_nu_cong]" id="evidence_5_6_to_truong_to_nu_cong" 
                                    class="evidence-input" accept=".pdf,.doc,.docx,.jpg,.png">
                                <label for="evidence_5_6_to_truong_to_nu_cong">
                                    <i class="fas fa-upload"></i> Chọn file
                                </label>
                                <span class="file-name" id="fileName_5_6_to_truong_to_nu_cong"></span>
                                <input type="hidden" name="total_hours_5_6_to_truong_to_nu_cong" 
                                    class="total-hours-input" 
                                    value="<?php echo isset($registered_tasks['5.6_to_truong_to_nu_cong']) ? $registered_tasks['5.6_to_truong_to_nu_cong']['total_hours'] : '0'; ?>">
                                <?php if (isset($registered_tasks['5.6_to_truong_to_nu_cong']['evidence_path'])): ?>
                                    <a href="uploads/NV_Khac_A/<?php echo $registered_tasks['5.6_to_truong_to_nu_cong']['evidence_path']; ?>" 
                                       class="evidence-link" target="_blank">
                                        <i class="fas fa-file-alt"></i> Xem minh chứng
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Nhóm 6: Ban chấp hành Công đoàn Trường -->
                        <tr class="task-group" data-group="group-6">
                            <td>6</td>
                            <td>Ban chấp hành Công đoàn Trường</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-6"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_6 = [
                            '6_chu_tich_cong_doan_truong' => ['- Chủ tịch Công đoàn Trường', 'Hoàn thành 30% khối lượng NV3'],
                            '6_pho_chu_tich_cong_doan_truong' => ['- Phó Chủ tịch Công đoàn Trường', 'Hoàn thành 25% khối lượng NV3'],
                            '6_uy_vien_cong_doan_truong' => ['- Ủy viên Công đoàn Trường', 'Hoàn thành 20% khối lượng NV3']
                        ];
                        foreach ($tasks_6 as $id => $details):
                            $task_id = str_replace('.', '_', $id);
                        ?>
                        <tr class="sub-task" data-group="group-6">
                            <td></td>
                            <td><?php echo $details[0]; ?></td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="selected_tasks[]" value="<?php echo $id; ?>"
                                    class="sub-task-checkbox"
                                    <?php echo isset($registered_tasks[$id]) ? 'checked' : ''; ?>>
                            </td>
                            <td><input type="number" name="quantity_<?php echo $task_id; ?>" min="0"
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['quantity'] : '0'; ?>"
                                    class="quantity-input" style="width: 60px;"></td>
                            <td class="hours faded"><?php echo $details[1]; ?></td>
                            <td class="total-hours faded">
                                <?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>
                            </td>
                            <td class="evidence-cell">
                                <input type="file" name="evidence[<?php echo $id; ?>]" id="evidence_<?php echo $task_id; ?>" 
                                    class="evidence-input" accept=".pdf,.doc,.docx,.jpg,.png">
                                <label for="evidence_<?php echo $task_id; ?>">
                                    <i class="fas fa-upload"></i> Chọn file
                                </label>
                                <span class="file-name" id="fileName_<?php echo $task_id; ?>"></span>
                                <input type="hidden" name="total_hours_<?php echo $task_id; ?>" 
                                    class="total-hours-input" 
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>">
                                <?php if (isset($registered_tasks[$id]['evidence_path'])): ?>
                                    <a href="uploads/NV_Khac_A/<?php echo $registered_tasks[$id]['evidence_path']; ?>" 
                                       class="evidence-link" target="_blank">
                                        <i class="fas fa-file-alt"></i> Xem minh chứng
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Nhóm 7: Công đoàn bộ phận -->
                        <tr class="task-group" data-group="group-7">
                            <td>7</td>
                            <td>Công đoàn bộ phận</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-7"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_7 = [
                            '7_chu_tich_cong_doan_bo_phan' => ['- Chủ tịch Công đoàn bộ phận', 'Hoàn thành 15% khối lượng NV3'],
                            '7_pho_chu_tich_cong_doan_bo_phan' => ['- Phó Chủ tịch Công đoàn bộ phận', 'Hoàn thành 10% khối lượng NV3'],
                            '7_uy_vien_cong_doan_bo_phan' => ['- Ủy viên Công đoàn bộ phận', 'Hoàn thành 5% khối lượng NV3']
                        ];
                        foreach ($tasks_7 as $id => $details):
                            $task_id = str_replace('.', '_', $id);
                        ?>
                        <tr class="sub-task" data-group="group-7">
                            <td></td>
                            <td><?php echo $details[0]; ?></td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="selected_tasks[]" value="<?php echo $id; ?>"
                                    class="sub-task-checkbox"
                                    <?php echo isset($registered_tasks[$id]) ? 'checked' : ''; ?>>
                            </td>
                            <td><input type="number" name="quantity_<?php echo $task_id; ?>" min="0"
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['quantity'] : '0'; ?>"
                                    class="quantity-input" style="width: 60px;"></td>
                            <td class="hours faded"><?php echo $details[1]; ?></td>
                            <td class="total-hours faded">
                                <?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>
                            </td>
                            <td class="evidence-cell">
                                <input type="file" name="evidence[<?php echo $id; ?>]" id="evidence_<?php echo $task_id; ?>" 
                                    class="evidence-input" accept=".pdf,.doc,.docx,.jpg,.png">
                                <label for="evidence_<?php echo $task_id; ?>">
                                    <i class="fas fa-upload"></i> Chọn file
                                </label>
                                <span class="file-name" id="fileName_<?php echo $task_id; ?>"></span>
                                <input type="hidden" name="total_hours_<?php echo $task_id; ?>" 
                                    class="total-hours-input" 
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>">
                                <?php if (isset($registered_tasks[$id]['evidence_path'])): ?>
                                    <a href="uploads/NV_Khac_A/<?php echo $registered_tasks[$id]['evidence_path']; ?>" 
                                       class="evidence-link" target="_blank">
                                        <i class="fas fa-file-alt"></i> Xem minh chứng
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Nhóm 8: Ban Chấp hành Đoàn Thanh niên Trường -->
                        <tr class="task-group" data-group="group-8">
                            <td>8</td>
                            <td>Ban Chấp hành Đoàn Thanh niên Trường</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-8"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_8 = [
                            '8_bi_thu_doan_thanh_nien_truong' => ['- Bí thư', 'Hoàn thành 30% khối lượng NV3'],
                            '8_pho_bi_thu_doan_thanh_nien_truong' => ['- Phó Bí thư', 'Hoàn thành 25% khối lượng NV3'],
                            '8_uy_vien_bch_doan_thanh_nien_truong' => ['- Ủy viên BCH', 'Hoàn thành 15% khối lượng NV3']
                        ];
                        foreach ($tasks_8 as $id => $details):
                            $task_id = str_replace('.', '_', $id);
                        ?>
                        <tr class="sub-task" data-group="group-8">
                            <td></td>
                            <td><?php echo $details[0]; ?></td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="selected_tasks[]" value="<?php echo $id; ?>"
                                    class="sub-task-checkbox"
                                    <?php echo isset($registered_tasks[$id]) ? 'checked' : ''; ?>>
                            </td>
                            <td><input type="number" name="quantity_<?php echo $task_id; ?>" min="0"
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['quantity'] : '0'; ?>"
                                    class="quantity-input" style="width: 60px;"></td>
                            <td class="hours faded"><?php echo $details[1]; ?></td>
                            <td class="total-hours faded">
                                <?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>
                            </td>
                            <td class="evidence-cell">
                                <input type="file" name="evidence[<?php echo $id; ?>]" id="evidence_<?php echo $task_id; ?>" 
                                    class="evidence-input" accept=".pdf,.doc,.docx,.jpg,.png">
                                <label for="evidence_<?php echo $task_id; ?>">
                                    <i class="fas fa-upload"></i> Chọn file
                                </label>
                                <span class="file-name" id="fileName_<?php echo $task_id; ?>"></span>
                                <input type="hidden" name="total_hours_<?php echo $task_id; ?>" 
                                    class="total-hours-input" 
                                    value="<?php echo isset($registered_tasks[$id]) ? $registered_tasks[$id]['total_hours'] : '0'; ?>">
                                <?php if (isset($registered_tasks[$id]['evidence_path'])): ?>
                                    <a href="uploads/NV_Khac_A/<?php echo $registered_tasks[$id]['evidence_path']; ?>" 
                                       class="evidence-link" target="_blank">
                                        <i class="fas fa-file-alt"></i> Xem minh chứng
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Nhóm 9: Ủy viên BCH Hội Cựu chiến binh Trường -->
                        <tr class="task-group" data-group="group-9">
                            <td>9</td>
                            <td>Ủy viên BCH Hội Cựu chiến binh Trường</td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="selected_tasks[]"
                                    value="9_uy_vien_bch_hoi_cuu_chien_binh_truong" class="sub-task-checkbox"
                                    <?php echo isset($registered_tasks['9_uy_vien_bch_hoi_cuu_chien_binh_truong']) ? 'checked' : ''; ?>>
                            </td>
                            <td><input type="number" name="quantity_9_uy_vien_bch_hoi_cuu_chien_binh_truong" min="0"
                                    value="<?php echo isset($registered_tasks['9_uy_vien_bch_hoi_cuu_chien_binh_truong']) ? $registered_tasks['9_uy_vien_bch_hoi_cuu_chien_binh_truong']['quantity'] : '0'; ?>"
                                    class="quantity-input" style="width: 60px;"></td>
                            <td class="hours faded">Hoàn thành 10% khối lượng NV3</td>
                            <td class="total-hours faded">
                                <?php echo isset($registered_tasks['9_uy_vien_bch_hoi_cuu_chien_binh_truong']) ? $registered_tasks['9_uy_vien_bch_hoi_cuu_chien_binh_truong']['total_hours'] : '0'; ?>
                            </td>
                            <td class="evidence-cell">
                                <input type="file" name="evidence[9_uy_vien_bch_hoi_cuu_chien_binh_truong]" id="evidence_9_uy_vien_bch_hoi_cuu_chien_binh_truong" 
                                    class="evidence-input" accept=".pdf,.doc,.docx,.jpg,.png">
                                <label for="evidence_9_uy_vien_bch_hoi_cuu_chien_binh_truong">
                                    <i class="fas fa-upload"></i> Chọn file
                                </label>
                                <span class="file-name" id="fileName_9_uy_vien_bch_hoi_cuu_chien_binh_truong"></span>
                                <input type="hidden" name="total_hours_9_uy_vien_bch_hoi_cuu_chien_binh_truong" 
                                    class="total-hours-input" 
                                    value="<?php echo isset($registered_tasks['9_uy_vien_bch_hoi_cuu_chien_binh_truong']) ? $registered_tasks['9_uy_vien_bch_hoi_cuu_chien_binh_truong']['total_hours'] : '0'; ?>">
                                <?php if (isset($registered_tasks['9_uy_vien_bch_hoi_cuu_chien_binh_truong']['evidence_path'])): ?>
                                    <a href="uploads/NV_Khac_A/<?php echo $registered_tasks['9_uy_vien_bch_hoi_cuu_chien_binh_truong']['evidence_path']; ?>" 
                                       class="evidence-link" target="_blank">
                                        <i class="fas fa-file-alt"></i> Xem minh chứng
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="form-actions">
                    <button type="submit" class="submit-btn">Gửi</button>
                    <button type="button" class="reset-btn">Làm lại</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($message)): ?>
    <div class="dialog-overlay" id="successDialog" style="display: flex;">
        <div class="dialog-box">
            <p><?php echo $message; ?></p>
            <button onclick="closeDialog()">Đóng</button>
        </div>
    </div>
    <?php endif; ?>

    <script>
    const TOTAL_NV3_HOURS = <?php echo $total_nv3_hours; ?>;

    document.getElementById('year').addEventListener('change', function() {
        const selectedYear = this.value;
        const url = new URL(window.location.href);
        url.searchParams.set('year', selectedYear);
        window.history.pushState({}, '', url);
        window.location.reload();
    });

    function calculateHours(hoursText, quantity) {
        if (hoursText.includes('%')) {
            const percentage = parseFloat(hoursText.match(/\d+(\.\d+)?/)[0]);
            return (percentage / 100) * TOTAL_NV3_HOURS * quantity;
        } else if (hoursText.includes('giờ')) {
            const hours = parseFloat(hoursText.match(/\d+(\.\d+)?/)[0]);
            if (hoursText.includes('tối đa')) {
                const maxHours = parseFloat(hoursText.match(/tối đa\s*(\d+)/)[1]);
                return Math.min(hours * quantity, maxHours);
            }
            return hours * quantity;
        } else if (hoursText.includes('thực tế') || hoursText.includes('phê duyệt') || hoursText.includes(
                'thời khóa biểu')) {
            return quantity; // Giả sử thời gian thực tế được nhập qua quantity
        }
        return 0;
    }

    const groupCheckboxes = document.querySelectorAll('.group-checkbox');
    groupCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const group = this.getAttribute('data-group');
            const subTasks = document.querySelectorAll(`.sub-task[data-group="${group}"]`);
            subTasks.forEach(task => {
                task.style.display = this.checked ? 'table-row' : 'none';
                if (!this.checked) {
                    const subCheckbox = task.querySelector('.sub-task-checkbox');
                    subCheckbox.checked = false;
                    const hoursCell = task.querySelector('.hours');
                    const totalHoursCell = task.querySelector('.total-hours');
                    const totalHoursInput = task.querySelector('.total-hours-input');
                    const quantityInput = task.querySelector('.quantity-input');
                    hoursCell.classList.add('faded');
                    totalHoursCell.classList.add('faded');
                    totalHoursCell.textContent = '0';
                    totalHoursInput.value = '0';
                    quantityInput.value = 0;
                }
            });
        });
    });

    const subTaskCheckboxes = document.querySelectorAll('.sub-task-checkbox');
    subTaskCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            const hoursCell = row.querySelector('.hours');
            const totalHoursCell = row.querySelector('.total-hours');
            const totalHoursInput = row.querySelector('.total-hours-input');
            const quantityInput = row.querySelector('.quantity-input');

            if (this.checked) {
                hoursCell.classList.remove('faded');
                totalHoursCell.classList.remove('faded');
                const hoursText = hoursCell.textContent;
                const quantity = parseInt(quantityInput.value) || 0;
                const totalHours = calculateHours(hoursText, quantity);
                totalHoursCell.textContent = totalHours.toFixed(2);
                totalHoursInput.value = totalHours.toFixed(2);
            } else {
                hoursCell.classList.add('faded');
                totalHoursCell.classList.add('faded');
                totalHoursCell.textContent = '0';
                totalHoursInput.value = '0';
                quantityInput.value = 0;
            }
        });
    });

    const quantityInputs = document.querySelectorAll('.quantity-input');
    quantityInputs.forEach(input => {
        input.addEventListener('input', function() {
            const row = this.closest('tr');
            const checkbox = row.querySelector('.sub-task-checkbox');
            const totalHoursCell = row.querySelector('.total-hours');
            const totalHoursInput = row.querySelector('.total-hours-input');

            if (checkbox && checkbox.checked) {
                const hoursCell = row.querySelector('.hours');
                const hoursText = hoursCell.textContent;
                const quantity = parseInt(this.value) || 0;
                const totalHours = calculateHours(hoursText, quantity);
                totalHoursCell.textContent = totalHours.toFixed(2);
                totalHoursInput.value = totalHours.toFixed(2);
            }
        });
    });

    document.querySelector('.reset-btn').addEventListener('click', function() {
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => checkbox.checked = false);
        document.querySelectorAll('.sub-task').forEach(task => task.style.display = 'none');
        document.querySelectorAll('.hours, .total-hours').forEach(cell => {
            cell.classList.add('faded');
            if (cell.classList.contains('total-hours')) cell.textContent = '0';
        });
        document.querySelectorAll('.quantity-input').forEach(input => input.value = 0);
        document.querySelectorAll('.total-hours-input').forEach(input => input.value = 0);
    });

    document.querySelectorAll('.sub-task-checkbox').forEach(checkbox => {
        if (checkbox.checked) {
            const group = checkbox.closest('.sub-task')?.getAttribute('data-group');
            if (group) {
                const groupCheckbox = document.querySelector(`.group-checkbox[data-group="${group}"]`);
                if (groupCheckbox) {
                    groupCheckbox.checked = true;
                    document.querySelectorAll(`.sub-task[data-group="${group}"]`).forEach(task => {
                        task.style.display = 'table-row';
                    });
                }
            }
            const row = checkbox.closest('tr');
            row.querySelector('.hours').classList.remove('faded');
            row.querySelector('.total-hours').classList.remove('faded');
        }
    });

    function closeDialog() {
        const dialog = document.getElementById('successDialog');
        if (dialog) {
            dialog.style.display = 'none';
        }
    }

    document.querySelectorAll('.evidence-input').forEach(input => {
        input.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const taskId = this.id.replace('evidence_', '');
            const fileNameElement = document.getElementById(`fileName_${taskId}`);
            if (fileNameElement) {
                fileNameElement.textContent = fileName || '';
            }
        });
    });
    </script>
</body>

</html>