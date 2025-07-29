<?php
// Kiểm tra nếu session chưa được khởi tạo thì mới gọi session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sửa đường dẫn tới file connection.php
require_once __DIR__ . '../../../../connection/connection.php';

if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login/login.php");
    exit();
}

$employeeID = $_SESSION['employeeID'];
$selectedYear = isset($_POST['selected_year']) ? $_POST['selected_year'] : date('Y');

// Load dữ liệu từ database dựa trên năm đã chọn
$stmt = $conn->prepare("SELECT * FROM coi_thi WHERE employeeID = :employeeID AND result_year = :result_year");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
$examData = $stmt->fetch(PDO::FETCH_ASSOC);

// Nếu không có dữ liệu, khởi tạo mảng trống
if (!$examData) {
    $examData = [
        'muc1_so_cau' => '',
        'muc2_so_cau' => '',
        'muc3_so_cau' => '',
        'muc4_so_cau' => '',
        'muc5_so_cau' => '',
        'muc6_so_cau' => '',
        'muc7_so_cau' => '',
        'muc8_so_cau' => '',
        'muc9_so_cau' => '',
        'muc10_so_cau' => '',
        'muc11_so_cau' => '',
        'muc1_task_type' => '',
        'muc2_task_type' => '',
        'muc3_task_type' => '',
        'muc4_task_type' => '',
        'muc5_task_type' => '',
        'muc6_task_type' => '',
    ];
}

// Xử lý AJAX request để lấy dữ liệu form
if (isset($_POST['action']) && $_POST['action'] === 'get_exam_data') {
    $year = $_POST['year'] ?? date('Y');
    $stmt = $conn->prepare("SELECT * FROM coi_thi WHERE employeeID = :employeeID AND result_year = :result_year");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) {
        $data = [
            'muc1_so_cau' => '',
            'muc2_so_cau' => '',
            'muc3_so_cau' => '',
            'muc4_so_cau' => '',
            'muc5_so_cau' => '',
            'muc6_so_cau' => '',
            'muc7_so_cau' => '',
            'muc8_so_cau' => '',
            'muc9_so_cau' => '',
            'muc10_so_cau' => '',
            'muc11_so_cau' => '',
        ];
    }
    echo json_encode($data);
    exit();
}

// Xử lý kiểm tra dữ liệu tồn tại (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'check_data_exists') {
    $year = $_POST['year'] ?? (date('Y') - 1);
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM coi_thi WHERE employeeID = :employeeID AND result_year = :result_year");
    $checkStmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
    $exists = $checkStmt->fetchColumn();
    echo json_encode(['exists' => $exists > 0]);
    exit();
}

