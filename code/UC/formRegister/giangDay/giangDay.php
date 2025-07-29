<?php
// Kiểm tra nếu session chưa được khởi tạo thì mới gọi session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sửa đường dẫn tới file connection.php (đảm bảo đúng với cấu trúc thư mục của bạn)
require_once __DIR__ . '../../../../connection/connection.php';

if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login/login.php");
    exit();
}

$employeeID = $_SESSION['employeeID'];
$selectedYear = isset($_POST['selected_year']) ? $_POST['selected_year'] : (date('Y') - 1);

// Xử lý AJAX request để lấy dữ liệu giảng dạy
if (isset($_POST['action']) && $_POST['action'] === 'get_teaching_data') {
    $year = $_POST['year'] ?? (date('Y') - 1);
    $stmt = $conn->prepare("SELECT * FROM giangday WHERE employeeID = :employeeID AND result_year = :result_year");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) {
        $data = [
            'muc1_tong_tiet' => '',
            'muc1_sv_tren_40' => 0,
            'muc1_tong_sv' => '',
            'muc2_tong_tiet' => '',
            'muc3_tong_tiet' => '',
            'muc4_tong_tiet' => '',
            'muc5_tong_tiet' => '',
            'muc5_sv_tren_40' => 0,
            'muc5_tong_sv' => '',
            'muc6_tong_tiet' => '',
            'muc6_sv_tren_30' => 0,
            'muc6_tong_sv' => '',
            'muc7_tong_ngay' => '',
            'muc7_sv_tren_25' => 0,
            'muc7_tong_sv' => '',
            'muc8_tong_tin_chi' => '',
            'muc9_tong_ngay' => '',
            'muc9_sv_tren_40' => 0,
            'muc9_tong_sv' => '',
            'muc9_them_gv' => 0,
        ];
    }
    echo json_encode($data);
    exit();
}

// Xử lý AJAX request để kiểm tra dữ liệu tồn tại
if (isset($_POST['action']) && $_POST['action'] === 'check_data_exists') {
    $year = $_POST['year'] ?? (date('Y') - 1);
    $stmt = $conn->prepare("SELECT COUNT(*) FROM giangday WHERE employeeID = :employeeID AND result_year = :result_year");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
    $exists = $stmt->fetchColumn();
    echo json_encode(['exists' => $exists > 0]);
    exit();
}

