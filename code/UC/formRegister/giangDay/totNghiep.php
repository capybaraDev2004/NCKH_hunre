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
$stmt = $conn->prepare("SELECT * FROM tot_nghiep WHERE employeeID = :employeeID AND result_year = :result_year");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
$examData = $stmt->fetch(PDO::FETCH_ASSOC);

// Nếu không có dữ liệu, khởi tạo mảng trống
if (!$examData) {
    $examData = [
        'muc1_so_de' => '',
        'muc1_vai_tro' => '',
        'muc2_so_ca' => '',
        'muc3_so_bai' => '',
        'muc4_so_lop' => '',
        'muc4_so_sv' => '',
        'muc4_sv_exceed' => false,
        'muc5_so_sv' => '',
        'muc6_so_luan_van' => '',
        'muc6_vai_tro' => '',
        'muc7_so_luan_van' => '',
        'muc7_so_gv' => null,
        'muc7_vai_tro' => null,
        'muc8_so_khoa_luan' => '',
        'muc8_vai_tro' => '',
        'muc9_so_de' => '',
        'muc9_vai_tro' => '',
        'muc10_so_luan_van' => '',
        'muc10_vai_tro' => '',
    ];
}

// Xử lý AJAX request để lấy dữ liệu form
if (isset($_POST['action']) && $_POST['action'] === 'get_exam_data') {
    $year = $_POST['year'] ?? date('Y');
    $stmt = $conn->prepare("SELECT * FROM tot_nghiep WHERE employeeID = :employeeID AND result_year = :result_year");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) {
        $data = [
            'muc1_so_de' => '',
            'muc1_vai_tro' => '',
            'muc2_so_ca' => '',
            'muc3_so_bai' => '',
            'muc4_so_lop' => '',
            'muc4_so_sv' => '',
            'muc4_sv_exceed' => false,
            'muc5_so_sv' => '',
            'muc6_so_luan_van' => '',
            'muc6_vai_tro' => '',
            'muc7_so_luan_van' => '',
            'muc7_so_gv' => null,
            'muc7_vai_tro' => null,
            'muc8_so_khoa_luan' => '',
            'muc8_vai_tro' => '',
            'muc9_so_de' => '',
            'muc9_vai_tro' => '',
            'muc10_so_luan_van' => '',
            'muc10_vai_tro' => '',
        ];
    }
    echo json_encode($data);
    exit();
}

// Xử lý kiểm tra dữ liệu tồn tại (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'check_data_exists') {
    $year = $_POST['year'] ?? date('Y');
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM tot_nghiep WHERE employeeID = :employeeID AND result_year = :result_year");
    $checkStmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
    $exists = $checkStmt->fetchColumn();
    echo json_encode(['exists' => $exists > 0]);
    exit();
}

