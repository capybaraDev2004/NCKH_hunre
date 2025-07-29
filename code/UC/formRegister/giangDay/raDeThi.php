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
$stmt = $conn->prepare("SELECT * FROM ra_de_thi WHERE employeeID = :employeeID AND result_year = :result_year");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
$questionData = $stmt->fetch(PDO::FETCH_ASSOC);

// Nếu không có dữ liệu, khởi tạo mảng trống
if (!$questionData) {
    $questionData = [
        'muc1_1_so_cau' => '',
        'muc1_2_so_cau' => '',
        'muc1_3_so_cau' => '',
        'muc1_4_so_cau' => '',
        'muc2_1_so_cau' => '',
        'muc2_2_so_cau' => '',
        'muc2_3_so_cau' => '',
        'muc3_1_so_cau' => '',
        'muc3_2_so_cau' => '',
        'muc3_3_so_cau' => '',
    ];
}

// Xử lý AJAX request để lấy dữ liệu form
if (isset($_POST['action']) && $_POST['action'] === 'get_question_data') {
    $year = $_POST['year'] ?? (date('Y') - 1);
    $stmt = $conn->prepare("SELECT * FROM ra_de_thi WHERE employeeID = :employeeID AND result_year = :result_year");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) {
        $data = [
            'muc1_1_so_cau' => '',
            'muc1_2_so_cau' => '',
            'muc1_3_so_cau' => '',
            'muc1_4_so_cau' => '',
            'muc2_1_so_cau' => '',
            'muc2_2_so_cau' => '',
            'muc2_3_so_cau' => '',
            'muc3_1_so_cau' => '',
            'muc3_2_so_cau' => '',
            'muc3_3_so_cau' => '',
        ];
    }
    echo json_encode($data);
    exit();
}

// Xử lý kiểm tra dữ liệu tồn tại (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'check_data_exists') {
    $year = $_POST['year'] ?? (date('Y') - 1);
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM ra_de_thi WHERE employeeID = :employeeID AND result_year = :result_year");
    $checkStmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
    $exists = $checkStmt->fetchColumn();
    echo json_encode(['exists' => $exists > 0]);
    exit();
}