// Xử lý AJAX request để lấy dữ liệu giảng dạy từ bảng schedule
if (isset($_POST['action']) && $_POST['action'] === 'get_schedule_data') {
    $year = $_POST['year'] ?? (date('Y') - 1);
    $stmt = $conn->prepare("SELECT total_period FROM schedule WHERE employeeID = :employeeID AND year = :year");
    $stmt->execute([':employeeID' => $employeeID, ':year' => $year]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Nếu không có dữ liệu, trả về giá trị mặc định
    if (!$data) {
        $data = ['total_period' => ''];
    }
    echo json_encode($data);
    exit();
}

// Xử lý dữ liệu từ form giảng dạy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $tongGioChuan = 0;

    // Giả định hàm tinhGioQuyDoi() đã được định nghĩa trong formRegister.php
    $muc1_gio = tinhGioQuyDoi($_POST['muc1_tong_tiet'] ?? 0, 1.0, isset($_POST['muc1_sv_tren_40']), $_POST['muc1_tong_sv'] ?? 0, 1.5, false, 40);
    $muc2_gio = (floatval($_POST['muc2_tong_tiet'] ?? 0)) * 1.95;
    $muc3_gio = (floatval($_POST['muc3_tong_tiet'] ?? 0)) * 1.95;
    $muc4_gio = (floatval($_POST['muc4_tong_tiet'] ?? 0)) * 1.95;
    $muc5_gio = tinhGioQuyDoi($_POST['muc5_tong_tiet'] ?? 0, 0.55, isset($_POST['muc5_sv_tren_40']), $_POST['muc5_tong_sv'] ?? 0, 1.0, false, 40);
    $muc6_gio = tinhGioQuyDoi($_POST['muc6_tong_tiet'] ?? 0, 0.55, isset($_POST['muc6_sv_tren_30']), $_POST['muc6_tong_sv'] ?? 0, 1.0, false, 30);
    $muc7_gio = tinhGioQuyDoi($_POST['muc7_tong_ngay'] ?? 0, 2.0, isset($_POST['muc7_sv_tren_25']), $_POST['muc7_tong_sv'] ?? 0, 2.5, false, 25);
    $muc8_gio = (floatval($_POST['muc8_tong_tin_chi'] ?? 0)) * 12.0;
    $muc9_gio = tinhGioQuyDoi($_POST['muc9_tong_ngay'] ?? 0, 5.0, isset($_POST['muc9_sv_tren_40']), $_POST['muc9_tong_sv'] ?? 0, PHP_INT_MAX, isset($_POST['muc9_them_gv']), 40);

    $tongGioChuan = $muc1_gio + $muc2_gio + $muc3_gio + $muc4_gio + $muc5_gio + $muc6_gio + $muc7_gio + $muc8_gio + $muc9_gio;

    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM giangday WHERE employeeID = :employeeID AND result_year = :result_year");
    $checkStmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    $exists = $checkStmt->fetchColumn();

    if ($selectedYear != date('Y') && $selectedYear != date('Y') - 1) {
        echo "<script>showPopup('Không thể cập nhật dữ liệu cho năm $selectedYear vì chỉ được phép chỉnh sửa năm hiện tại hoặc năm hiện tại -1', 'error');</script>";
    } else {
        if ($exists) {
            $stmt = $conn->prepare("
                UPDATE giangday SET
                    muc1_tong_tiet = :muc1_tong_tiet, muc1_sv_tren_40 = :muc1_sv_tren_40, muc1_tong_sv = :muc1_tong_sv, muc1_gio_quy_doi = :muc1_gio_quy_doi,
                    muc2_tong_tiet = :muc2_tong_tiet, muc2_gio_quy_doi = :muc2_gio_quy_doi,
                    muc3_tong_tiet = :muc3_tong_tiet, muc3_gio_quy_doi = :muc3_gio_quy_doi,
                    muc4_tong_tiet = :muc4_tong_tiet, muc4_gio_quy_doi = :muc4_gio_quy_doi,
                    muc5_tong_tiet = :muc5_tong_tiet, muc5_sv_tren_40 = :muc5_sv_tren_40, muc5_tong_sv = :muc5_tong_sv, muc5_gio_quy_doi = :muc5_gio_quy_doi,
                    muc6_tong_tiet = :muc6_tong_tiet, muc6_sv_tren_30 = :muc6_sv_tren_30, muc6_tong_sv = :muc6_tong_sv, muc6_gio_quy_doi = :muc6_gio_quy_doi,
                    muc7_tong_ngay = :muc7_tong_ngay, muc7_sv_tren_25 = :muc7_sv_tren_25, muc7_tong_sv = :muc7_tong_sv, muc7_gio_quy_doi = :muc7_gio_quy_doi,
                    muc8_tong_tin_chi = :muc8_tong_tin_chi, muc8_gio_quy_doi = :muc8_gio_quy_doi,
                    muc9_tong_ngay = :muc9_tong_ngay, muc9_sv_tren_40 = :muc9_sv_tren_40, muc9_tong_sv = :muc9_tong_sv, muc9_them_gv = :muc9_them_gv, muc9_gio_quy_doi = :muc9_gio_quy_doi,
                    tong_gio_quy_doi = :tong_gio_quy_doi, ngay_cap_nhat = CURRENT_TIMESTAMP
                WHERE employeeID = :employeeID AND result_year = :result_year
            ");
            $stmt->execute([
                ':employeeID' => $employeeID,
                ':result_year' => $selectedYear,
                ':muc1_tong_tiet' => $_POST['muc1_tong_tiet'] ?? 0, ':muc1_sv_tren_40' => isset($_POST['muc1_sv_tren_40']) ? 1 : 0, ':muc1_tong_sv' => $_POST['muc1_tong_sv'] ?? 0, ':muc1_gio_quy_doi' => $muc1_gio,
                ':muc2_tong_tiet' => $_POST['muc2_tong_tiet'] ?? 0, ':muc2_gio_quy_doi' => $muc2_gio,
                ':muc3_tong_tiet' => $_POST['muc3_tong_tiet'] ?? 0, ':muc3_gio_quy_doi' => $muc3_gio,
                ':muc4_tong_tiet' => $_POST['muc4_tong_tiet'] ?? 0, ':muc4_gio_quy_doi' => $muc4_gio,
                ':muc5_tong_tiet' => $_POST['muc5_tong_tiet'] ?? 0, ':muc5_sv_tren_40' => isset($_POST['muc5_sv_tren_40']) ? 1 : 0, ':muc5_tong_sv' => $_POST['muc5_tong_sv'] ?? 0, ':muc5_gio_quy_doi' => $muc5_gio,
                ':muc6_tong_tiet' => $_POST['muc6_tong_tiet'] ?? 0, ':muc6_sv_tren_30' => isset($_POST['muc6_sv_tren_30']) ? 1 : 0, ':muc6_tong_sv' => $_POST['muc6_tong_sv'] ?? 0, ':muc6_gio_quy_doi' => $muc6_gio,
                ':muc7_tong_ngay' => $_POST['muc7_tong_ngay'] ?? 0, ':muc7_sv_tren_25' => isset($_POST['muc7_sv_tren_25']) ? 1 : 0, ':muc7_tong_sv' => $_POST['muc7_tong_sv'] ?? 0, ':muc7_gio_quy_doi' => $muc7_gio,
                ':muc8_tong_tin_chi' => $_POST['muc8_tong_tin_chi'] ?? 0, ':muc8_gio_quy_doi' => $muc8_gio,
                ':muc9_tong_ngay' => $_POST['muc9_tong_ngay'] ?? 0, ':muc9_sv_tren_40' => isset($_POST['muc9_sv_tren_40']) ? 1 : 0, ':muc9_tong_sv' => $_POST['muc9_tong_sv'] ?? 0, ':muc9_them_gv' => isset($_POST['muc9_them_gv']) ? 1 : 0, ':muc9_gio_quy_doi' => $muc9_gio,
                ':tong_gio_quy_doi' => $tongGioChuan
            ]);
            echo "<script>showPopup('Cập nhật dữ liệu thành công cho năm $selectedYear', 'success');</script>";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO giangday (employeeID, result_year, muc1_tong_tiet, muc1_sv_tren_40, muc1_tong_sv, muc1_gio_quy_doi, muc2_tong_tiet, muc2_gio_quy_doi, muc3_tong_tiet, muc3_gio_quy_doi, muc4_tong_tiet, muc4_gio_quy_doi, muc5_tong_tiet, muc5_sv_tren_40, muc5_tong_sv, muc5_gio_quy_doi, muc6_tong_tiet, muc6_sv_tren_30, muc6_tong_sv, muc6_gio_quy_doi, muc7_tong_ngay, muc7_sv_tren_25, muc7_tong_sv, muc7_gio_quy_doi, muc8_tong_tin_chi, muc8_gio_quy_doi, muc9_tong_ngay, muc9_sv_tren_40, muc9_tong_sv, muc9_them_gv, muc9_gio_quy_doi, tong_gio_quy_doi)
                VALUES (:employeeID, :result_year, :muc1_tong_tiet, :muc1_sv_tren_40, :muc1_tong_sv, :muc1_gio_quy_doi, :muc2_tong_tiet, :muc2_gio_quy_doi, :muc3_tong_tiet, :muc3_gio_quy_doi, :muc4_tong_tiet, :muc4_gio_quy_doi, :muc5_tong_tiet, :muc5_sv_tren_40, :muc5_tong_sv, :muc5_gio_quy_doi, :muc6_tong_tiet, :muc6_sv_tren_30, :muc6_tong_sv, :muc6_gio_quy_doi, :muc7_tong_ngay, :muc7_sv_tren_25, :muc7_tong_sv, :muc7_gio_quy_doi, :muc8_tong_tin_chi, :muc8_gio_quy_doi, :muc9_tong_ngay, :muc9_sv_tren_40, :muc9_tong_sv, :muc9_them_gv, :muc9_gio_quy_doi, :tong_gio_quy_doi)
            ");
            $stmt->execute([
                ':employeeID' => $employeeID,
                ':result_year' => $selectedYear,
                ':muc1_tong_tiet' => $_POST['muc1_tong_tiet'] ?? 0, ':muc1_sv_tren_40' => isset($_POST['muc1_sv_tren_40']) ? 1 : 0, ':muc1_tong_sv' => $_POST['muc1_tong_sv'] ?? 0, ':muc1_gio_quy_doi' => $muc1_gio,
                ':muc2_tong_tiet' => $_POST['muc2_tong_tiet'] ?? 0, ':muc2_gio_quy_doi' => $muc2_gio,
                ':muc3_tong_tiet' => $_POST['muc3_tong_tiet'] ?? 0, ':muc3_gio_quy_doi' => $muc3_gio,
                ':muc4_tong_tiet' => $_POST['muc4_tong_tiet'] ?? 0, ':muc4_gio_quy_doi' => $muc4_gio,
                ':muc5_tong_tiet' => $_POST['muc5_tong_tiet'] ?? 0, ':muc5_sv_tren_40' => isset($_POST['muc5_sv_tren_40']) ? 1 : 0, ':muc5_tong_sv' => $_POST['muc5_tong_sv'] ?? 0, ':muc5_gio_quy_doi' => $muc5_gio,
                ':muc6_tong_tiet' => $_POST['muc6_tong_tiet'] ?? 0, ':muc6_sv_tren_30' => isset($_POST['muc6_sv_tren_30']) ? 1 : 0, ':muc6_tong_sv' => $_POST['muc6_tong_sv'] ?? 0, ':muc6_gio_quy_doi' => $muc6_gio,
                ':muc7_tong_ngay' => $_POST['muc7_tong_ngay'] ?? 0, ':muc7_sv_tren_25' => isset($_POST['muc7_sv_tren_25']) ? 1 : 0, ':muc7_tong_sv' => $_POST['muc7_tong_sv'] ?? 0, ':muc7_gio_quy_doi' => $muc7_gio,
                ':muc8_tong_tin_chi' => $_POST['muc8_tong_tin_chi'] ?? 0, ':muc8_gio_quy_doi' => $muc8_gio,
                ':muc9_tong_ngay' => $_POST['muc9_tong_ngay'] ?? 0, ':muc9_sv_tren_40' => isset($_POST['muc9_sv_tren_40']) ? 1 : 0, ':muc9_tong_sv' => $_POST['muc9_tong_sv'] ?? 0, ':muc9_them_gv' => isset($_POST['muc9_them_gv']) ? 1 : 0, ':muc9_gio_quy_doi' => $muc9_gio,
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
    <title>Đăng ký giảng dạy</title>
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
        .total-input, .day-input { width: 100px; padding: 5px; text-align: center; margin-bottom: 5px; }
        .student-input, .additional-gv-checkbox { width: 80px; padding: 5px; text-align: center; display: none; }
        .year-select { padding: 8px; font-size: 14px; border-radius: 4px; margin-bottom: 10px; }
        button { background-color: #223771; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.3s ease; }
        button:hover { background-color: #1a2a5b; }
        button:active { background-color: #142146; }
        .giangday a, .NV1{
            color: #f8843d !important;
        }
        .readonly-input {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        .readonly-checkbox {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <h2 class="form-title">Tổng hợp giảng dạy trực tiếp</h2>
    <form id="teachingForm" method="POST" action="" onsubmit="return handleSubmit(event)">
        <div style="text-align: center; margin-bottom: 15px;">
            <label for="selected_year">Năm lưu dữ liệu: </label>
            <select name="selected_year" class="year-select" id="selectedYear">
                <?php
                $currentYear = date('Y');
                $selectedYear = isset($_POST['selected_year']) ? $_POST['selected_year'] : ($currentYear);
                for ($i = $currentYear - 5; $i <= $currentYear; $i++) {
                    echo "<option value='$i'" . ($selectedYear == $i ? " selected" : "") . ">$i</option>";
                }
                ?>
            </select>
        </div>
        <table class="teaching-table">
            <thead>
                <tr>
                    <th>TT</th>
                    <th>Công việc</th>
                    <th>Định mức lao động</th>
                    <th>Giờ chuẩn</th>
                    <th>Tổng số tiết</th>
                    <th>Tổng giờ quy đổi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>Giảng dạy lý thuyết trực tiếp trên lớp hoặc trực tuyến cho đại học; Giảng dạy lý thuyết kết hợp làm mẫu ở thao trường, bãi tập môn học GQOP-AN, giảng dạy môn GDTC, lớp có số SV ≤ 40<br>Giảng dạy lý thuyết trực tiếp trên lớp hoặc trực tuyến cho đại học; Giảng dạy lý thuyết kết hợp làm mẫu ở thao trường, bãi tập môn học GQOP-AN, giảng dạy môn GDTC, lớp có số SV > 40, cứ thêm 01 SV thì tính thêm 0,01 giờ, tối đa giờ chuẩn quy đổi không vượt quá 1,5 giờ giảng dạy</td>
                    <td>1 tiết</td>
                    <td class="standard-hour" data-hour="1.0">1.0 - 1.5 gc</td>
                    <td>
                        <input type="number" name="muc1_tong_tiet" class="total-input period-input readonly-input" min="0" step="1" placeholder="Tổng tiết" readonly>
                        <br>
                        <label><input type="checkbox" name="muc1_sv_tren_40" class="sv-checkbox sv-checkbox-1 readonly-checkbox" disabled> Tổng SV > 40</label>
                        <input type="number" name="muc1_tong_sv" class="student-input sv-input sv-input-1 readonly-input" placeholder="Tổng SV" min="41" step="1" readonly>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>Giảng dạy chuyên đề, lý thuyết cho các lớp đào tạo trình độ thạc sĩ</td>
                    <td>1 tiết</td>
                    <td class="standard-hour" data-hour="1.95">1.95 gc</td>
                    <td><input type="number" name="muc2_tong_tiet" class="total-input period-input" min="0" step="1" placeholder="Tổng tiết"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>3</td>
                    <td>Giảng dạy bằng tiếng Anh (đối với các học phần tiếng Anh chuyên ngành; học phần chuyên ngành giảng dạy bằng tiếng Anh) cho các lớp đào tạo trình độ đại học, thạc sĩ (không áp dụng đối với GV bộ môn ngoại ngữ)</td>
                    <td>1 tiết</td>
                    <td class="standard-hour" data-hour="1.95">1.95 gc</td>
                    <td><input type="number" name="muc3_tong_tiet" class="total-input period-input" min="0" step="1" placeholder="Tổng tiết"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>4</td>
                    <td>Giảng dạy bằng tiếng Anh (đối với các học phần tiếng Anh chuyên ngành; học phần chuyên ngành giảng dạy bằng tiếng Anh) cho các lớp đào tạo trình độ đại học, thạc sĩ (không áp dụng đối với GV bộ môn ngoại ngữ)</td>
                    <td>1 tiết</td>
                    <td class="standard-hour" data-hour="1.95">1.95 gc</td>
                    <td><input type="number" name="muc4_tong_tiet" class="total-input period-input" min="0" step="1" placeholder="Tổng tiết"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>5</td>
                    <td>Hướng dẫn thực hành (Học phần có cả lý thuyết và thực hành):<br>- Lớp (nhóm) có số SV ≤ 40: 1 tiết thực hành = 0.55 gc<br>- Lớp (nhóm) có số SV > 40, cứ thêm 01 SV thì tính thêm 0.01 gc, nhưng không quá 1.0 gc</td>
                    <td>1 tiết thực hành</td>
                    <td class="standard-hour" data-hour="0.55">0.55 - 1.0 gc</td>
                    <td>
                        <input type="number" name="muc5_tong_tiet" class="total-input period-input readonly-input" min="0" step="1" placeholder="Tổng tiết" readonly>
                        <br>
                        <label><input type="checkbox" name="muc5_sv_tren_40" class="sv-checkbox sv-checkbox-5 readonly-checkbox" disabled> Tổng SV > 40</label>
                        <input type="number" name="muc5_tong_sv" class="student-input sv-input sv-input-5 readonly-input" placeholder="Tổng SV" min="41" step="1" readonly>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>6</td>
                    <td>Hướng dẫn thực hành (Học phần chỉ có thực hành, có sử dụng thiết bị và hóa chất trong phòng thí nghiệm môi trường):<br>- Lớp (nhóm) có số SV ≤ 30: 1 tiết thực hành = 0.55 gc<br>- Lớp (nhóm) có số SV > 30, cứ thêm 01 SV thì tính thêm 0.01 gc, nhưng không quá 1.0 gc</td>
                    <td>1 tiết thực hành</td>
                    <td class="standard-hour" data-hour="0.55">0.55 - 1.0 gc</td>
                    <td>
                        <input type="number" name="muc6_tong_tiet" class="total-input period-input readonly-input" min="0" step="1" placeholder="Tổng tiết" readonly>
                        <br>
                        <label><input type="checkbox" name="muc6_sv_tren_30" class="sv-checkbox sv-checkbox-6 readonly-checkbox" disabled> Tổng SV > 30</label>
                        <input type="number" name="muc6_tong_sv" class="student-input sv-input sv-input-6 readonly-input" placeholder="Tổng SV" min="31" step="1" readonly>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>7</td>
                    <td>Hướng dẫn thực tập môn học:<br>- Lớp (nhóm) có số SV ≤ 25: 1 ngày làm việc = 2.0 gc<br>- Lớp (nhóm) có số SV > 25, cứ thêm 01 SV thì tính thêm 0.01 gc, nhưng không quá 2.5 gc</td>
                    <td>1 ngày làm việc</td>
                    <td class="standard-hour" data-hour="2.0">2.0 - 2.5 gc</td>
                    <td>
                        <input type="number" name="muc7_tong_ngay" class="total-input day-input" min="0" step="1" placeholder="Tổng ngày">
                        <br>
                        <label><input type="checkbox" name="muc7_sv_tren_25" class="sv-checkbox sv-checkbox-7"> Tổng SV > 25</label>
                        <input type="number" name="muc7_tong_sv" class="student-input sv-input sv-input-7" placeholder="Tổng SV" min="26" step="1">
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>8</td>
                    <td>Hướng dẫn các học phần niên luận, đồ án môn học, báo cáo chuyên đề trình độ đại học, thạc sĩ cho 1 lớp</td>
                    <td>1 tín chỉ</td>
                    <td class="standard-hour" data-hour="12.0">12.0 gc</td>
                    <td><input type="number" name="muc8_tong_tin_chi" class="total-input period-input" min="0" step="1" placeholder="Tín chỉ"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>9</td>
                    <td>Các học phần lý thuyết có đi thực tế, tham quan nhân thực tại cơ sở, đi tích, báo tăng...:<br>- Nếu lớp ≤ 40 SV, cứ 01 giảng viên hướng dẫn = 5.0 gc/ngày<br>- Nếu lớp > 40 SV, cứ thêm 01 SV thì tính thêm 0.02 gc/ngày, tổng giờ chuẩn duy trì tính chung cho 02 giảng viên (nếu có thêm 1 giảng viên)</td>
                    <td>1 ngày</td>
                    <td class="standard-hour" data-hour="5.0">5.0 gc</td>
                    <td>
                        <input type="number" name="muc9_tong_ngay" class="total-input day-input" min="0" step="1" placeholder="Tổng ngày">
                        <br>
                        <label><input type="checkbox" name="muc9_sv_tren_40" class="sv-checkbox sv-checkbox-9"> Tổng SV > 40</label>
                        <input type="number" name="muc9_tong_sv" class="student-input sv-input sv-input-9" placeholder="Tổng SV" min="41" step="1">
                        <br>
                        <label class="additional-gv-checkbox additional-gv-checkbox-9"><input type="checkbox" name="muc9_them_gv" class="additional-gv additional-gv-9"> Thêm 1 giảng viên</label>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
            </tbody>
        </table>
        <div style="text-align: center; margin-top: 20px;">
            <button type="submit">Cập nhật</button>
            <p style="margin-top: 10px;">Tổng số giờ chuẩn hiện tại: <strong id="tongGioChuan">0 gc</strong></p>
        </div>
    </form>

    <script>
        const periodInputs = document.querySelectorAll('.period-input');
        const dayInputs = document.querySelectorAll('.day-input');
        const convertedHoursCells = document.querySelectorAll('.converted-hours');
        const standardHourCells = document.querySelectorAll('.standard-hour');

        const svCheckbox1 = document.querySelector('.sv-checkbox-1'), svInput1 = document.querySelector('.sv-input-1'), periodInputRow1 = periodInputs[0], standardHourCellRow1 = standardHourCells[0], convertedHoursCellRow1 = convertedHoursCells[0];
        const periodInputRow2 = periodInputs[1], standardHourCellRow2 = standardHourCells[1], convertedHoursCellRow2 = convertedHoursCells[1];
        const periodInputRow3 = periodInputs[2], standardHourCellRow3 = standardHourCells[2], convertedHoursCellRow3 = convertedHoursCells[2];
        const periodInputRow4 = periodInputs[3], standardHourCellRow4 = standardHourCells[3], convertedHoursCellRow4 = convertedHoursCells[3];
        const svCheckbox5 = document.querySelector('.sv-checkbox-5'), svInput5 = document.querySelector('.sv-input-5'), periodInputRow5 = periodInputs[4], standardHourCellRow5 = standardHourCells[4], convertedHoursCellRow5 = convertedHoursCells[4];
        const svCheckbox6 = document.querySelector('.sv-checkbox-6'), svInput6 = document.querySelector('.sv-input-6'), periodInputRow6 = periodInputs[5], standardHourCellRow6 = standardHourCells[5], convertedHoursCellRow6 = convertedHoursCells[5];
        const svCheckbox7 = document.querySelector('.sv-checkbox-7'), svInput7 = document.querySelector('.sv-input-7'), dayInputRow7 = dayInputs[0], standardHourCellRow7 = standardHourCells[6], convertedHoursCellRow7 = convertedHoursCells[6];
        const periodInputRow8 = periodInputs[6], standardHourCellRow8 = standardHourCells[7], convertedHoursCellRow8 = convertedHoursCells[7];
        const svCheckbox9 = document.querySelector('.sv-checkbox-9'), svInput9 = document.querySelector('.sv-input-9'), additionalGvCheckbox9 = document.querySelector('.additional-gv-9'), additionalGvLabel9 = document.querySelector('.additional-gv-checkbox-9'), dayInputRow9 = dayInputs[1], standardHourCellRow9 = standardHourCells[8], convertedHoursCellRow9 = convertedHoursCells[8];

        function calculateConvertedHours(input, standardHourCell, convertedHourCell, rowIndex) {
            const periods = parseFloat(input.value) || 0;
            let standardHour = parseFloat(standardHourCell.getAttribute('data-hour')), totalStudents = 0;

            if (rowIndex === 0 && svCheckbox1.checked) {
                totalStudents = parseFloat(svInput1.value) || 40;
                if (totalStudents > 40) {
                    standardHour = Math.min(1.5, 1.0 + 0.01 * (totalStudents - 40));
                    standardHourCell.textContent = standardHour.toFixed(2) + ' gc';
                }
            } else if (rowIndex === 0) standardHourCell.textContent = '1.0 - 1.5 gc';

            if (rowIndex === 4 && svCheckbox5.checked) {
                totalStudents = parseFloat(svInput5.value) || 40;
                if (totalStudents > 40) {
                    standardHour = Math.min(1.0, 0.55 + 0.01 * (totalStudents - 40));
                    standardHourCell.textContent = standardHour.toFixed(2) + ' gc';
                }
            } else if (rowIndex === 4) standardHourCell.textContent = '0.55 - 1.0 gc';

            if (rowIndex === 5 && svCheckbox6.checked) {
                totalStudents = parseFloat(svInput6.value) || 30;
                if (totalStudents > 30) {
                    standardHour = Math.min(1.0, 0.55 + 0.01 * (totalStudents - 30));
                    standardHourCell.textContent = standardHour.toFixed(2) + ' gc';
                }
            } else if (rowIndex === 5) standardHourCell.textContent = '0.55 - 1.0 gc';

            if (rowIndex === 6 && svCheckbox7.checked) {
                totalStudents = parseFloat(svInput7.value) || 25;
                if (totalStudents > 25) {
                    standardHour = Math.min(2.5, 2.0 + 0.01 * (totalStudents - 25));
                    standardHourCell.textContent = standardHour.toFixed(2) + ' gc';
                }
            } else if (rowIndex === 6) standardHourCell.textContent = '2.0 - 2.5 gc';

            if (rowIndex === 8 && svCheckbox9.checked) {
                totalStudents = parseFloat(svInput9.value) || 40;
                if (totalStudents > 40) {
                    standardHour = 5.0 + 0.02 * (totalStudents - 40);
                    if (additionalGvCheckbox9.checked) {
                        standardHour /= 2;
                        standardHourCell.textContent = standardHour.toFixed(2) + ' gc (chia 2 GV)';
                    } else standardHourCell.textContent = standardHour.toFixed(2) + ' gc';
                }
            } else if (rowIndex === 8) standardHourCell.textContent = '5.0 gc';

            const result = periods * standardHour;
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

        svCheckbox1.addEventListener('change', () => { svInput1.style.display = svCheckbox1.checked ? 'inline-block' : 'none'; calculateConvertedHours(periodInputRow1, standardHourCellRow1, convertedHoursCellRow1, 0); });
        svInput1.addEventListener('input', () => calculateConvertedHours(periodInputRow1, standardHourCellRow1, convertedHoursCellRow1, 0));
        periodInputRow1.addEventListener('input', () => calculateConvertedHours(periodInputRow1, standardHourCellRow1, convertedHoursCellRow1, 0));

        periodInputRow2.addEventListener('input', () => calculateConvertedHours(periodInputRow2, standardHourCellRow2, convertedHoursCellRow2, 1));
        periodInputRow3.addEventListener('input', () => calculateConvertedHours(periodInputRow3, standardHourCellRow3, convertedHoursCellRow3, 2));
        periodInputRow4.addEventListener('input', () => calculateConvertedHours(periodInputRow4, standardHourCellRow4, convertedHoursCellRow4, 3));

        svCheckbox5.addEventListener('change', () => { svInput5.style.display = svCheckbox5.checked ? 'inline-block' : 'none'; calculateConvertedHours(periodInputRow5, standardHourCellRow5, convertedHoursCellRow5, 4); });
        svInput5.addEventListener('input', () => calculateConvertedHours(periodInputRow5, standardHourCellRow5, convertedHoursCellRow5, 4));
        periodInputRow5.addEventListener('input', () => calculateConvertedHours(periodInputRow5, standardHourCellRow5, convertedHoursCellRow5, 4));

        svCheckbox6.addEventListener('change', () => { svInput6.style.display = svCheckbox6.checked ? 'inline-block' : 'none'; calculateConvertedHours(periodInputRow6, standardHourCellRow6, convertedHoursCellRow6, 5); });
        svInput6.addEventListener('input', () => calculateConvertedHours(periodInputRow6, standardHourCellRow6, convertedHoursCellRow6, 5));
        periodInputRow6.addEventListener('input', () => calculateConvertedHours(periodInputRow6, standardHourCellRow6, convertedHoursCellRow6, 5));

        svCheckbox7.addEventListener('change', () => { svInput7.style.display = svCheckbox7.checked ? 'inline-block' : 'none'; calculateConvertedHours(dayInputRow7, standardHourCellRow7, convertedHoursCellRow7, 6); });
        svInput7.addEventListener('input', () => calculateConvertedHours(dayInputRow7, standardHourCellRow7, convertedHoursCellRow7, 6));
        dayInputRow7.addEventListener('input', () => calculateConvertedHours(dayInputRow7, standardHourCellRow7, convertedHoursCellRow7, 6));

        periodInputRow8.addEventListener('input', () => calculateConvertedHours(periodInputRow8, standardHourCellRow8, convertedHoursCellRow8, 7));

        svCheckbox9.addEventListener('change', () => {
            svInput9.style.display = svCheckbox9.checked ? 'inline-block' : 'none';
            additionalGvLabel9.style.display = svCheckbox9.checked ? 'inline-block' : 'none';
            calculateConvertedHours(dayInputRow9, standardHourCellRow9, convertedHoursCellRow9, 8);
        });
        svInput9.addEventListener('input', () => calculateConvertedHours(dayInputRow9, standardHourCellRow9, convertedHoursCellRow9, 8));
        additionalGvCheckbox9.addEventListener('change', () => calculateConvertedHours(dayInputRow9, standardHourCellRow9, convertedHoursCellRow9, 8));
        dayInputRow9.addEventListener('input', () => calculateConvertedHours(dayInputRow9, standardHourCellRow9, convertedHoursCellRow9, 8));

        function loadTeachingData(year) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_teaching_data&year=' + year
            })
            .then(response => response.json())
            .then(data => {
                periodInputRow1.value = data.muc1_tong_tiet || '';
                svCheckbox1.checked = data.muc1_sv_tren_40 == 1;
                svInput1.value = data.muc1_tong_sv || '';
                svInput1.style.display = svCheckbox1.checked ? 'inline-block' : 'none';
                calculateConvertedHours(periodInputRow1, standardHourCellRow1, convertedHoursCellRow1, 0);

                periodInputRow2.value = data.muc2_tong_tiet || '';
                calculateConvertedHours(periodInputRow2, standardHourCellRow2, convertedHoursCellRow2, 1);

                periodInputRow3.value = data.muc3_tong_tiet || '';
                calculateConvertedHours(periodInputRow3, standardHourCellRow3, convertedHoursCellRow3, 2);

                periodInputRow4.value = data.muc4_tong_tiet || '';
                calculateConvertedHours(periodInputRow4, standardHourCellRow4, convertedHoursCellRow4, 3);

                periodInputRow5.value = data.muc5_tong_tiet || '';
                svCheckbox5.checked = data.muc5_sv_tren_40 == 1;
                svInput5.value = data.muc5_tong_sv || '';
                svInput5.style.display = svCheckbox5.checked ? 'inline-block' : 'none';
                calculateConvertedHours(periodInputRow5, standardHourCellRow5, convertedHoursCellRow5, 4);

                periodInputRow6.value = data.muc6_tong_tiet || '';
                svCheckbox6.checked = data.muc6_sv_tren_30 == 1;
                svInput6.value = data.muc6_tong_sv || '';
                svInput6.style.display = svCheckbox6.checked ? 'inline-block' : 'none';
                calculateConvertedHours(periodInputRow6, standardHourCellRow6, convertedHoursCellRow6, 5);

                dayInputRow7.value = data.muc7_tong_ngay || '';
                svCheckbox7.checked = data.muc7_sv_tren_25 == 1;
                svInput7.value = data.muc7_tong_sv || '';
                svInput7.style.display = svCheckbox7.checked ? 'inline-block' : 'none';
                calculateConvertedHours(dayInputRow7, standardHourCellRow7, convertedHoursCellRow7, 6);

                periodInputRow8.value = data.muc8_tong_tin_chi || '';
                calculateConvertedHours(periodInputRow8, standardHourCellRow8, convertedHoursCellRow8, 7);

                dayInputRow9.value = data.muc9_tong_ngay || '';
                svCheckbox9.checked = data.muc9_sv_tren_40 == 1;
                svInput9.value = data.muc9_tong_sv || '';
                additionalGvCheckbox9.checked = data.muc9_them_gv == 1;
                svInput9.style.display = svCheckbox9.checked ? 'inline-block' : 'none';
                additionalGvLabel9.style.display = svCheckbox9.checked ? 'inline-block' : 'none';
                calculateConvertedHours(dayInputRow9, standardHourCellRow9, convertedHoursCellRow9, 8);
            })
            .catch(error => console.error('Lỗi khi tải dữ liệu:', error));
        }

        function handleSubmit(event) {
            event.preventDefault();
            const selectedYear = document.getElementById('selectedYear').value;
            const currentYear = new Date().getFullYear();
            const currentYearMinusOne = currentYear - 1;

            if (selectedYear != currentYear && selectedYear != currentYearMinusOne) {
                showPopup(`Không thể cập nhật dữ liệu cho năm ${selectedYear} vì chỉ được phép chỉnh sửa năm hiện tại (${currentYear}) hoặc năm hiện tại -1 (${currentYearMinusOne})`, 'error');
            } else {
                // Gửi dữ liệu form trực tiếp không cần xác nhận
                sendFormData(selectedYear);
            }
            return false;
        }

        // Hàm phụ để gửi dữ liệu form qua AJAX
        function sendFormData(selectedYear) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(new FormData(document.getElementById('teachingForm')))
            })
            .then(response => response.text())
            .then(data => {
                // Kiểm tra dữ liệu đã tồn tại để hiển thị thông báo phù hợp
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=check_data_exists&year=' + selectedYear
                })
                .then(checkResponse => checkResponse.json())
                .then(checkData => {
                    if (checkData.exists) {
                        showPopup('Cập nhật dữ liệu thành công cho năm ' + selectedYear, 'success');
                    } else {
                        showPopup('Lưu dữ liệu thành công cho năm ' + selectedYear, 'success');
                    }
                });
            })
            .catch(error => {
                console.error('Lỗi khi gửi dữ liệu:', error);
                showPopup('Có lỗi xảy ra khi gửi dữ liệu', 'error');
            });
        }

        // Thêm hàm loadScheduleData vào phần script
        function loadScheduleData(year) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_schedule_data&year=' + year
            })
            .then(response => response.json())
            .then(data => {
                // Cập nhật giá trị input từ dữ liệu schedule
                document.querySelector('input[name="muc1_tong_tiet"]').value = data.total_period || '';
                // Kích hoạt tính toán lại giờ quy đổi
                calculateConvertedHours(periodInputRow1, standardHourCellRow1, convertedHoursCellRow1, 0);
            })
            .catch(error => console.error('Lỗi khi tải dữ liệu:', error));
        }

        // Sửa lại event listener cho select năm
        document.getElementById('selectedYear').addEventListener('change', function() {
            loadTeachingData(this.value);
            loadScheduleData(this.value); // Thêm gọi hàm load dữ liệu schedule
        });

        // Sửa lại đoạn load dữ liệu tự động khi trang được tải
        document.addEventListener('DOMContentLoaded', function() {
            loadTeachingData(document.getElementById('selectedYear').value);
            loadScheduleData(document.getElementById('selectedYear').value); // Thêm gọi hàm load dữ liệu schedule
        });
    </script>
</body>
</html>