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
                           WHERE employee_id = ? AND year = ? AND section = 'b2_3'");
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
    // Xóa toàn bộ dữ liệu cũ trước khi lưu mới để ghi đè
    $delete_sql = "DELETE FROM task_registrations WHERE employee_id = ? AND year = ? AND section = 'b2_3'";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->execute([$employee_id, $selected_year]);

    $selected_tasks = $_POST['selected_tasks'] ?? [];
    
    // Thêm dữ liệu mới (chỉ các nhiệm vụ được chọn trong form hiện tại)
    foreach ($selected_tasks as $original_task_id) {
        $task_id = trim($original_task_id);
        $normalized_task_id = str_replace('.', '_', $task_id);
        
        $quantity_field = "quantity_" . $normalized_task_id;
        $total_hours_field = "total_hours_" . $normalized_task_id;
        
        $quantity = (int)($_POST[$quantity_field] ?? 0);
        $total_hours = (float)($_POST[$total_hours_field] ?? 0);

        if ($quantity > 0 && $total_hours > 0) {
            // Sử dụng evidence_path cũ nếu có, nếu không thì để null và chờ upload mới
            $evidence_path = $registered_tasks[$task_id]['evidence_path'] ?? null;
            $sql = "INSERT INTO task_registrations 
                    (employee_id, task_id, quantity, total_hours, section, year, evidence_path) 
                    VALUES (?, ?, ?, ?, 'b2_3', ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$employee_id, $task_id, $quantity, $total_hours, $selected_year, $evidence_path]);
        }
    }

    // Xử lý upload file minh chứng
    if (isset($_FILES['evidence']) && is_array($_FILES['evidence']['name'])) {
        $uploadDir = 'uploads/NV_Khac_B/';
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
                                      WHERE employee_id = ? AND task_id = ? AND year = ? AND section = 'b2_3'";
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
    <title>Đăng ký nhiệm vụ khác - Mục 2 và 3</title>
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

    .b2_3>a {
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
    </style>
</head>

<body>
    <div class="main-content">
        <div class="research-form">
            <h2 class="form-title">Đăng ký nhiệm vụ khác - Mục 2 và 3</h2>
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
                            <td colspan="7">B. Tham gia các nhiệm vụ nhà trường phân công không thuộc nhiệm vụ giảng
                                dạy, NCKH</td>
                        </tr>

                        <!-- Nhóm 2 -->
                        <tr class="task-group" data-group="group-b-2">
                            <td>2</td>
                            <td>Hoạt động phục vụ công tác tuyển sinh, truyền thông, quản lý các trang thông tin của
                                trường, khoa</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-b-2"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_2 = [
                            '2.1_dang_tin_khoa' => ['Đăng tin cấp khoa trên các trang thông tin của trường, khoa', '5 giờ/tin'],
                            '2.1_dang_tin_truong' => ['Đăng tin cấp trường trên các trang thông tin của trường, khoa', '10 giờ/tin'],
                            '2.2_quan_tri_web_fanpage_muc_1' => ['Quản trị web, fanpage khoa - Mức 1 (tốt)', '30 giờ'],
                            '2.2_quan_tri_web_fanpage_muc_2' => ['Quản trị web, fanpage khoa - Mức 2 (khá)', '25 giờ'],
                            '2.2_quan_tri_web_fanpage_muc_3' => ['Quản trị web, fanpage khoa - Mức 3 (trung bình)', '15 giờ'],
                            '2.3_truyen_thong_quang_ba' => ['Tham gia hoạt động truyền thông, quảng bá hình ảnh của Khoa, Trường', 'Theo thời gian thực tế'],
                            '2.4_tuyen_sinh_nhap_hoc' => ['Tham gia công tác tuyển sinh, nhập học của trường', 'Theo thời gian thực tế'],
                            '2.5_ho_tro_quang_ba_tuyen_sinh' => ['Hỗ trợ công tác quảng bá tuyển sinh của trường', 'Theo thời gian thực tế']
                        ];
                        foreach ($tasks_2 as $id => $details):
                            $task_id = str_replace('.', '_', $id);
                        ?>
                        <tr class="sub-task" data-group="group-b-2">
                            <td><?php echo str_replace('_', '.', substr($id, 0, 3)); ?></td>
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
                                <a href="uploads/NV_Khac_B/<?php echo $registered_tasks[$id]['evidence_path']; ?>"
                                    class="evidence-link" target="_blank">
                                    <i class="fas fa-file-alt"></i> Xem minh chứng
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Nhóm 3 -->
                        <tr class="task-group" data-group="group-b-3">
                            <td>3</td>
                            <td>Học tập, nâng cao trình độ, hội họp</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-b-3"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_3 = [
                            '3.1_chung_chi_chuyen_mon' => ['Chứng chỉ/chứng nhận chuyên môn, nghiệp vụ phục vụ trực tiếp', '100 giờ/1 chứng chỉ'],
                            '3.2_chung_chi_nang_cao_chat_luong' => ['Chứng chỉ hỗ trợ nâng cao chất lượng và hiệu quả công việc', '100 giờ/1 chứng chỉ'],
                            '3.3_hoi_thao_trong_nuoc' => ['Tham dự hội thảo chuyên ngành trong nước', '5 giờ/hội thảo'],
                            '3.3_hoi_thao_quoc_te' => ['Tham dự hội thảo chuyên ngành quốc tế', '10 giờ/hội thảo'],
                            '3.4_sinh_hoat_khoa' => ['Sinh hoạt chuyên môn cấp khoa', '4 giờ/buổi'],
                            '3.4_sinh_hoat_truong' => ['Sinh hoạt chuyên môn cấp trường', '6 giờ/buổi'],
                            '3.5_seminar_nguoi_trinh_bay' => ['Thực hiện seminar chuyên môn - Người trình bày', '16 giờ/cuộc'],
                            '3.5_seminar_nguoi_tham_du' => ['Tham gia seminar chuyên môn - Người tham dự', '3 giờ/cuộc'],
                            '3.6_sinh_hoat_chuyen_mon_bo_mon' => ['Tham gia sinh hoạt chuyên môn bộ môn', '4 giờ/buổi, tối đa 40 giờ/năm'],
                            '3.7_du_gio_chuyen_mon' => ['Dự giờ chuyên môn', '2 giờ/giảng viên/1 tiết'],
                            '3.8_tap_huan_ly_luan_chinh_tri' => ['Tham gia tập huấn, bồi dưỡng giảng viên Lý luận chính trị', 'Theo thời gian thực tế'],
                            '3.9_hop_chi_bo' => ['Họp chi bộ', 'Tối đa 4 giờ/tháng'],
                            '3.10_hop_cong_doan' => ['Họp công đoàn', 'Tối đa 16 giờ/năm'],
                            '3.11_hop_doan_thanh_nien' => ['Họp Đoàn thanh niên', 'Tối đa 16 giờ/năm'],
                            '3.12_hop_trao_doi_chuyen_mon' => ['Tham dự các cuộc họp, trao đổi chuyên môn với khách', '3 giờ/buổi'],
                            '3.13_cong_tac_ngoai_truong' => ['Tham dự đoàn công tác quốc gia, quốc tế ngoài trường', 'Theo thời gian thực tế'],
                            '3.14_nghien_cuu_sinh' => ['Giảng viên làm nghiên cứu sinh trong nước', '100% khối lượng NV3'],
                            '3.15_tro_giang_cao_hoc' => ['Trợ giảng đi học cao học đúng chuyên ngành', '50% khối lượng NV3']
                        ];
                        foreach ($tasks_3 as $id => $details):
                            $task_id = str_replace('.', '_', $id);
                        ?>
                        <tr class="sub-task" data-group="group-b-3">
                            <td><?php echo str_replace('_', '.', substr($id, 0, 3)); ?></td>
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
                                <a href="uploads/NV_Khac_B/<?php echo $registered_tasks[$id]['evidence_path']; ?>"
                                    class="evidence-link" target="_blank">
                                    <i class="fas fa-file-alt"></i> Xem minh chứng
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="form-actions">
                    <button type="submit" class="submit-btn">Gửi</button>
                    <button type="button" class="reset-btn">Làm lại</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Dialog HTML -->
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
            const group = checkbox.closest('.sub-task').getAttribute('data-group');
            const groupCheckbox = document.querySelector(`.group-checkbox[data-group="${group}"]`);
            if (groupCheckbox) {
                groupCheckbox.checked = true;
                document.querySelectorAll(`.sub-task[data-group="${group}"]`).forEach(task => {
                    task.style.display = 'table-row';
                });
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

    // Hiển thị tên tệp sau khi chọn
    document.querySelectorAll('.evidence-input').forEach(input => {
        input.addEventListener('change', function() {
            const fileNameSpan = this.parentElement.querySelector('.file-name');
            if (this.files.length > 0) {
                fileNameSpan.textContent = this.files[0].name;
            } else {
                fileNameSpan.textContent = '';
            }
        });
    });
    </script>
</body>

</html>