// Xử lý dữ liệu từ form chính (khi submit cập nhật)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action']) && isset($_POST['muc1_1_so_cau'])) {
    $tongGioChuan = 0;

    // Lấy dữ liệu từ form
    $muc1_1_so_cau = floatval($_POST['muc1_1_so_cau'] ?? 0); // Đề thi tự luận
    $muc1_2_so_cau = floatval($_POST['muc1_2_so_cau'] ?? 0); // Câu hỏi trắc nghiệm (Đại học)
    $muc1_3_so_cau = floatval($_POST['muc1_3_so_cau'] ?? 0); // Câu hỏi trắc nghiệm (Thạc sỹ)
    $muc1_4_so_cau = floatval($_POST['muc1_4_so_cau'] ?? 0); // Đáp án cho câu hỏi trắc nghiệm
    $muc2_1_so_cau = floatval($_POST['muc2_1_so_cau'] ?? 0); // Đề thi vấn đáp (Đại học)
    $muc2_2_so_cau = floatval($_POST['muc2_2_so_cau'] ?? 0); // Đề thi vấn đáp (Thạc sỹ)
    $muc2_3_so_cau = floatval($_POST['muc2_3_so_cau'] ?? 0); // Đề thi vấn đáp (Tiến sỹ)
    $muc3_1_so_cau = floatval($_POST['muc3_1_so_cau'] ?? 0); // Câu hỏi ôn tập (Đại học)
    $muc3_2_so_cau = floatval($_POST['muc3_2_so_cau'] ?? 0); // Câu hỏi ôn tập (Thạc sỹ)
    $muc3_3_so_cau = floatval($_POST['muc3_3_so_cau'] ?? 0); // Câu hỏi ôn tập (Tiến sỹ)

    // Tính giờ quy đổi
    $muc1_1_gio = $muc1_1_so_cau * 1.0; // Đề thi tự luận: 1 gc/đề
    $muc1_2_gio = $muc1_2_so_cau * 0.04; // Câu hỏi trắc nghiệm (Đại học): 0.04 gc/câu
    $muc1_3_gio = $muc1_3_so_cau * 0.1; // Câu hỏi trắc nghiệm (Thạc sỹ): 0.1 gc/câu
    $muc1_4_gio = $muc1_4_so_cau * 0.02; // Đáp án trắc nghiệm: 0.02 gc/đáp án
    $muc2_1_gio = $muc2_1_so_cau * 0.5; // Đề thi vấn đáp (Đại học): 0.5 gc/đề
    $muc2_2_gio = $muc2_2_so_cau * 0.75; // Đề thi vấn đáp (Thạc sỹ): 0.75 gc/đề
    $muc2_3_gio = $muc2_3_so_cau * 1.0; // Đề thi vấn đáp (Tiến sỹ): 1.0 gc/đề
    $muc3_1_gio = $muc3_1_so_cau * 0.03; // Câu hỏi ôn tập (Đại học): 0.03 gc/câu
    $muc3_2_gio = $muc3_2_so_cau * 0.05; // Câu hỏi ôn tập (Thạc sỹ): 0.05 gc/câu
    $muc3_3_gio = $muc3_3_so_cau * 0.07; // Câu hỏi ôn tập (Tiến sỹ): 0.07 gc/câu

    $tongGioChuan = $muc1_1_gio + $muc1_2_gio + $muc1_3_gio + $muc1_4_gio + $muc2_1_gio + $muc2_2_gio + $muc2_3_gio + $muc3_1_gio + $muc3_2_gio + $muc3_3_gio;

    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM ra_de_thi WHERE employeeID = :employeeID AND result_year = :result_year");
    $checkStmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    $exists = $checkStmt->fetchColumn();

    if ($selectedYear != date('Y') && $selectedYear != date('Y') - 1) {
        echo "<script>showPopup('Không thể cập nhật dữ liệu cho năm $selectedYear vì chỉ được phép chỉnh sửa năm hiện tại hoặc năm hiện tại -1', 'error');</script>";
    } else {
        if ($exists) {
            $stmt = $conn->prepare("
                UPDATE ra_de_thi SET
                    muc1_1_so_cau = :muc1_1_so_cau, muc1_1_gio_quy_doi = :muc1_1_gio_quy_doi,
                    muc1_2_so_cau = :muc1_2_so_cau, muc1_2_gio_quy_doi = :muc1_2_gio_quy_doi,
                    muc1_3_so_cau = :muc1_3_so_cau, muc1_3_gio_quy_doi = :muc1_3_gio_quy_doi,
                    muc1_4_so_cau = :muc1_4_so_cau, muc1_4_gio_quy_doi = :muc1_4_gio_quy_doi,
                    muc2_1_so_cau = :muc2_1_so_cau, muc2_1_gio_quy_doi = :muc2_1_gio_quy_doi,
                    muc2_2_so_cau = :muc2_2_so_cau, muc2_2_gio_quy_doi = :muc2_2_gio_quy_doi,
                    muc2_3_so_cau = :muc2_3_so_cau, muc2_3_gio_quy_doi = :muc2_3_gio_quy_doi,
                    muc3_1_so_cau = :muc3_1_so_cau, muc3_1_gio_quy_doi = :muc3_1_gio_quy_doi,
                    muc3_2_so_cau = :muc3_2_so_cau, muc3_2_gio_quy_doi = :muc3_2_gio_quy_doi,
                    muc3_3_so_cau = :muc3_3_so_cau, muc3_3_gio_quy_doi = :muc3_3_gio_quy_doi,
                    tong_gio_quy_doi = :tong_gio_quy_doi, ngay_cap_nhat = CURRENT_TIMESTAMP
                WHERE employeeID = :employeeID AND result_year = :result_year
            ");
            $stmt->execute([
                ':employeeID' => $employeeID,
                ':result_year' => $selectedYear,
                ':muc1_1_so_cau' => $muc1_1_so_cau, ':muc1_1_gio_quy_doi' => $muc1_1_gio,
                ':muc1_2_so_cau' => $muc1_2_so_cau, ':muc1_2_gio_quy_doi' => $muc1_2_gio,
                ':muc1_3_so_cau' => $muc1_3_so_cau, ':muc1_3_gio_quy_doi' => $muc1_3_gio,
                ':muc1_4_so_cau' => $muc1_4_so_cau, ':muc1_4_gio_quy_doi' => $muc1_4_gio,
                ':muc2_1_so_cau' => $muc2_1_so_cau, ':muc2_1_gio_quy_doi' => $muc2_1_gio,
                ':muc2_2_so_cau' => $muc2_2_so_cau, ':muc2_2_gio_quy_doi' => $muc2_2_gio,
                ':muc2_3_so_cau' => $muc2_3_so_cau, ':muc2_3_gio_quy_doi' => $muc2_3_gio,
                ':muc3_1_so_cau' => $muc3_1_so_cau, ':muc3_1_gio_quy_doi' => $muc3_1_gio,
                ':muc3_2_so_cau' => $muc3_2_so_cau, ':muc3_2_gio_quy_doi' => $muc3_2_gio,
                ':muc3_3_so_cau' => $muc3_3_so_cau, ':muc3_3_gio_quy_doi' => $muc3_3_gio,
                ':tong_gio_quy_doi' => $tongGioChuan
            ]);
            echo "<script>showPopup('Cập nhật dữ liệu thành công cho năm $selectedYear', 'success');</script>";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO ra_de_thi (employeeID, result_year, muc1_1_so_cau, muc1_1_gio_quy_doi, muc1_2_so_cau, muc1_2_gio_quy_doi, muc1_3_so_cau, muc1_3_gio_quy_doi, muc1_4_so_cau, muc1_4_gio_quy_doi, muc2_1_so_cau, muc2_1_gio_quy_doi, muc2_2_so_cau, muc2_2_gio_quy_doi, muc2_3_so_cau, muc2_3_gio_quy_doi, muc3_1_so_cau, muc3_1_gio_quy_doi, muc3_2_so_cau, muc3_2_gio_quy_doi, muc3_3_so_cau, muc3_3_gio_quy_doi, tong_gio_quy_doi)
                VALUES (:employeeID, :result_year, :muc1_1_so_cau, :muc1_1_gio_quy_doi, :muc1_2_so_cau, :muc1_2_gio_quy_doi, :muc1_3_so_cau, :muc1_3_gio_quy_doi, :muc1_4_so_cau, :muc1_4_gio_quy_doi, :muc2_1_so_cau, :muc2_1_gio_quy_doi, :muc2_2_so_cau, :muc2_2_gio_quy_doi, :muc2_3_so_cau, :muc2_3_gio_quy_doi, :muc3_1_so_cau, :muc3_1_gio_quy_doi, :muc3_2_so_cau, :muc3_2_gio_quy_doi, :muc3_3_so_cau, :muc3_3_gio_quy_doi, :tong_gio_quy_doi)
            ");
            $stmt->execute([
                ':employeeID' => $employeeID,
                ':result_year' => $selectedYear,
                ':muc1_1_so_cau' => $muc1_1_so_cau, ':muc1_1_gio_quy_doi' => $muc1_1_gio,
                ':muc1_2_so_cau' => $muc1_2_so_cau, ':muc1_2_gio_quy_doi' => $muc1_2_gio,
                ':muc1_3_so_cau' => $muc1_3_so_cau, ':muc1_3_gio_quy_doi' => $muc1_3_gio,
                ':muc1_4_so_cau' => $muc1_4_so_cau, ':muc1_4_gio_quy_doi' => $muc1_4_gio,
                ':muc2_1_so_cau' => $muc2_1_so_cau, ':muc2_1_gio_quy_doi' => $muc2_1_gio,
                ':muc2_2_so_cau' => $muc2_2_so_cau, ':muc2_2_gio_quy_doi' => $muc2_2_gio,
                ':muc2_3_so_cau' => $muc2_3_so_cau, ':muc2_3_gio_quy_doi' => $muc2_3_gio,
                ':muc3_1_so_cau' => $muc3_1_so_cau, ':muc3_1_gio_quy_doi' => $muc3_1_gio,
                ':muc3_2_so_cau' => $muc3_2_so_cau, ':muc3_2_gio_quy_doi' => $muc3_2_gio,
                ':muc3_3_so_cau' => $muc3_3_so_cau, ':muc3_3_gio_quy_doi' => $muc3_3_gio,
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
    <title>Xây dựng ngân hàng câu hỏi thi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; }
        .teaching-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        .teaching-table th, .teaching-table td { border: 1px solid #d2ddfd; padding: 10px; text-align: left; vertical-align: top; }
        .teaching-table th { background-color: #223771; color: white; text-align: center; }
        .teaching-table td { background-color: #fff; }
        .teaching-table td:first-child { width: 5%; text-align: center; }
        .teaching-table td:nth-child(2) { width: 45%; }
        .teaching-table td:nth-child(3) { width: 15%; text-align: center; }
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
        .radethi a, .NV1{
            color: #f8843d !important;
        }
    </style>
</head>
<body>
    <h2 class="form-title">Xây dựng ngân hàng câu hỏi thi</h2>
    <!-- Form chọn năm riêng biệt -->
    <div style="text-align: center; margin-bottom: 15px;">
        <form method="POST" action="" id="yearForm">
            <label for="selected_year">Năm lưu dữ liệu: </label>
            <select name="selected_year" id="selectedYear" class="year-select" onchange="this.form.submit()">
                <?php
                $currentYear = date('Y');
                for ($i = $currentYear - 5; $i <= $currentYear; $i++) {
                    echo "<option value='$i'" . ($i == $selectedYear ? " selected" : "") . ">$i</option>";
                }
                ?>
            </select>
        </form>
    </div>

    <!-- Form chính riêng biệt -->
    <form id="questionForm" method="POST" action="" onsubmit="return handleSubmit(event)">
        <input type="hidden" name="selected_year" value="<?php echo $selectedYear; ?>">
        <table class="teaching-table">
            <thead>
                <tr>
                    <th>TT</th>
                    <th>Công việc</th>
                    <th>Định mức lao động</th>
                    <th>Giờ chuẩn</th>
                    <th>Tổng số</th>
                    <th>Giờ quy đổi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1.1</td>
                    <td>Đề thi tự luận</td>
                    <td>01 đề</td>
                    <td class="standard-hour" data-hour="1.0">1.0 gc</td>
                    <td><input type="number" name="muc1_1_so_cau" class="total-input question-input" min="0" step="1" placeholder="Tổng đề" value="<?php echo htmlspecialchars($questionData['muc1_1_so_cau']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>1.2</td>
                    <td>Câu hỏi trắc nghiệm (Đại học)</td>
                    <td>01 câu</td>
                    <td class="standard-hour" data-hour="0.04">0.04 gc</td>
                    <td><input type="number" name="muc1_2_so_cau" class="total-input question-input" min="0" step="1" placeholder="Tổng câu" value="<?php echo htmlspecialchars($questionData['muc1_2_so_cau']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>1.3</td>
                    <td>Câu hỏi trắc nghiệm (Thạc sỹ)</td>
                    <td>01 câu</td>
                    <td class="standard-hour" data-hour="0.1">0.1 gc</td>
                    <td><input type="number" name="muc1_3_so_cau" class="total-input question-input" min="0" step="1" placeholder="Tổng câu" value="<?php echo htmlspecialchars($questionData['muc1_3_so_cau']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>1.4</td>
                    <td>Đáp án cho câu hỏi trắc nghiệm</td>
                    <td>01 đáp án</td>
                    <td class="standard-hour" data-hour="0.02">0.02 gc</td>
                    <td><input type="number" name="muc1_4_so_cau" class="total-input question-input" min="0" step="1" placeholder="Tổng đáp án" value="<?php echo htmlspecialchars($questionData['muc1_4_so_cau']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>2.1</td>
                    <td>Đề thi vấn đáp (Đại học)</td>
                    <td>01 đề</td>
                    <td class="standard-hour" data-hour="0.5">0.5 gc</td>
                    <td><input type="number" name="muc2_1_so_cau" class="total-input question-input" min="0" step="1" placeholder="Tổng đề" value="<?php echo htmlspecialchars($questionData['muc2_1_so_cau']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>2.2</td>
                    <td>Đề thi vấn đáp (Thạc sỹ)</td>
                    <td>01 đề</td>
                    <td class="standard-hour" data-hour="0.75">0.75 gc</td>
                    <td><input type="number" name="muc2_2_so_cau" class="total-input question-input" min="0" step="1" placeholder="Tổng đề" value="<?php echo htmlspecialchars($questionData['muc2_2_so_cau']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>2.3</td>
                    <td>Đề thi vấn đáp (Tiến sỹ)</td>
                    <td>01 đề</td>
                    <td class="standard-hour" data-hour="1.0">1.0 gc</td>
                    <td><input type="number" name="muc2_3_so_cau" class="total-input question-input" min="0" step="1" placeholder="Tổng đề" value="<?php echo htmlspecialchars($questionData['muc2_3_so_cau']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>3.1</td>
                    <td>Câu hỏi ôn tập (Đại học)</td>
                    <td>01 câu</td>
                    <td class="standard-hour" data-hour="0.03">0.03 gc</td>
                    <td><input type="number" name="muc3_1_so_cau" class="total-input question-input" min="0" step="1" placeholder="Tổng câu" value="<?php echo htmlspecialchars($questionData['muc3_1_so_cau']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>3.2</td>
                    <td>Câu hỏi ôn tập (Thạc sỹ)</td>
                    <td>01 câu</td>
                    <td class="standard-hour" data-hour="0.05">0.05 gc</td>
                    <td><input type="number" name="muc3_2_so_cau" class="total-input question-input" min="0" step="1" placeholder="Tổng câu" value="<?php echo htmlspecialchars($questionData['muc3_2_so_cau']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>3.3</td>
                    <td>Câu hỏi ôn tập (Tiến sỹ)</td>
                    <td>01 câu</td>
                    <td class="standard-hour" data-hour="0.07">0.07 gc</td>
                    <td><input type="number" name="muc3_3_so_cau" class="total-input question-input" min="0" step="1" placeholder="Tổng câu" value="<?php echo htmlspecialchars($questionData['muc3_3_so_cau']); ?>"></td>
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
        const questionInputs = document.querySelectorAll('.question-input');
        const standardHourCells = document.querySelectorAll('.standard-hour');
        const convertedHoursCells = document.querySelectorAll('.converted-hours');

        function calculateConvertedHours() {
            let tongGio = 0;
            questionInputs.forEach((input, index) => {
                const value = parseFloat(input.value) || 0;
                const standardHour = parseFloat(standardHourCells[index].getAttribute('data-hour'));
                const result = value * standardHour;
                convertedHoursCells[index].textContent = result.toFixed(2) + ' gc';
                tongGio += result;
            });
            document.getElementById('tongGioChuan').textContent = tongGio.toFixed(2) + ' gc';
        }

        questionInputs.forEach((input, index) => {
            input.addEventListener('input', calculateConvertedHours);
        });

        function showPopup(message, type) {
            alert(message); // Thay bằng hàm popup tùy chỉnh nếu cần
        }

        function handleSubmit(event) {
            event.preventDefault();
            const selectedYear = document.getElementById('selectedYear').value;
            const currentYear = new Date().getFullYear();
            const currentYearMinusOne = currentYear - 1;
            const formData = new FormData(document.getElementById('questionForm'));

            if (selectedYear != currentYear && selectedYear != currentYearMinusOne) {
                showPopup(`Không thể cập nhật dữ liệu cho năm ${selectedYear} vì chỉ được phép chỉnh sửa năm hiện tại (${currentYear}) hoặc năm hiện tại -1 (${currentYearMinusOne})`, 'error');
            } else {
                // Gửi dữ liệu trực tiếp không cần xác nhận
                submitFormData(formData, selectedYear);
            }
            return false;
        }

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

        // Thêm đoạn này để tính toán giờ quy đổi khi trang được tải
        document.addEventListener('DOMContentLoaded', function() {
            calculateConvertedHours();
        });
    </script>
</body>
</html>