// Xử lý dữ liệu từ form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action']) && isset($_POST['muc1_so_de'])) {
    $tongGioChuan = 0;

    // Lấy dữ liệu từ form
    $muc1_so_de = floatval($_POST['muc1_so_de'] ?? 0);
    $muc1_vai_tro = $_POST['muc1_vai_tro'] ?? '';
    $muc2_so_ca = floatval($_POST['muc2_so_ca'] ?? 0);
    $muc3_so_bai = floatval($_POST['muc3_so_bai'] ?? 0);
    $muc4_so_lop = floatval($_POST['muc4_so_lop'] ?? 0);
    $muc4_so_sv = isset($_POST['muc4_sv_exceed']) ? floatval($_POST['muc4_so_sv'] ?? 0) : 0;
    $muc5_so_sv = floatval($_POST['muc5_so_sv'] ?? 0);
    $muc6_so_luan_van = floatval($_POST['muc6_so_luan_van'] ?? 0);
    $muc6_vai_tro = $_POST['muc6_vai_tro'] ?? '';
    $muc7_so_luan_van = floatval($_POST['muc7_so_luan_van'] ?? 0);
    $muc7_so_gv = isset($_POST['muc7_so_gv']) ? $_POST['muc7_so_gv'] : null;
    $muc7_vai_tro = null;
    if ($muc7_so_gv === '2' && isset($_POST['muc7_vai_tro'])) {
        $muc7_vai_tro = $_POST['muc7_vai_tro'];
    }
    $muc8_so_khoa_luan = floatval($_POST['muc8_so_khoa_luan'] ?? 0);
    $muc8_vai_tro = $_POST['muc8_vai_tro'] ?? '';
    $muc9_so_de = floatval($_POST['muc9_so_de'] ?? 0);
    $muc9_vai_tro = $_POST['muc9_vai_tro'] ?? '';
    $muc10_so_luan_van = floatval($_POST['muc10_so_luan_van'] ?? 0);
    $muc10_vai_tro = $_POST['muc10_vai_tro'] ?? '';

    // Xét điều kiện số lượng dựa trên vai trò
    // Mục 1: Ra đề, phản biện đề thi viết
    if ($muc1_so_de > 5) {
        echo "<script>showPopup('Mỗi môn thi không được quá 05 đề!', 'error');</script>";
        exit();
    }

    // Mục 4: Hướng dẫn đề cương thực tập tốt nghiệp
    if (isset($_POST['muc4_sv_exceed']) && $muc4_so_sv <= 25) {
        echo "<script>showPopup('Số sinh viên phải lớn hơn 25 khi tích chọn vượt 25 SV!', 'error');</script>";
        exit();
    }

    // Mục 6: Hướng dẫn khóa luận tốt nghiệp trình độ đại học
    if ($muc6_vai_tro == 'TSKH_GVCC_PGS_GS') {
        if ($muc6_so_luan_van > 185) {
            echo "<script>showPopup('Số sinh viên hướng dẫn khóa luận tốt nghiệp (TSKH, GVCC, PGS, GS) không được vượt quá 185!', 'error');</script>";
            exit();
        }
    } elseif ($muc6_vai_tro == 'TS_GVC') {
        if ($muc6_so_luan_van > 155) {
            echo "<script>showPopup('Số sinh viên hướng dẫn khóa luận tốt nghiệp (TS, GVC) không được vượt quá 155!', 'error');</script>";
            exit();
        }
    }

    // Mục 7: Hướng dẫn luận văn tốt nghiệp trình độ Thạc sỹ
    if ($muc7_so_luan_van > 0) {
        if (!$muc7_so_gv) {
            echo "<script>showPopup('Vui lòng chọn số giảng viên hướng dẫn!', 'error');</script>";
            exit();
        }
        
        if ($muc7_so_gv === '2' && !$muc7_vai_tro) {
            echo "<script>showPopup('Vui lòng chọn vai trò giảng viên khi chọn 2 giảng viên hướng dẫn!', 'error');</script>";
            exit();
        }

        if ($muc7_so_gv === '1' && $muc7_so_luan_van > 7) {
            echo "<script>showPopup('Số luận văn hướng dẫn (1 giảng viên) không được vượt quá 7!', 'error');</script>";
            exit();
        }
    }

    // Mục 8: Hội đồng chấm khóa luận tốt nghiệp đại học
    if ($muc8_vai_tro == 'TS') {
        if ($muc8_so_khoa_luan > 3) {
            echo "<script>showPopup('Số khóa luận chấm (TS) không được vượt quá 3!', 'error');</script>";
            exit();
        }
    } elseif (in_array($muc8_vai_tro, ['TSKH', 'PGS'])) {
        if ($muc8_so_khoa_luan > 5) {
            echo "<script>showPopup('Số khóa luận chấm (TSKH, PGS) không được vượt quá 5!', 'error');</script>";
            exit();
        }
    }

    // Mục 9: Hội đồng xét duyệt đề cương luận văn Thạc sỹ
    if ($muc9_so_de > 5) {
        echo "<script>showPopup('Số đề xét duyệt luận văn Thạc sỹ không được vượt quá 5!', 'error');</script>";
        exit();
    }

    // Tính giờ quy đổi
    $muc1_gio = $muc1_so_de * ($muc1_vai_tro == 'Ra đề' ? 2.5 : 1.25); // Ra đề, phản biện đề thi viết
    $muc2_gio = $muc2_so_ca * 2.0; // Coi thi
    $muc3_gio = $muc3_so_bai * 0.2; // Chấm thi viết
    $muc4_gio = $muc4_so_lop * (isset($_POST['muc4_sv_exceed']) ? min(5.0, 2.5 + ($muc4_so_sv - 25) * 0.01) : 2.5); // Hướng dẫn đề cương thực tập tốt nghiệp
    $muc5_gio = $muc5_so_sv * 0.3; // Chấm báo cáo thực tập tốt nghiệp
    $muc6_gio = $muc6_so_luan_van * 15.0; // Hướng dẫn khóa luận tốt nghiệp trình độ đại học
    $muc7_gio = ($muc7_so_gv === '1') ? $muc7_so_luan_van * 25.0 : ($muc7_so_gv === '2' ? ($muc7_vai_tro === 'GV1' ? $muc7_so_luan_van * 17.5 : $muc7_so_luan_van * 7.5) : 0); // Hướng dẫn luận văn tốt nghiệp trình độ Thạc sỹ
    $muc8_gio = $muc8_so_khoa_luan * ($muc8_vai_tro == 'Chủ tịch' ? 2.0 : ($muc8_vai_tro == 'Phản biện' ? 2.5 : 1.25)); // Hội đồng chấm khóa luận
    $muc9_gio = $muc9_so_de * 0.5; // Hội đồng xét duyệt đề cương luận văn Thạc sỹ
    $muc10_gio = $muc10_so_luan_van * ($muc10_vai_tro == 'Chủ tịch' ? 7.5 : ($muc10_vai_tro == 'Phản biện' ? 8.75 : ($muc10_vai_tro == 'Thư ký' ? 6.25 : 5.0))); // Hội đồng chấm luận văn Thạc sỹ

    $tongGioChuan = $muc1_gio + $muc2_gio + $muc3_gio + $muc4_gio + $muc5_gio + $muc6_gio + $muc7_gio + $muc8_gio + $muc9_gio + $muc10_gio;

    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM tot_nghiep WHERE employeeID = :employeeID AND result_year = :result_year");
    $checkStmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    $exists = $checkStmt->fetchColumn();

    if ($selectedYear != date('Y') && $selectedYear != date('Y') - 1) {
        echo "<script>showPopup('Không thể cập nhật dữ liệu cho năm $selectedYear vì chỉ được phép chỉnh sửa năm hiện tại hoặc năm hiện tại -1', 'error');</script>";
    } else {
        if ($exists) {
            $stmt = $conn->prepare("
                UPDATE tot_nghiep SET
                    muc1_so_de = :muc1_so_de, muc1_gio_quy_doi = :muc1_gio_quy_doi, muc1_vai_tro = :muc1_vai_tro,
                    muc2_so_ca = :muc2_so_ca, muc2_gio_quy_doi = :muc2_gio_quy_doi,
                    muc3_so_bai = :muc3_so_bai, muc3_gio_quy_doi = :muc3_gio_quy_doi,
                    muc4_so_lop = :muc4_so_lop, muc4_so_sv = :muc4_so_sv, muc4_gio_quy_doi = :muc4_gio_quy_doi,
                    muc5_so_sv = :muc5_so_sv, muc5_gio_quy_doi = :muc5_gio_quy_doi,
                    muc6_so_luan_van = :muc6_so_luan_van, muc6_gio_quy_doi = :muc6_gio_quy_doi, muc6_vai_tro = :muc6_vai_tro,
                    muc7_so_luan_van = :muc7_so_luan_van, muc7_gio_quy_doi = :muc7_gio_quy_doi, muc7_so_gv = :muc7_so_gv, muc7_vai_tro = :muc7_vai_tro,
                    muc8_so_khoa_luan = :muc8_so_khoa_luan, muc8_gio_quy_doi = :muc8_gio_quy_doi, muc8_vai_tro = :muc8_vai_tro,
                    muc9_so_de = :muc9_so_de, muc9_gio_quy_doi = :muc9_gio_quy_doi, muc9_vai_tro = :muc9_vai_tro,
                    muc10_so_luan_van = :muc10_so_luan_van, muc10_gio_quy_doi = :muc10_gio_quy_doi, muc10_vai_tro = :muc10_vai_tro,
                    tong_gio_quy_doi = :tong_gio_quy_doi, ngay_cap_nhat = CURRENT_TIMESTAMP
                WHERE employeeID = :employeeID AND result_year = :result_year
            ");
            $stmt->execute([
                ':employeeID' => $employeeID,
                ':result_year' => $selectedYear,
                ':muc1_so_de' => $muc1_so_de, ':muc1_gio_quy_doi' => $muc1_gio, ':muc1_vai_tro' => $muc1_vai_tro,
                ':muc2_so_ca' => $muc2_so_ca, ':muc2_gio_quy_doi' => $muc2_gio,
                ':muc3_so_bai' => $muc3_so_bai, ':muc3_gio_quy_doi' => $muc3_gio,
                ':muc4_so_lop' => $muc4_so_lop, ':muc4_so_sv' => $muc4_so_sv, ':muc4_gio_quy_doi' => $muc4_gio,
                ':muc5_so_sv' => $muc5_so_sv, ':muc5_gio_quy_doi' => $muc5_gio,
                ':muc6_so_luan_van' => $muc6_so_luan_van, ':muc6_gio_quy_doi' => $muc6_gio, ':muc6_vai_tro' => $muc6_vai_tro,
                ':muc7_so_luan_van' => $muc7_so_luan_van, ':muc7_gio_quy_doi' => $muc7_gio, ':muc7_so_gv' => $muc7_so_gv, ':muc7_vai_tro' => $muc7_vai_tro,
                ':muc8_so_khoa_luan' => $muc8_so_khoa_luan, ':muc8_gio_quy_doi' => $muc8_gio, ':muc8_vai_tro' => $muc8_vai_tro,
                ':muc9_so_de' => $muc9_so_de, ':muc9_gio_quy_doi' => $muc9_gio, ':muc9_vai_tro' => $muc9_vai_tro,
                ':muc10_so_luan_van' => $muc10_so_luan_van, ':muc10_gio_quy_doi' => $muc10_gio, ':muc10_vai_tro' => $muc10_vai_tro,
                ':tong_gio_quy_doi' => $tongGioChuan
            ]);
            echo "<script>showPopup('Cập nhật dữ liệu thành công cho năm $selectedYear', 'success');</script>";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO tot_nghiep (employeeID, result_year, muc1_so_de, muc1_gio_quy_doi, muc1_vai_tro, muc2_so_ca, muc2_gio_quy_doi, muc3_so_bai, muc3_gio_quy_doi, muc4_so_lop, muc4_so_sv, muc4_gio_quy_doi, muc5_so_sv, muc5_gio_quy_doi, muc6_so_luan_van, muc6_gio_quy_doi, muc6_vai_tro, muc7_so_luan_van, muc7_gio_quy_doi, muc7_so_gv, muc7_vai_tro, muc8_so_khoa_luan, muc8_gio_quy_doi, muc8_vai_tro, muc9_so_de, muc9_gio_quy_doi, muc9_vai_tro, muc10_so_luan_van, muc10_gio_quy_doi, muc10_vai_tro, tong_gio_quy_doi)
                VALUES (:employeeID, :result_year, :muc1_so_de, :muc1_gio_quy_doi, :muc1_vai_tro, :muc2_so_ca, :muc2_gio_quy_doi, :muc3_so_bai, :muc3_gio_quy_doi, :muc4_so_lop, :muc4_so_sv, :muc4_gio_quy_doi, :muc5_so_sv, :muc5_gio_quy_doi, :muc6_so_luan_van, :muc6_gio_quy_doi, :muc6_vai_tro, :muc7_so_luan_van, :muc7_gio_quy_doi, :muc7_so_gv, :muc7_vai_tro, :muc8_so_khoa_luan, :muc8_gio_quy_doi, :muc8_vai_tro, :muc9_so_de, :muc9_gio_quy_doi, :muc9_vai_tro, :muc10_so_luan_van, :muc10_gio_quy_doi, :muc10_vai_tro, :tong_gio_quy_doi)
            ");
            $stmt->execute([
                ':employeeID' => $employeeID,
                ':result_year' => $selectedYear,
                ':muc1_so_de' => $muc1_so_de, ':muc1_gio_quy_doi' => $muc1_gio, ':muc1_vai_tro' => $muc1_vai_tro,
                ':muc2_so_ca' => $muc2_so_ca, ':muc2_gio_quy_doi' => $muc2_gio,
                ':muc3_so_bai' => $muc3_so_bai, ':muc3_gio_quy_doi' => $muc3_gio,
                ':muc4_so_lop' => $muc4_so_lop, ':muc4_so_sv' => $muc4_so_sv, ':muc4_gio_quy_doi' => $muc4_gio,
                ':muc5_so_sv' => $muc5_so_sv, ':muc5_gio_quy_doi' => $muc5_gio,
                ':muc6_so_luan_van' => $muc6_so_luan_van, ':muc6_gio_quy_doi' => $muc6_gio, ':muc6_vai_tro' => $muc6_vai_tro,
                ':muc7_so_luan_van' => $muc7_so_luan_van, ':muc7_gio_quy_doi' => $muc7_gio, ':muc7_so_gv' => $muc7_so_gv, ':muc7_vai_tro' => $muc7_vai_tro,
                ':muc8_so_khoa_luan' => $muc8_so_khoa_luan, ':muc8_gio_quy_doi' => $muc8_gio, ':muc8_vai_tro' => $muc8_vai_tro,
                ':muc9_so_de' => $muc9_so_de, ':muc9_gio_quy_doi' => $muc9_gio, ':muc9_vai_tro' => $muc9_vai_tro,
                ':muc10_so_luan_van' => $muc10_so_luan_van, ':muc10_gio_quy_doi' => $muc10_gio, ':muc10_vai_tro' => $muc10_vai_tro,
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
    <title>Tốt nghiệp</title>
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
        .role-select { margin-top: 5px; }
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
        .totnghiep a, .NV1{
            color: #f8843d !important;
        }
    </style>
</head>
<body>
    <h2 class="form-title">Tốt nghiệp</h2>
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
    <form id="examForm" method="POST" action="" onsubmit="return handleSubmit(event)">
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
                    <td>Ra đề, phản biện đề thi viết</td>
                    <td>01 đề + 01 đáp án</td>
                    <td class="standard-hour" data-hour="2.5">2.5 gc (Ra đề) / 1.25 gc (Phản biện)</td>
                    <td>
                        <input type="number" name="muc1_so_de" class="total-input exam-input" min="0" step="1" placeholder="Tổng đề" max="5" value="<?php echo htmlspecialchars($examData['muc1_so_de']); ?>"><br>
                        <div class="role-select">
                            <label><input type="radio" name="muc1_vai_tro" value="Ra đề" <?php echo $examData['muc1_vai_tro'] === 'Ra đề' ? 'checked' : ''; ?>> Ra đề</label><br>
                            <label><input type="radio" name="muc1_vai_tro" value="Phản biện" <?php echo $examData['muc1_vai_tro'] === 'Phản biện' ? 'checked' : ''; ?>> Phản biện</label>
                        </div>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>Coi thi</td>
                    <td>Ca</td>
                    <td class="standard-hour" data-hour="2.0">2.0 gc</td>
                    <td><input type="number" name="muc2_so_ca" class="total-input exam-input" min="0" step="1" placeholder="Tổng ca" value="<?php echo htmlspecialchars($examData['muc2_so_ca']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>3</td>
                    <td>Chấm thi viết</td>
                    <td>1 bài</td>
                    <td class="standard-hour" data-hour="0.2">0.2 gc</td>
                    <td><input type="number" name="muc3_so_bai" class="total-input exam-input" min="0" step="1" placeholder="Tổng bài" value="<?php echo htmlspecialchars($examData['muc3_so_bai']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>4</td>
                    <td>Hướng dẫn đề cương thực tập tốt nghiệp</td>
                    <td>1 lớp</td>
                    <td class="standard-hour" data-hour="2.5">2.5 - 5.0 gc</td>
                    <td>
                        <input type="number" name="muc4_so_lop" class="total-input exam-input" min="0" step="1" placeholder="Tổng lớp" value="<?php echo htmlspecialchars($examData['muc4_so_lop']); ?>"><br>
                        <label>
                            <input type="checkbox" name="muc4_sv_exceed" id="muc4_sv_exceed" 
                                <?php echo (isset($examData['muc4_so_sv']) && $examData['muc4_so_sv'] > 25) ? 'checked' : ''; ?>> 
                            Số SV vượt 25
                        </label><br>
                        <div id="muc4_sv_input" style="display: <?php echo (isset($examData['muc4_so_sv']) && $examData['muc4_so_sv'] > 25) ? 'block' : 'none'; ?>">
                            <input type="number" name="muc4_so_sv" class="total-input" min="26" step="1" placeholder="Tổng SV" 
                                value="<?php echo (isset($examData['muc4_so_sv']) && $examData['muc4_so_sv'] > 25) ? htmlspecialchars($examData['muc4_so_sv']) : ''; ?>">
                        </div>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>5</td>
                    <td>Chấm báo cáo thực tập tốt nghiệp</td>
                    <td>1 SV</td>
                    <td class="standard-hour" data-hour="0.3">0.3 gc</td>
                    <td><input type="number" name="muc5_so_sv" class="total-input exam-input" min="0" step="1" placeholder="Tổng SV" value="<?php echo htmlspecialchars($examData['muc5_so_sv']); ?>"></td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>6</td>
                    <td>Hướng dẫn khóa luận tốt nghiệp trình độ đại học</td>
                    <td>1 SV/1 khóa luận</td>
                    <td class="standard-hour" data-hour="15.0">15.0 gc</td>
                    <td>
                        <input type="number" name="muc6_so_luan_van" class="total-input exam-input" min="0" step="1" placeholder="Tổng SV" value="<?php echo htmlspecialchars($examData['muc6_so_luan_van']); ?>"><br>
                        <div class="role-select">
                            <label><input type="radio" name="muc6_vai_tro" value="TSKH_GVCC_PGS_GS" <?php echo $examData['muc6_vai_tro'] === 'TSKH_GVCC_PGS_GS' ? 'checked' : ''; ?>> TSKH, GVCC, PGS, GS (<= 185 SV)</label><br>
                            <label><input type="radio" name="muc6_vai_tro" value="TS_GVC" <?php echo $examData['muc6_vai_tro'] === 'TS_GVC' ? 'checked' : ''; ?>> TS, GVC (<= 155 SV)</label>
                        </div>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>7</td>
                    <td>Hướng dẫn luận văn tốt nghiệp trình độ Thạc sỹ</td>
                    <td>1 luận văn</td>
                    <td class="standard-hour" data-hour="25.0">25.0 gc</td>
                    <td>
                        <input type="number" name="muc7_so_luan_van" class="total-input exam-input" min="0" step="1" placeholder="Tổng luận văn" value="<?php echo htmlspecialchars($examData['muc7_so_luan_van']); ?>"><br>
                        <div class="role-select">
                            <label>
                                <input type="radio" name="muc7_so_gv" value="1" 
                                    <?php echo ($examData['muc7_so_gv'] === '1' || $examData['muc7_so_gv'] === 1) ? 'checked' : ''; ?>> 
                                1 giảng viên (25.0 gc)
                            </label><br>
                            <label>
                                <input type="radio" name="muc7_so_gv" value="2" 
                                    <?php echo ($examData['muc7_so_gv'] === '2' || $examData['muc7_so_gv'] === 2) ? 'checked' : ''; ?>> 
                                2 giảng viên
                            </label><br>
                            <div id="muc7_vai_tro" style="display: <?php echo ($examData['muc7_so_gv'] === '2' || $examData['muc7_so_gv'] === 2) ? 'block' : 'none'; ?>; margin-left: 20px;">
                                <label>
                                    <input type="radio" name="muc7_vai_tro" value="GV1" 
                                        <?php echo $examData['muc7_vai_tro'] === 'GV1' ? 'checked' : ''; ?> 
                                        <?php echo ($examData['muc7_so_gv'] !== '2' && $examData['muc7_so_gv'] !== 2) ? 'disabled' : ''; ?>> 
                                    Giảng viên hướng dẫn 1 (17.5 gc)
                                </label><br>
                                <label>
                                    <input type="radio" name="muc7_vai_tro" value="GV2" 
                                        <?php echo $examData['muc7_vai_tro'] === 'GV2' ? 'checked' : ''; ?> 
                                        <?php echo ($examData['muc7_so_gv'] !== '2' && $examData['muc7_so_gv'] !== 2) ? 'disabled' : ''; ?>> 
                                    Giảng viên hướng dẫn 2 (7.5 gc)
                                </label>
                            </div>
                        </div>
                    </td>
                    <td class="converted-hours">0 gc</td>   
                </tr>
                <tr>
                    <td>8</td>
                    <td>Hội đồng chấm khóa luận tốt nghiệp đại học</td>
                    <td>01 khóa luận</td>
                    <td class="standard-hour" data-hour="2.0">2.0 - 2.5 gc</td>
                    <td>
                        <input type="number" name="muc8_so_khoa_luan" class="total-input exam-input" min="0" step="1" placeholder="Tổng khóa luận" value="<?php echo htmlspecialchars($examData['muc8_so_khoa_luan']); ?>"><br>
                        <div class="role-select">
                            <label><input type="radio" name="muc8_vai_tro" value="Chủ tịch" <?php echo $examData['muc8_vai_tro'] === 'Chủ tịch' ? 'checked' : ''; ?>> Chủ tịch (2.0 gc)</label><br>
                            <label><input type="radio" name="muc8_vai_tro" value="Phản biện" <?php echo $examData['muc8_vai_tro'] === 'Phản biện' ? 'checked' : ''; ?>> Phản biện (2.5 gc)</label><br>
                            <label><input type="radio" name="muc8_vai_tro" value="Thư ký" <?php echo $examData['muc8_vai_tro'] === 'Thư ký' ? 'checked' : ''; ?>> Thư ký (1.25 gc)</label>
                        </div>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>9</td>
                    <td>Hội đồng xét duyệt đề cương luận văn Thạc sỹ</td>
                    <td>01 đề cương/người</td>
                    <td class="standard-hour" data-hour="0.5" data-chu-tich="0.5" data-phan-bien="0.5" data-thu-ky="0.5" data-uy-vien="0.5">0.5 gc</td>
                    <td>
                        <input type="number" name="muc9_so_de" class="total-input exam-input" min="0" step="1" placeholder="Tổng đề" max="5" value="<?php echo htmlspecialchars($examData['muc9_so_de']); ?>"><br>
                        <div class="role-select">
                            <label><input type="radio" name="muc9_vai_tro" value="Chủ tịch" <?php echo $examData['muc9_vai_tro'] === 'Chủ tịch' ? 'checked' : ''; ?>> Chủ tịch</label><br>
                            <label><input type="radio" name="muc9_vai_tro" value="Phản biện" <?php echo $examData['muc9_vai_tro'] === 'Phản biện' ? 'checked' : ''; ?>> Phản biện</label><br>
                            <label><input type="radio" name="muc9_vai_tro" value="Thư ký" <?php echo $examData['muc9_vai_tro'] === 'Thư ký' ? 'checked' : ''; ?>> Thư ký</label><br>
                            <label><input type="radio" name="muc9_vai_tro" value="Ủy viên" <?php echo $examData['muc9_vai_tro'] === 'Ủy viên' ? 'checked' : ''; ?>> Ủy viên</label>
                        </div>
                    </td>
                    <td class="converted-hours">0 gc</td>
                </tr>
                <tr>
                    <td>10</td>
                    <td>Hội đồng chấm luận văn Thạc sỹ</td>
                    <td>01 luận văn</td>
                    <td class="standard-hour" data-hour="7.5">5.0 - 8.75 gc</td>
                    <td>
                        <input type="number" name="muc10_so_luan_van" class="total-input exam-input" min="0" step="1" placeholder="Tổng luận văn" value="<?php echo htmlspecialchars($examData['muc10_so_luan_van']); ?>"><br>
                        <div class="role-select">
                            <label><input type="radio" name="muc10_vai_tro" value="Chủ tịch" <?php echo $examData['muc10_vai_tro'] === 'Chủ tịch' ? 'checked' : ''; ?>> Chủ tịch (7.5 gc)</label><br>
                            <label><input type="radio" name="muc10_vai_tro" value="Phản biện" <?php echo $examData['muc10_vai_tro'] === 'Phản biện' ? 'checked' : ''; ?>> Phản biện (8.75 gc)</label><br>
                            <label><input type="radio" name="muc10_vai_tro" value="Thư ký" <?php echo $examData['muc10_vai_tro'] === 'Thư ký' ? 'checked' : ''; ?>> Thư ký (6.25 gc)</label><br>
                            <label><input type="radio" name="muc10_vai_tro" value="Ủy viên" <?php echo $examData['muc10_vai_tro'] === 'Ủy viên' ? 'checked' : ''; ?>> Ủy viên (5.0 gc)</label>
                        </div>
                    </td>
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

        document.addEventListener('DOMContentLoaded', function() {
            // Tính toán ban đầu cho tất cả các hàng khi trang load
            examInputs.forEach((input, index) => {
                calculateConvertedHours(input, standardHourCells[index], convertedHoursCells[index], index);
            });

            // Cập nhật hiển thị giờ chuẩn ban đầu dựa trên vai trò đã chọn
            updateAllStandardHours();

            // Khởi tạo trạng thái cho mục 7
            const muc7SoGV = document.querySelector('input[name="muc7_so_gv"]:checked');
            const vaiTroDiv = document.getElementById('muc7_vai_tro');
            if (muc7SoGV && muc7SoGV.value === '2') {
                vaiTroDiv.style.display = 'block';
                vaiTroDiv.querySelectorAll('input[type="radio"]').forEach(r => {
                    r.disabled = false;
                });
            }
        });

        function updateStandardHourDisplay(radio) {
            const row = radio.closest('tr');
            const standardHourCell = row.querySelector('.standard-hour');
            const rowIndex = Array.from(row.parentNode.children).indexOf(row);
            
            switch(rowIndex) {
                case 0: // Mục 1
                    standardHourCell.textContent = radio.value === 'Ra đề' ? '2.5 gc (Ra đề)' : '1.25 gc (Phản biện)';
                    break;
                case 5: // Mục 6
                    standardHourCell.textContent = '15.0 gc';
                    break;
                case 6: // Mục 7
                    if (radio.name === 'muc7_so_gv') {
                        if (radio.value === '1') {
                            standardHourCell.textContent = '25.0 gc';
                        } else {
                            const vaiTro = document.querySelector('input[name="muc7_vai_tro"]:checked')?.value;
                            standardHourCell.textContent = vaiTro === 'GV1' ? '17.5 gc (70%)' : '7.5 gc (30%)';
                        }
                    } else if (radio.name === 'muc7_vai_tro') {
                        standardHourCell.textContent = radio.value === 'GV1' ? '17.5 gc (70%)' : '7.5 gc (30%)';
                    }
                    break;
                case 7: // Mục 8
                    if (radio.value === 'Chủ tịch') standardHourCell.textContent = '2.0 gc';
                    else if (radio.value === 'Phản biện') standardHourCell.textContent = '2.5 gc';
                    else standardHourCell.textContent = '1.25 gc';
                    break;
                case 8: // Mục 9
                    standardHourCell.textContent = '0.5 gc';
                    break;
                case 9: // Mục 10
                    if (radio.value === 'Chủ tịch') standardHourCell.textContent = '7.5 gc';
                    else if (radio.value === 'Phản biện' || radio.value === 'Thư ký') standardHourCell.textContent = '8.75 gc';
                    else standardHourCell.textContent = '5.0 gc';
                    break;
            }
        }

        function updateAllStandardHours() {
            // Cập nhật hiển thị giờ chuẩn cho tất cả các radio button đã chọn
            document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
                updateStandardHourDisplay(radio);
            });
        }

        // Thêm event listener cho tất cả các radio buttons
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                updateStandardHourDisplay(this);
                // Cập nhật giờ quy đổi sau khi thay đổi vai trò
                const row = this.closest('tr');
                const rowIndex = Array.from(row.parentNode.children).indexOf(row);
                const examInput = examInputs[rowIndex];
                const standardHourCell = standardHourCells[rowIndex];
                const convertedHourCell = convertedHoursCells[rowIndex];
                calculateConvertedHours(examInput, standardHourCell, convertedHourCell, rowIndex);
            });
        });

        function calculateConvertedHours(input, standardHourCell, convertedHourCell, rowIndex) {
            const value = parseFloat(input.value) || 0;
            let standardHour;

            if (rowIndex === 8) { // Mục 9
                standardHour = 0.5;
            } else {
                if (rowIndex === 0) { // Mục 1
                    const vaiTro = document.querySelector('input[name="muc1_vai_tro"]:checked')?.value;
                    standardHour = vaiTro === 'Ra đề' ? 2.5 : 1.25;
                } else if (rowIndex === 3) { // Mục 4
                    const svExceed = document.getElementById('muc4_sv_exceed').checked;
                    const soSV = svExceed ? parseFloat(document.querySelector('input[name="muc4_so_sv"]').value) || 0 : 0;
                    standardHour = svExceed ? Math.min(5.0, 2.5 + (soSV - 25) * 0.01) : 2.5;
                } else if (rowIndex === 6) { // Mục 7
                    const soGV = document.querySelector('input[name="muc7_so_gv"]:checked')?.value;
                    if (soGV === '1') {
                        standardHour = 25.0;
                    } else if (soGV === '2') {
                        const vaiTro = document.querySelector('input[name="muc7_vai_tro"]:checked')?.value;
                        standardHour = vaiTro === 'GV1' ? 17.5 : (vaiTro === 'GV2' ? 7.5 : 0);
                    } else {
                        standardHour = 0;
                    }
                } else if (rowIndex === 7) { // Mục 8
                    const vaiTro = document.querySelector('input[name="muc8_vai_tro"]:checked')?.value;
                    standardHour = vaiTro === 'Chủ tịch' ? 2.0 : (vaiTro === 'Phản biện' ? 2.5 : 1.25);
                } else if (rowIndex === 9) { // Mục 10
                    const vaiTro = document.querySelector('input[name="muc10_vai_tro"]:checked')?.value;
                    standardHour = vaiTro === 'Chủ tịch' ? 7.5 : (vaiTro === 'Phản biện' ? 8.75 : (vaiTro === 'Thư ký' ? 6.25 : 5.0));
                } else {
                    standardHour = parseFloat(standardHourCell.getAttribute('data-hour'));
                }
            }

            const result = value * standardHour;
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

        examInputRow1.addEventListener('input', () => calculateConvertedHours(examInputRow1, standardHourCellRow1, convertedHoursCellRow1, 0));
        examInputRow2.addEventListener('input', () => calculateConvertedHours(examInputRow2, standardHourCellRow2, convertedHoursCellRow2, 1));
        examInputRow3.addEventListener('input', () => calculateConvertedHours(examInputRow3, standardHourCellRow3, convertedHoursCellRow3, 2));
        examInputRow4.addEventListener('input', () => calculateConvertedHours(examInputRow4, standardHourCellRow4, convertedHoursCellRow4, 3));
        examInputRow5.addEventListener('input', () => calculateConvertedHours(examInputRow5, standardHourCellRow5, convertedHoursCellRow5, 4));
        examInputRow6.addEventListener('input', () => calculateConvertedHours(examInputRow6, standardHourCellRow6, convertedHoursCellRow6, 5));
        examInputRow7.addEventListener('input', () => calculateConvertedHours(examInputRow7, standardHourCellRow7, convertedHoursCellRow7, 6));
        examInputRow8.addEventListener('input', () => calculateConvertedHours(examInputRow8, standardHourCellRow8, convertedHoursCellRow8, 7));
        examInputRow9.addEventListener('input', () => calculateConvertedHours(examInputRow9, standardHourCellRow9, convertedHoursCellRow9, 8));
        examInputRow10.addEventListener('input', () => calculateConvertedHours(examInputRow10, standardHourCellRow10, convertedHoursCellRow10, 9));

        // Xử lý hiển thị ô nhập số SV khi tích checkbox
        document.getElementById('muc4_sv_exceed').addEventListener('change', function() {
            const svInputDiv = document.getElementById('muc4_sv_input');
            if (this.checked) {
                svInputDiv.style.display = 'block';
            } else {
                svInputDiv.style.display = 'none';
                document.querySelector('input[name="muc4_so_sv"]').value = '';
                calculateConvertedHours(examInputRow4, standardHourCellRow4, convertedHoursCellRow4, 3);
            }
        });

        // Tính lại giờ khi nhập số SV
        document.querySelector('input[name="muc4_so_sv"]').addEventListener('input', () => {
            calculateConvertedHours(examInputRow4, standardHourCellRow4, convertedHoursCellRow4, 3);
        });

        // Xử lý hiển thị vai trò cho mục 7
        document.querySelectorAll('input[name="muc7_so_gv"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const vaiTroDiv = document.getElementById('muc7_vai_tro');
                const vaiTroRadios = vaiTroDiv.querySelectorAll('input[type="radio"]');
                
                if (this.value === '2') {
                    vaiTroDiv.style.display = 'block';
                    vaiTroRadios.forEach(r => {
                        r.disabled = false;
                    });
                } else {
                    vaiTroDiv.style.display = 'none';
                    vaiTroRadios.forEach(r => {
                        r.checked = false;
                        r.disabled = true;
                    });
                }
                
                updateStandardHourDisplay(this);
                calculateConvertedHours(examInputRow7, standardHourCellRow7, convertedHoursCellRow7, 6);
            });
        });

        // Xử lý thay đổi vai trò để tính lại giờ
        document.querySelectorAll('input[name="muc1_vai_tro"], input[name="muc6_vai_tro"], input[name="muc7_vai_tro"], input[name="muc8_vai_tro"], input[name="muc9_vai_tro"], input[name="muc10_vai_tro"]').forEach(radio => {
            radio.addEventListener('change', () => {
                const rowIndex = parseInt(radio.name.match(/\d+/)[0]) - 1;
                calculateConvertedHours(examInputs[rowIndex], standardHourCells[rowIndex], convertedHoursCells[rowIndex], rowIndex);
            });
        });

        function showPopup(message, type) {
            alert(message);
        }

        function handleSubmit(event) {
            event.preventDefault();
            const selectedYear = document.getElementById('selectedYear').value;
            const currentYear = new Date().getFullYear();
            const currentYearMinusOne = currentYear - 1;

            // Client-side validation
            if (examInputRow1.value > 5) {
                showPopup('Mỗi môn thi không được quá 05 đề!', 'error');
                return false;
            }
            const svExceed = document.getElementById('muc4_sv_exceed').checked;
            const soSV = svExceed ? parseFloat(document.querySelector('input[name="muc4_so_sv"]').value) || 0 : 0;
            if (svExceed && soSV <= 25) {
                showPopup('Số sinh viên phải lớn hơn 25 khi tích chọn vượt 25 SV!', 'error');
                return false;
            }
            if (examInputRow6.value > 185 && document.querySelector('input[name="muc6_vai_tro"]:checked')?.value === 'TSKH_GVCC_PGS_GS') {
                showPopup('Số sinh viên hướng dẫn khóa luận tốt nghiệp (TSKH, GVCC, PGS, GS) không được vượt quá 185!', 'error');
                return false;
            }
            if (examInputRow6.value > 155 && document.querySelector('input[name="muc6_vai_tro"]:checked')?.value === 'TS_GVC') {
                showPopup('Số sinh viên hướng dẫn khóa luận tốt nghiệp (TS, GVC) không được vượt quá 155!', 'error');
                return false;
            }
            if (examInputRow7.value > 7 && document.querySelector('input[name="muc7_so_gv"]:checked')?.value === '1') {
                showPopup('Số luận văn hướng dẫn (1 giảng viên) không được vượt quá 7!', 'error');
                return false;
            }
            if (examInputRow7.value > 5 && document.querySelector('input[name="muc7_so_gv"]:checked')?.value === '2') {
                showPopup('Số luận văn hướng dẫn (2 giảng viên) không được vượt quá 5!', 'error');
                return false;
            }
            if (examInputRow8.value > 3 && document.querySelector('input[name="muc8_vai_tro"]:checked')?.value === 'TS') {
                showPopup('Số khóa luận chấm (TS) không được vượt quá 3!', 'error');
                return false;
            }
            if (examInputRow8.value > 5 && ['TSKH', 'PGS'].includes(document.querySelector('input[name="muc8_vai_tro"]:checked')?.value)) {
                showPopup('Số khóa luận chấm (TSKH, PGS) không được vượt quá 5!', 'error');
                return false;
            }
            if (examInputRow9.value > 5) {
                showPopup('Số đề xét duyệt luận văn Thạc sỹ không được vượt quá 5!', 'error');
                return false;
            }

            // Kiểm tra mục 7
            const soGV = document.querySelector('input[name="muc7_so_gv"]:checked')?.value;
            const soLuanVan = parseFloat(examInputRow7.value) || 0;
            
            if (soLuanVan > 0) {
                if (!soGV) {
                    showPopup('Vui lòng chọn số giảng viên hướng dẫn!', 'error');
                    return false;
                }
                
                if (soGV === '2') {
                    const vaiTro = document.querySelector('input[name="muc7_vai_tro"]:checked')?.value;
                    if (!vaiTro) {
                        showPopup('Vui lòng chọn vai trò giảng viên khi chọn 2 giảng viên hướng dẫn!', 'error');
                        return false;
                    }
                }
            }

            // Thêm selected_year vào formData
            const formData = new FormData(document.getElementById('examForm'));
            formData.append('selected_year', selectedYear);

            if (selectedYear != currentYear && selectedYear != currentYearMinusOne) {
                showPopup(`Không thể cập nhật dữ liệu cho năm ${selectedYear} vì chỉ được phép chỉnh sửa năm hiện tại (${currentYear}) hoặc năm hiện tại -1 (${currentYearMinusOne})`, 'error');
                return false;
            }

            // Submit form data directly without confirmation
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('Cập nhật dữ liệu thành công') || data.includes('Lưu dữ liệu thành công')) {
                    showPopup(`Dữ liệu đã được cập nhật thành công cho năm ${selectedYear}`, 'success');
                    // Reload trang sau khi cập nhật thành công
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else if (data.includes('Không thể cập nhật dữ liệu')) {
                    showPopup(`Không thể cập nhật dữ liệu cho năm ${selectedYear}`, 'error');
                } else {
                    showPopup('Có lỗi xảy ra khi cập nhật dữ liệu!', 'error');
                }
            })
            .catch(error => {
                showPopup('Lỗi kết nối server: ' + error, 'error');
            });

            return false;
        }
    </script>
</body>
</html>