// Xử lý dữ liệu từ form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action']) && isset($_POST['muc1_so_cau'])) {
    $tongGioChuan = 0;

    // Validate the number of exam sessions (đề thi) based on the notes
    $muc1_so_cau = floatval($_POST['muc1_so_cau'] ?? 0);
    $muc2_so_cau = floatval($_POST['muc2_so_cau'] ?? 0);
    $muc3_so_cau = floatval($_POST['muc3_so_cau'] ?? 0);
    $muc4_so_cau = floatval($_POST['muc4_so_cau'] ?? 0);
    $muc5_so_cau = floatval($_POST['muc5_so_cau'] ?? 0);
    $muc6_so_cau = floatval($_POST['muc6_so_cau'] ?? 0);
    $muc7_so_cau = floatval($_POST['muc7_so_cau'] ?? 0);
    $muc8_so_cau = floatval($_POST['muc8_so_cau'] ?? 0);
    $muc9_so_cau = floatval($_POST['muc9_so_cau'] ?? 0);
    $muc10_so_cau = floatval($_POST['muc10_so_cau'] ?? 0);
    $muc11_so_cau = floatval($_POST['muc11_so_cau'] ?? 0);

    // Check limits for "Mỗi học phần không quá 06 đề" (muc1, muc5)
    if ($muc1_so_cau > 6 || $muc5_so_cau > 6) {
        echo "<script>showPopup('Mỗi học phần không được quá 06 đề thi cho mục 1 và 5!', 'error');</script>";
        exit();
    }

    // Check limits for "Mỗi học phần không quá 20 đề" (muc2, muc6)
    if ($muc2_so_cau > 20 || $muc6_so_cau > 20) {
        echo "<script>showPopup('Mỗi học phần không được quá 20 đề thi cho mục 2 và 6!', 'error');</script>";
        exit();
    }

    // Lấy loại công việc từ form
    $muc1_task_type = $_POST['muc1_task_type'] ?? '';
    $muc2_task_type = $_POST['muc2_task_type'] ?? '';
    $muc3_task_type = $_POST['muc3_task_type'] ?? '';
    $muc4_task_type = $_POST['muc4_task_type'] ?? '';
    $muc5_task_type = $_POST['muc5_task_type'] ?? '';
    $muc6_task_type = $_POST['muc6_task_type'] ?? '';

    // Tính giờ quy đổi dựa trên loại công việc
    $muc1_gio = ($muc1_task_type === 'khong') ? 0 : $muc1_so_cau * ($muc1_task_type === 'ra_de' ? 1.0 : 0.5);
    $muc2_gio = ($muc2_task_type === 'khong') ? 0 : $muc2_so_cau * ($muc2_task_type === 'ra_de' ? 0.3 : 0.15);
    $muc3_gio = ($muc3_task_type === 'khong') ? 0 : $muc3_so_cau * ($muc3_task_type === 'ra_de' ? 0.2 : 1.0);
    $muc4_gio = ($muc4_task_type === 'khong') ? 0 : $muc4_so_cau * ($muc4_task_type === 'ra_de' ? 0.15 : 0.075);
    $muc5_gio = ($muc5_task_type === 'khong') ? 0 : $muc5_so_cau * ($muc5_task_type === 'ra_de' ? 1.5 : 0.75);
    $muc6_gio = ($muc6_task_type === 'khong') ? 0 : $muc6_so_cau * ($muc6_task_type === 'ra_de' ? 0.2 : 0.1);
    $muc7_gio = $muc7_so_cau * 1.0;
    $muc8_gio = $muc8_so_cau * 0.1;
    $muc9_gio = $muc9_so_cau * 0.2;
    $muc10_gio = $muc10_so_cau * 0.15;
    $muc11_gio = $muc11_so_cau * 0.15;

    $tongGioChuan = $muc1_gio + $muc2_gio + $muc3_gio + $muc4_gio + $muc5_gio + $muc6_gio + $muc7_gio + $muc8_gio + $muc9_gio + $muc10_gio + $muc11_gio;

    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM coi_thi WHERE employeeID = :employeeID AND result_year = :result_year");
    $checkStmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    $exists = $checkStmt->fetchColumn();

    // Cho phép cập nhật cho năm hiện tại và năm hiện tại - 1
    $currentYear = date('Y');
    $allowedYears = [$currentYear, $currentYear - 1];
    if (!in_array($selectedYear, $allowedYears)) {
        echo "<script>showPopup('Không thể cập nhật dữ liệu cho năm $selectedYear vì chỉ được phép chỉnh sửa năm hiện tại ($currentYear) hoặc năm trước đó (" . ($currentYear - 1) . ")', 'error');</script>";
    } else {
        if ($exists) {
            $stmt = $conn->prepare("
                UPDATE coi_thi SET
                    muc1_so_cau = :muc1_so_cau, muc1_gio_quy_doi = :muc1_gio_quy_doi, muc1_task_type = :muc1_task_type,
                    muc2_so_cau = :muc2_so_cau, muc2_gio_quy_doi = :muc2_gio_quy_doi, muc2_task_type = :muc2_task_type,
                    muc3_so_cau = :muc3_so_cau, muc3_gio_quy_doi = :muc3_gio_quy_doi, muc3_task_type = :muc3_task_type,
                    muc4_so_cau = :muc4_so_cau, muc4_gio_quy_doi = :muc4_gio_quy_doi, muc4_task_type = :muc4_task_type,
                    muc5_so_cau = :muc5_so_cau, muc5_gio_quy_doi = :muc5_gio_quy_doi, muc5_task_type = :muc5_task_type,
                    muc6_so_cau = :muc6_so_cau, muc6_gio_quy_doi = :muc6_gio_quy_doi, muc6_task_type = :muc6_task_type,
                    muc7_so_cau = :muc7_so_cau, muc7_gio_quy_doi = :muc7_gio_quy_doi,
                    muc8_so_cau = :muc8_so_cau, muc8_gio_quy_doi = :muc8_gio_quy_doi,
                    muc9_so_cau = :muc9_so_cau, muc9_gio_quy_doi = :muc9_gio_quy_doi,
                    muc10_so_cau = :muc10_so_cau, muc10_gio_quy_doi = :muc10_gio_quy_doi,
                    muc11_so_cau = :muc11_so_cau, muc11_gio_quy_doi = :muc11_gio_quy_doi,
                    tong_gio_quy_doi = :tong_gio_quy_doi,
                    ngay_cap_nhat = CURRENT_TIMESTAMP
                WHERE employeeID = :employeeID AND result_year = :result_year
            ");
            $stmt->execute([
                ':employeeID' => $employeeID,
                ':result_year' => $selectedYear,
                ':muc1_so_cau' => $muc1_so_cau, ':muc1_gio_quy_doi' => $muc1_gio, ':muc1_task_type' => $muc1_task_type,
                ':muc2_so_cau' => $muc2_so_cau, ':muc2_gio_quy_doi' => $muc2_gio, ':muc2_task_type' => $muc2_task_type,
                ':muc3_so_cau' => $muc3_so_cau, ':muc3_gio_quy_doi' => $muc3_gio, ':muc3_task_type' => $muc3_task_type,
                ':muc4_so_cau' => $muc4_so_cau, ':muc4_gio_quy_doi' => $muc4_gio, ':muc4_task_type' => $muc4_task_type,
                ':muc5_so_cau' => $muc5_so_cau, ':muc5_gio_quy_doi' => $muc5_gio, ':muc5_task_type' => $muc5_task_type,
                ':muc6_so_cau' => $muc6_so_cau, ':muc6_gio_quy_doi' => $muc6_gio, ':muc6_task_type' => $muc6_task_type,
                ':muc7_so_cau' => $muc7_so_cau, ':muc7_gio_quy_doi' => $muc7_gio,
                ':muc8_so_cau' => $muc8_so_cau, ':muc8_gio_quy_doi' => $muc8_gio,
                ':muc9_so_cau' => $muc9_so_cau, ':muc9_gio_quy_doi' => $muc9_gio,
                ':muc10_so_cau' => $muc10_so_cau, ':muc10_gio_quy_doi' => $muc10_gio,
                ':muc11_so_cau' => $muc11_so_cau, ':muc11_gio_quy_doi' => $muc11_gio,
                ':tong_gio_quy_doi' => $tongGioChuan
            ]);
            echo "<script>showPopup('Cập nhật dữ liệu thành công cho năm $selectedYear', 'success');</script>";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO coi_thi (
                    employeeID, result_year,
                    muc1_so_cau, muc1_gio_quy_doi, muc1_task_type,
                    muc2_so_cau, muc2_gio_quy_doi, muc2_task_type,
                    muc3_so_cau, muc3_gio_quy_doi, muc3_task_type,
                    muc4_so_cau, muc4_gio_quy_doi, muc4_task_type,
                    muc5_so_cau, muc5_gio_quy_doi, muc5_task_type,
                    muc6_so_cau, muc6_gio_quy_doi, muc6_task_type,
                    muc7_so_cau, muc7_gio_quy_doi,
                    muc8_so_cau, muc8_gio_quy_doi,
                    muc9_so_cau, muc9_gio_quy_doi,
                    muc10_so_cau, muc10_gio_quy_doi,
                    muc11_so_cau, muc11_gio_quy_doi,
                    tong_gio_quy_doi
                ) VALUES (
                    :employeeID, :result_year,
                    :muc1_so_cau, :muc1_gio_quy_doi, :muc1_task_type,
                    :muc2_so_cau, :muc2_gio_quy_doi, :muc2_task_type,
                    :muc3_so_cau, :muc3_gio_quy_doi, :muc3_task_type,
                    :muc4_so_cau, :muc4_gio_quy_doi, :muc4_task_type,
                    :muc5_so_cau, :muc5_gio_quy_doi, :muc5_task_type,
                    :muc6_so_cau, :muc6_gio_quy_doi, :muc6_task_type,
                    :muc7_so_cau, :muc7_gio_quy_doi,
                    :muc8_so_cau, :muc8_gio_quy_doi,
                    :muc9_so_cau, :muc9_gio_quy_doi,
                    :muc10_so_cau, :muc10_gio_quy_doi,
                    :muc11_so_cau, :muc11_gio_quy_doi,
                    :tong_gio_quy_doi
                )
            ");
            $stmt->execute([
                ':employeeID' => $employeeID,
                ':result_year' => $selectedYear,
                ':muc1_so_cau' => $muc1_so_cau, ':muc1_gio_quy_doi' => $muc1_gio, ':muc1_task_type' => $muc1_task_type,
                ':muc2_so_cau' => $muc2_so_cau, ':muc2_gio_quy_doi' => $muc2_gio, ':muc2_task_type' => $muc2_task_type,
                ':muc3_so_cau' => $muc3_so_cau, ':muc3_gio_quy_doi' => $muc3_gio, ':muc3_task_type' => $muc3_task_type,
                ':muc4_so_cau' => $muc4_so_cau, ':muc4_gio_quy_doi' => $muc4_gio, ':muc4_task_type' => $muc4_task_type,
                ':muc5_so_cau' => $muc5_so_cau, ':muc5_gio_quy_doi' => $muc5_gio, ':muc5_task_type' => $muc5_task_type,
                ':muc6_so_cau' => $muc6_so_cau, ':muc6_gio_quy_doi' => $muc6_gio, ':muc6_task_type' => $muc6_task_type,
                ':muc7_so_cau' => $muc7_so_cau, ':muc7_gio_quy_doi' => $muc7_gio,
                ':muc8_so_cau' => $muc8_so_cau, ':muc8_gio_quy_doi' => $muc8_gio,
                ':muc9_so_cau' => $muc9_so_cau, ':muc9_gio_quy_doi' => $muc9_gio,
                ':muc10_so_cau' => $muc10_so_cau, ':muc10_gio_quy_doi' => $muc10_gio,
                ':muc11_so_cau' => $muc11_so_cau, ':muc11_gio_quy_doi' => $muc11_gio,
                ':tong_gio_quy_doi' => $tongGioChuan
            ]);
            echo "<script>showPopup('Lưu dữ liệu thành công cho năm $selectedYear', 'success');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coi thi và chấm thi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; }
        .teaching-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        .teaching-table th, .teaching-table td { border: 1px solid #d2ddfd; padding: 10px; text-align: left; vertical-align: top; }
        .teaching-table th { background-color: #223771; color: white; text-align: center; }
        .teaching-table td { background-color: #fff; }
        .teaching-table td:first-child { width: 5%; text-align: center; }
        .teaching-table td:nth-child(2) { width: 50%; }
        .teaching-table td:nth-child(3) { width: 10%; text-align: center; }
        .teaching-table td:nth-child(4) { width: 10%; text-align: center; }
        .teaching-table td:nth-child(5) { width: 15%; text-align: center; }
        .teaching-table td:nth-child(6) { width: 10%; text-align: center; }
        .form-title { color: #223771; font-size: 24px; margin-bottom: 20px; text-align: center; }
        .total-input { width: 100px; padding: 5px; text-align: center; margin-bottom: 5px; }
        .year-select { padding: 8px; font-size: 14px; border-radius: 4px; margin-bottom: 10px; }
        button {
            background-color: #223771;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #1a2a5b;
        }
        button:active {
            background-color: #142146;
        }
        .coithi a, .NV1 {
            color: #f8843d !important;
        }
        .task-type { display: flex; flex-direction: column; }
        .task-type label { margin-right: 10px; font-size: 12px; }
    </style>
</head>
<body>
    <h2 class="form-title">Coi thi và chấm thi</h2>
    <!-- Form chọn năm riêng biệt -->
    <div style="text-align: center; margin-bottom: 15px;">
        <form method="POST" action="" id="yearForm">
            <label for="selected_year">Năm lưu dữ liệu: </label>
            <select name="selected_year" id="selectedYear" class="year-select" onchange="this.form.submit()">
                <?php
                $currentYear = date('Y');
                $selectedYear = isset($_POST['selected_year']) ? $_POST['selected_year'] : $currentYear;
                for ($i = $currentYear - 5; $i <= $currentYear; $i++) {
                    echo "<option value='$i'" . ($selectedYear == $i ? " selected" : "") . ">$i</option>";
                }
                ?>
            </select>
        </form>
    </div>

    <!-- Form chính -->
    <form id="examForm" method="POST" action="" onsubmit="return handleSubmit(event)">
        <input type="hidden" name="selected_year" value="<?php echo $selectedYear; ?>">
        <table class="teaching-table">
            <thead>
                <tr>
                    <th>TT</th>
                    <th>Công việc</th>
                    <th>Định mức lao động</th>
                    <th>Giờ chuẩn</th>
                    <th>Tổng số</th>
                    <th>Tổng giờ quy đổi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>Ra đề, phân biên đề thi viết và đề thi thực hành trong phòng máy (áp dụng đối với thi giữa học phần và kết thúc học phần trình độ đại học)</td>
                    <td class="task-dinh-muc" data-ra-de="0.1 đề + 0.1 đáp án" data-phan-bien="0.1 đề + 0.1 đáp án">0.1 đề + 0.1 đáp án</td>
                    <td class="standard-hour" data-ra-de="1.0" data-phan-bien="0.5" data-hour="0">0 gc</td>
                    <td>
                        <input type="number" name="muc1_so_cau" class="total-input exam-input" min="0" step="1" placeholder="Tổng đề" max="6" value="<?php echo htmlspecialchars($examData['muc1_so_cau']); ?>">
                        <div class="task-type">
                            <label>
                                <input type="radio" name="muc1_task_type" value="ra_de" class="task-type-checkbox" 
                                    <?php echo $examData['muc1_task_type'] === 'ra_de' ? 'checked' : ''; ?>> Ra đề thi
                            </label>
                            <label>
                                <input type="radio" name="muc1_task_type" value="phan_bien" class="task-type-checkbox"
                                    <?php echo $examData['muc1_task_type'] === 'phan_bien' ? 'checked' : ''; ?>> Phân biên đề thi
                            </label>
                        </div>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>Ra đề, phân biên đề thi thực hành trong phòng thí nghiệm; Ra đề, phân biên đề thi văn đáp (áp dụng đối với thi giữa học phần và kết thúc học phần trình độ đại học)</td>
                    <td class="task-dinh-muc" data-ra-de="0.1 đề" data-phan-bien="0.1 đề">0.1 đề</td>
                    <td class="standard-hour" data-ra-de="0.3" data-phan-bien="0.15" data-hour="0">0 gc</td>
                    <td>
                        <input type="number" name="muc2_so_cau" class="total-input exam-input" min="0" step="1" placeholder="Tổng đề" max="20" value="<?php echo htmlspecialchars($examData['muc2_so_cau']); ?>">
                        <div class="task-type">
                            <label>
                                <input type="radio" name="muc2_task_type" value="ra_de" class="task-type-checkbox" 
                                    <?php echo $examData['muc2_task_type'] === 'ra_de' ? 'checked' : ''; ?>> Ra đề thi
                            </label>
                            <label>
                                <input type="radio" name="muc2_task_type" value="phan_bien" class="task-type-checkbox"
                                    <?php echo $examData['muc2_task_type'] === 'phan_bien' ? 'checked' : ''; ?>> Phân biên đề thi
                            </label>
                        </div>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>3</td>
                    <td>Ra đề, phân biên đề thi viết đối với các học phần giảng dạy bằng tiếng Anh (không phải là môn Ngoại ngữ, áp dụng đối với thi giữa học phần và kết thúc học phần trình độ đại học)</td>
                    <td class="task-dinh-muc" data-ra-de="0.1 đề + 0.1 đáp án" data-phan-bien="0.1 đề + 0.1 đáp án">0.1 đề + 0.1 đáp án</td>
                    <td class="standard-hour" data-ra-de="0.2" data-phan-bien="1.0" data-hour="0">0 gc</td>
                    <td>
                        <input type="number" name="muc3_so_cau" class="total-input exam-input" min="0" step="1" placeholder="Tổng đề" max="6" value="<?php echo htmlspecialchars($examData['muc3_so_cau']); ?>">
                        <div class="task-type">
                            <label>
                                <input type="radio" name="muc3_task_type" value="ra_de" class="task-type-checkbox" 
                                    <?php echo $examData['muc3_task_type'] === 'ra_de' ? 'checked' : ''; ?>> Ra đề thi
                            </label>
                            <label>
                                <input type="radio" name="muc3_task_type" value="phan_bien" class="task-type-checkbox"
                                    <?php echo $examData['muc3_task_type'] === 'phan_bien' ? 'checked' : ''; ?>> Phân biên đề thi
                            </label>
                        </div>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>4</td>
                    <td>Ra đề, phân biên đề thi kỹ năng nội đối với môn Ngoại ngữ (áp dụng đối với thi giữa học phần và kết thúc học phần trình độ đại học)</td>
                    <td class="task-dinh-muc" data-ra-de="0.1 đề + 0.1 đáp án" data-phan-bien="0.1 đề + 0.1 đáp án">0.1 đề + 0.1 đáp án</td>
                    <td class="standard-hour" data-ra-de="0.15" data-phan-bien="0.075" data-hour="0">0 gc</td>
                    <td>
                        <input type="number" name="muc4_so_cau" class="total-input exam-input" min="0" step="1" placeholder="Tổng đề" max="20" value="<?php echo htmlspecialchars($examData['muc4_so_cau']); ?>">
                        <div class="task-type">
                            <label>
                                <input type="radio" name="muc4_task_type" value="ra_de" class="task-type-checkbox" 
                                    <?php echo $examData['muc4_task_type'] === 'ra_de' ? 'checked' : ''; ?>> Ra đề thi
                            </label>
                            <label>
                                <input type="radio" name="muc4_task_type" value="phan_bien" class="task-type-checkbox"
                                    <?php echo $examData['muc4_task_type'] === 'phan_bien' ? 'checked' : ''; ?>> Phân biên đề thi
                            </label>
                        </div>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>5</td>
                    <td>Ra đề, phân biên đề thi viết (áp dụng đối với thi giữa học phần và kết thúc học phần trình độ thạc sĩ)</td>
                    <td class="task-dinh-muc" data-ra-de="0.1 đề + 0.1 đáp án" data-phan-bien="0.1 đề + 0.1 đáp án">0.1 đề + 0.1 đáp án</td>
                    <td class="standard-hour" data-ra-de="1.5" data-phan-bien="0.75" data-hour="0">0 gc</td>
                    <td>
                        <input type="number" name="muc5_so_cau" class="total-input exam-input" min="0" step="1" placeholder="Tổng đề" max="6" value="<?php echo htmlspecialchars($examData['muc5_so_cau']); ?>">
                        <div class="task-type">
                            <label>
                                <input type="radio" name="muc5_task_type" value="ra_de" class="task-type-checkbox" 
                                    <?php echo $examData['muc5_task_type'] === 'ra_de' ? 'checked' : ''; ?>> Ra đề thi
                            </label>
                            <label>
                                <input type="radio" name="muc5_task_type" value="phan_bien" class="task-type-checkbox"
                                    <?php echo $examData['muc5_task_type'] === 'phan_bien' ? 'checked' : ''; ?>> Phân biên đề thi
                            </label>
                        </div>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>6</td>
                    <td>Ra đề, phân biên đề thi kỹ năng nội đối với môn Ngoại ngữ (áp dụng đối với thi giữa học phần và kết thúc học phần trình độ thạc sĩ)</td>
                    <td class="task-dinh-muc" data-ra-de="0.1 đề + 0.1 đáp án" data-phan-bien="0.1 đề + 0.1 đáp án">0.1 đề + 0.1 đáp án</td>
                    <td class="standard-hour" data-ra-de="0.2" data-phan-bien="0.1" data-hour="0">0 gc</td>
                    <td>
                        <input type="number" name="muc6_so_cau" class="total-input exam-input" min="0" step="1" placeholder="Tổng đề" max="20" value="<?php echo htmlspecialchars($examData['muc6_so_cau']); ?>">
                        <div class="task-type">
                            <label>
                                <input type="radio" name="muc6_task_type" value="ra_de" class="task-type-checkbox" 
                                    <?php echo $examData['muc6_task_type'] === 'ra_de' ? 'checked' : ''; ?>> Ra đề thi
                            </label>
                            <label>
                                <input type="radio" name="muc6_task_type" value="phan_bien" class="task-type-checkbox"
                                    <?php echo $examData['muc6_task_type'] === 'phan_bien' ? 'checked' : ''; ?>> Phân biên đề thi
                            </label>
                        </div>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>7</td>
                    <td>Coi thi (viết, trắc nghiệm, thực hành trên máy tính)</td>
                    <td>Ca thi</td>
                    <td class="standard-hour" data-hour="1.0">1.0 gc</td>
                    <td><input type="number" name="muc7_so_cau" class="total-input exam-input" min="0" step="1" placeholder="Tổng ca" value="<?php echo htmlspecialchars($examData['muc7_so_cau']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>8</td>
                    <td>Chấm thi (viết, thực hành trên máy tính, kỹ năng nội đối với môn ngoại ngữ, áp dụng đối với thi giữa học phần và kết thúc học phần trình độ đại học)</td>
                    <td>1 bài (1 SV)</td>
                    <td class="standard-hour" data-hour="0.1">0.1 gc</td>
                    <td><input type="number" name="muc8_so_cau" class="total-input exam-input" min="0" step="1" placeholder="Tổng bài" value="<?php echo htmlspecialchars($examData['muc8_so_cau']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>9</td>
                    <td>Chấm thi các học phần giảng dạy bằng tiếng Anh (không phải là môn ngoại ngữ)</td>
                    <td>1 bài</td>
                    <td class="standard-hour" data-hour="0.2">0.2 gc</td>
                    <td><input type="number" name="muc9_so_cau" class="total-input exam-input" min="0" step="1" placeholder="Tổng bài" value="<?php echo htmlspecialchars($examData['muc9_so_cau']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>10</td>
                    <td>Chấm thi (kỹ năng viết, áp dụng đối với thi giữa học phần và kết thúc học phần trình độ thạc sĩ)</td>
                    <td>1 bài (1 HV)</td>
                    <td class="standard-hour" data-hour="0.15">0.15 gc</td>
                    <td><input type="number" name="muc10_so_cau" class="total-input exam-input" min="0" step="1" placeholder="Tổng bài" value="<?php echo htmlspecialchars($examData['muc10_so_cau']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>11</td>
                    <td>Chấm thi (kỹ năng nói, áp dụng đối với thi giữa học phần và kết thúc học phần trình độ thạc sĩ)</td>
                    <td>1 học viên</td>
                    <td class="standard-hour" data-hour="0.15">0.15 gc</td>
                    <td><input type="number" name="muc11_so_cau" class="total-input exam-input" min="0" step="1" placeholder="Tổng học viên" value="<?php echo htmlspecialchars($examData['muc11_so_cau']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
            </tbody>
        </table>
        <div style="text-align: center; margin-top: 20px;">
            <button type="submit">Cập nhật</button>
            <p>Tổng số giờ chuẩn hiện tại: <strong id="tongGioChuan">0 gc</strong></p>
        </div>
    </form>

    <script>
        const examInputs = document.querySelectorAll('.exam-input');
        const convertedHoursCells = document.querySelectorAll('.converted-hours');
        const standardHourCells = document.querySelectorAll('.standard-hour');
        const taskTypeCheckboxes = document.querySelectorAll('.task-type-checkbox');
        const taskDinhMucCells = document.querySelectorAll('.task-dinh-muc');

        const examInputRow1 = examInputs[0], standardHourCellRow1 = standardHourCells[0], convertedHoursCellRow1 = convertedHoursCells[0];
        const examInputRow2 = examInputs[1], standardHourCellRow2 = standardHourCells[1], convertedHoursCellRow2 = convertedHoursCells[1];
        const examInputRow3 = examInputs[2], standardHourCellRow3 = standardHourCells[2], convertedHoursCellRow3 = convertedHoursCells[2];
        const examInputRow4 = examInputs[3], standardHourCellRow4 = standardHourCells[3], convertedHoursCellRow4 = convertedHoursCells[3];
        const examInputRow5 = examInputs[4], standardHourCellRow5 = standardHourCells[4], convertedHoursCellRow5 = convertedHoursCells[4];
        const examInputRow6 = examInputs[5], standardHourCellRow6 = standardHourCells[5], convertedHoursCellRow6 = convertedHoursCells[5];
        const examInputRow7 = examInputs[6], standardHourCellRow7 = standardHourCells[6], convertedHoursCellRow7 = convertedHoursCells[6];
        const examInputRow8 = examInputs[7], standardHourCellRow8 = standardHourCells[7], convertedHoursCellRow8 = convertedHoursCells[7];
        const examInputRow9 = examInputs[8], standardHourCellRow9 = standardHourCells[8], convertedHoursCellRow9 = convertedHoursCells[8];
        const examInputRow10 = examInputs[9], standardHourCellRow10 = standardHourCells[9], convertedHoursCellRow10 = convertedHoursCells[9];
        const examInputRow11 = examInputs[10], standardHourCellRow11 = standardHourCells[10], convertedHoursCellRow11 = convertedHoursCells[10];

        function calculateConvertedHours(input, standardHourCell, convertedHourCell) {
            const exams = parseFloat(input.value) || 0;
            const standardHour = parseFloat(standardHourCell.getAttribute('data-hour'));
            const result = exams * standardHour;
            convertedHourCell.textContent = result.toFixed(2) + ' gc';
            updateTongGioChuan();
        }

        function updateTongGioChuan() {
            let tongGio = 0;
            convertedHoursCells.forEach(cell => {
                const gio = parseFloat(cell.textContent.replace(' gc', '')) || 0;
                tongGio += gio;
            });
            document.getElementById('tongGioChuan').textContent = tongGio.toFixed(2) + ' gc';
        }

        // Xử lý thay đổi checkbox (chỉ cho phép chọn 1 checkbox)
        taskTypeCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const row = this.closest('tr');
                const standardHourCell = row.querySelector('.standard-hour');
                const taskDinhMucCell = row.querySelector('.task-dinh-muc');
                const examInput = row.querySelector('.exam-input');
                const convertedHourCell = row.querySelector('.converted-hours');
                const siblingCheckboxes = row.querySelectorAll('.task-type-checkbox');

                // Nếu checkbox này được chọn, bỏ chọn các checkbox khác trong cùng hàng
                if (this.checked) {
                    siblingCheckboxes.forEach(cb => {
                        if (cb !== this) cb.checked = false;
                    });
                }

                const taskType = this.checked ? this.value : 'khong';
                const raDeHour = parseFloat(standardHourCell.getAttribute('data-ra-de'));
                const phanBienHour = parseFloat(standardHourCell.getAttribute('data-phan-bien'));
                const raDeDinhMuc = taskDinhMucCell.getAttribute('data-ra-de');
                const phanBienDinhMuc = taskDinhMucCell.getAttribute('data-phan-bien');

                if (taskType === 'ra_de') {
                    standardHourCell.setAttribute('data-hour', raDeHour);
                    standardHourCell.textContent = raDeHour.toFixed(2) + ' gc';
                    taskDinhMucCell.textContent = raDeDinhMuc;
                } else if (taskType === 'phan_bien') {
                    standardHourCell.setAttribute('data-hour', phanBienHour);
                    standardHourCell.textContent = phanBienHour.toFixed(2) + ' gc';
                    taskDinhMucCell.textContent = phanBienDinhMuc;
                } else {
                    standardHourCell.setAttribute('data-hour', 0);
                    standardHourCell.textContent = '0 gc';
                    taskDinhMucCell.textContent = raDeDinhMuc; // Mặc định hiển thị định mức của "Ra đề thi"
                }

                calculateConvertedHours(examInput, standardHourCell, convertedHourCell);
            });
        });

        examInputRow1.addEventListener('input', () => calculateConvertedHours(examInputRow1, standardHourCellRow1, convertedHoursCellRow1));
        examInputRow2.addEventListener('input', () => calculateConvertedHours(examInputRow2, standardHourCellRow2, convertedHoursCellRow2));
        examInputRow3.addEventListener('input', () => calculateConvertedHours(examInputRow3, standardHourCellRow3, convertedHoursCellRow3));
        examInputRow4.addEventListener('input', () => calculateConvertedHours(examInputRow4, standardHourCellRow4, convertedHoursCellRow4));
        examInputRow5.addEventListener('input', () => calculateConvertedHours(examInputRow5, standardHourCellRow5, convertedHoursCellRow5));
        examInputRow6.addEventListener('input', () => calculateConvertedHours(examInputRow6, standardHourCellRow6, convertedHoursCellRow6));
        examInputRow7.addEventListener('input', () => calculateConvertedHours(examInputRow7, standardHourCellRow7, convertedHoursCellRow7));
        examInputRow8.addEventListener('input', () => calculateConvertedHours(examInputRow8, standardHourCellRow8, convertedHoursCellRow8));
        examInputRow9.addEventListener('input', () => calculateConvertedHours(examInputRow9, standardHourCellRow9, convertedHoursCellRow9));
        examInputRow10.addEventListener('input', () => calculateConvertedHours(examInputRow10, standardHourCellRow10, convertedHoursCellRow10));
        examInputRow11.addEventListener('input', () => calculateConvertedHours(examInputRow11, standardHourCellRow11, convertedHoursCellRow11));

        function handleSubmit(event) {
            event.preventDefault();
            const selectedYear = document.getElementById('selectedYear').value;
            const currentYear = new Date().getFullYear();
            const formData = new FormData(document.getElementById('examForm'));

            if (selectedYear != currentYear && selectedYear != currentYear - 1) {
                showPopup(`Không thể cập nhật dữ liệu cho năm ${selectedYear} vì chỉ được phép chỉnh sửa năm hiện tại hoặc năm hiện tại -1`, 'error');
            } else {
                // Gửi dữ liệu form trực tiếp không cần xác nhận
                submitFormData(formData, selectedYear);
            }
            return false;
        }

        // Hàm gửi dữ liệu form qua AJAX
        function submitFormData(formData, selectedYear) {
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('Cập nhật dữ liệu thành công') || data.includes('Lưu dữ liệu thành công')) {
                    showPopup(`Dữ liệu đã được cập nhật thành công cho năm ${selectedYear}`, 'success');
                } else if (data.includes('Không thể cập nhật dữ liệu')) {
                    showPopup(`Không thể cập nhật dữ liệu cho năm ${selectedYear}`, 'error');
                } else {
                    showPopup('Có lỗi xảy ra khi cập nhật dữ liệu!', 'error');
                }
            })
            .catch(error => {
                showPopup('Lỗi kết nối server: ' + error, 'error');
            });
        }

        // Load dữ liệu từ database khi trang được tải
        document.addEventListener('DOMContentLoaded', function() {
            // Khôi phục trạng thái của các radio buttons và tính toán giờ quy đổi
            const taskTypeRadios = document.querySelectorAll('input[type="radio"]');
            taskTypeRadios.forEach(radio => {
                if (radio.checked) {
                    const row = radio.closest('tr');
                    const standardHourCell = row.querySelector('.standard-hour');
                    const taskDinhMucCell = row.querySelector('.task-dinh-muc');
                    
                    updateHourCalculation(radio, standardHourCell, taskDinhMucCell);
                }
            });

            // Tính toán ban đầu cho tất cả các hàng
            examInputs.forEach((input, index) => {
                const standardHourCell = standardHourCells[index];
                const convertedHourCell = convertedHoursCells[index];
                calculateConvertedHours(input, standardHourCell, convertedHourCell);
            });
        });

        function updateHourCalculation(radio, standardHourCell, taskDinhMucCell) {
            const taskType = radio.value;
            const raDeHour = parseFloat(standardHourCell.getAttribute('data-ra-de'));
            const phanBienHour = parseFloat(standardHourCell.getAttribute('data-phan-bien'));
            const raDeDinhMuc = taskDinhMucCell.getAttribute('data-ra-de');
            const phanBienDinhMuc = taskDinhMucCell.getAttribute('data-phan-bien');

            if (taskType === 'ra_de') {
                standardHourCell.setAttribute('data-hour', raDeHour);
                standardHourCell.textContent = raDeHour.toFixed(2) + ' gc';
                taskDinhMucCell.textContent = raDeDinhMuc;
            } else if (taskType === 'phan_bien') {
                standardHourCell.setAttribute('data-hour', phanBienHour);
                standardHourCell.textContent = phanBienHour.toFixed(2) + ' gc';
                taskDinhMucCell.textContent = phanBienDinhMuc;
            }
        }

        document.getElementById('selectedYear').addEventListener('change', function() {
            // Cập nhật input hidden trong form chính
            document.querySelector('input[name="selected_year"]').value = this.value;
        });
    </script>
</body>
</html>