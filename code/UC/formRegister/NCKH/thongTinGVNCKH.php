<?php
// Kiểm tra session
if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login/login.php");
    exit();
}
require_once __DIR__ . '/../../../connection/connection.php';

$employeeID = $_SESSION['employeeID'];
$selectedYear = isset($_POST['selected_year']) ? $_POST['selected_year'] : date('Y');

// Lấy thông tin giảng viên
$stmt = $conn->prepare("SELECT academicTitle, leadershipPosition, rankTeacher FROM employee WHERE employeeID = :employeeID");
$stmt->execute([':employeeID' => $employeeID]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// Xác định định mức tối thiểu cơ bản
$dinhMuc = ($employee['academicTitle'] === 'Giảng viên (tập sự)') ? 0 : 590;
if (!empty($employee['leadershipPosition'])) {  
    $dinhMuc = 590;
}

// Kiểm tra sản phẩm đặc biệt để điều chỉnh định mức (bao gồm sáng chế)
$specialProductMessage = 'Không có sản phẩm đặc biệt';
$isSpecialA = false;
$isSpecialB = false;

// Điều kiện a) - Định mức 770 giờ
// 1. Bài báo GSNN >= 1.0 hoặc Q3, tác giả đứng đầu, >= 40%
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM bai_bao_history bh
    JOIN vietbaibao vb ON bh.nckh_id = vb.ID
    WHERE bh.employeeID = :employeeID AND bh.result_year = :result_year
    AND (vb.NoiDung LIKE '%GSNN ≥ 1,0%' OR vb.NoiDung LIKE '%Q3%')
    AND bh.vai_tro = 'Chủ nhiệm' AND bh.phan_tram_dong_gop >= 40
");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
if ($stmt->fetchColumn() > 0) $isSpecialA = true;

// 2. Bài báo Q2, tác giả đứng đầu, >= 15%
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM bai_bao_history bh
    JOIN vietbaibao vb ON bh.nckh_id = vb.ID
    WHERE bh.employeeID = :employeeID AND bh.result_year = :result_year
    AND vb.NoiDung LIKE '%Q2%'
    AND bh.vai_tro = 'Chủ nhiệm' AND bh.phan_tram_dong_gop >= 15
");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
if ($stmt->fetchColumn() > 0) $isSpecialA = true;

// 3. Bài báo Q1, > 25%
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM bai_bao_history bh
    JOIN vietbaibao vb ON bh.nckh_id = vb.ID
    WHERE bh.employeeID = :employeeID AND bh.result_year = :result_year
    AND vb.NoiDung LIKE '%Q1%'
    AND bh.phan_tram_dong_gop > 25
");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
if ($stmt->fetchColumn() > 0) $isSpecialA = true;

// 4. Sách quốc tế, chủ biên hoặc tác giả duy nhất
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM vietsach_history vh
    JOIN vietsach vs ON vh.nckh_id = vs.ID
    WHERE vh.employeeID = :employeeID AND vh.result_year = :result_year
    AND vs.NoiDung LIKE '%xuất bản ở nước ngoài%'
    AND (vh.vai_tro = 'Chủ biên' OR vh.so_tac_gia = 1)
");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
if ($stmt->fetchColumn() > 0) $isSpecialA = true;

// 5. Sáng chế
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM nckhcc_history nh
    JOIN nghiencuukhoahoccaccap nc ON nh.nckh_id = nc.ID
    WHERE nh.employeeID = :employeeID AND nh.result_year = :result_year
    AND nc.NoiDung LIKE '%sáng chế%'
");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
if ($stmt->fetchColumn() > 0) $isSpecialA = true;

// Điều kiện b) - Định mức 680 giờ (nếu không thỏa mãn a)
if (!$isSpecialA) {
    // 1. Bài báo GSNN >= 1.0 hoặc Q3, > 30%
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM bai_bao_history bh
        JOIN vietbaibao vb ON bh.nckh_id = vb.ID
        WHERE bh.employeeID = :employeeID AND bh.result_year = :result_year
        AND (vb.NoiDung LIKE '%GSNN ≥ 1,0%' OR vb.NoiDung LIKE '%Q3%')
        AND bh.phan_tram_dong_gop > 30
    ");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    if ($stmt->fetchColumn() > 0) $isSpecialB = true;

    // 2. Bài báo Q2, > 25%
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM bai_bao_history bh
        JOIN vietbaibao vb ON bh.nckh_id = vb.ID
        WHERE bh.employeeID = :employeeID AND bh.result_year = :result_year
        AND vb.NoiDung LIKE '%Q2%'
        AND bh.phan_tram_dong_gop > 25
    ");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    if ($stmt->fetchColumn() > 0) $isSpecialB = true;

    // 3. Bài báo Q1, >= 15%
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM bai_bao_history bh
        JOIN vietbaibao vb ON bh.nckh_id = vb.ID
        WHERE bh.employeeID = :employeeID AND bh.result_year = :result_year
        AND vb.NoiDung LIKE '%Q1%'
        AND bh.phan_tram_dong_gop >= 15
    ");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    if ($stmt->fetchColumn() > 0) $isSpecialB = true;

    // 4. Sách quốc tế, thành viên
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM vietsach_history vh
        JOIN vietsach vs ON vh.nckh_id = vs.ID
        WHERE vh.employeeID = :employeeID AND vh.result_year = :result_year
        AND vs.NoiDung LIKE '%xuất bản ở nước ngoài%'
        AND vh.vai_tro = 'Thành viên'
    ");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    if ($stmt->fetchColumn() > 0) $isSpecialB = true;

    // 5. Sáng chế
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM nckhcc_history nh
        JOIN nghiencuukhoahoccaccap nc ON nh.nckh_id = nc.ID
        WHERE nh.employeeID = :employeeID AND nh.result_year = :result_year
        AND nc.NoiDung LIKE '%sáng chế%'
    ");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    if ($stmt->fetchColumn() > 0) $isSpecialB = true;
}

// Điều chỉnh định mức
if ($isSpecialA) {
    $dinhMuc = 770;
    $specialProductMessage = 'Có sản phẩm đặc biệt (điều kiện a) → Định mức: 770 giờ';
} elseif ($isSpecialB) {
    $dinhMuc = 680;
    $specialProductMessage = 'Có sản phẩm đặc biệt (điều kiện b) → Định mức: 680 giờ';
}

// Tính tổng giờ NCKH từ 4 bảng lịch sử
$totalHours = 0;
$stmt = $conn->prepare("SELECT COALESCE(SUM(gio_quy_doi), 0) as total FROM nckhcc_history WHERE employeeID = :employeeID AND result_year = :result_year");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
$totalHours += $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COALESCE(SUM(gio_quy_doi), 0) as total FROM bai_bao_history WHERE employeeID = :employeeID AND result_year = :result_year");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
$totalHours += $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COALESCE(SUM(gio_quy_doi), 0) as total FROM huongdansv_history WHERE employeeID = :employeeID AND result_year = :result_year");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
$totalHours += $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COALESCE(SUM(gio_quy_doi), 0) as total FROM vietsach_history WHERE employeeID = :employeeID AND result_year = :result_year");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
$totalHours += $stmt->fetchColumn();

// Kiểm tra điều kiện hoàn thành theo hạng giảng viên
$status = 'Chưa hoàn thành';
$conditionMessage = '';

switch ($employee['rankTeacher']) {
    case 'Hạng I':
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM bai_bao_history bh
            JOIN vietbaibao vb ON bh.nckh_id = vb.ID
            WHERE bh.employeeID = :employeeID AND bh.result_year = :result_year
            AND (vb.NoiDung LIKE '%Q1%' OR vb.NoiDung LIKE '%Q2%' OR vb.NoiDung LIKE '%Q3%')
        ");
        $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
        $q123Count = $stmt->fetchColumn();

        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM vietsach_history vh
            JOIN vietsach vs ON vh.nckh_id = vs.ID
            WHERE vh.employeeID = :employeeID AND vh.result_year = :result_year
            AND vs.NoiDung LIKE '%xuất bản ở nước ngoài%'
        ");
        $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
        $internationalBookCount = $stmt->fetchColumn();

        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM nckhcc_history nh
            JOIN nghiencuukhoahoccaccap nc ON nh.nckh_id = nc.ID
            WHERE nh.employeeID = :employeeID AND nh.result_year = :result_year
            AND nc.NoiDung LIKE '%sáng chế%'
        ");
        $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
        $patentCount = $stmt->fetchColumn();

        $conditionMet = ($q123Count > 0 || $internationalBookCount > 0 || $patentCount > 0);
        if ($totalHours >= $dinhMuc && $conditionMet) {
            $status = 'Hoàn thành';
        }
        $conditionMessage = $conditionMet ? 'Đạt yêu cầu sản phẩm (Q1/Q2/Q3, sách quốc tế, hoặc sáng chế)' : 'Chưa đạt yêu cầu sản phẩm (cần Q1/Q2/Q3, sách quốc tế, hoặc sáng chế)';
        break;

    case 'Hạng II':
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM bai_bao_history bh
            JOIN vietbaibao vb ON bh.nckh_id = vb.ID
            WHERE bh.employeeID = :employeeID AND bh.result_year = :result_year
            AND (vb.NoiDung LIKE '%GSNN ≥ 0,75%' OR vb.NoiDung LIKE '%GSNN ≥ 1,0%')
        ");
        $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
        $gsnnCount = $stmt->fetchColumn();

        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM nckhcc_history nh
            JOIN nghiencuukhoahoccaccap nc ON nh.nckh_id = nc.ID
            WHERE nh.employeeID = :employeeID AND nh.result_year = :result_year
            AND nc.NoiDung LIKE '%được nghiệm thu%'
        ");
        $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
        $projectCount = $stmt->fetchColumn();

        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM vietsach_history vh
            JOIN vietsach vs ON vh.nckh_id = vs.ID
            WHERE vh.employeeID = :employeeID AND vh.result_year = :result_year
            AND vs.NoiDung LIKE '%giáo trình%'
        ");
        $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
        $textbookCount = $stmt->fetchColumn();

        $conditionMet = ($gsnnCount > 0 || $projectCount > 0 || $textbookCount > 0);
        if ($totalHours >= $dinhMuc && $conditionMet) {
            $status = 'Hoàn thành';
        }
        $conditionMessage = $conditionMet ? 'Đạt yêu cầu sản phẩm (bài báo GSNN ≥ 0,75, đề tài nghiệm thu, hoặc giáo trình)' : 'Chưa đạt yêu cầu sản phẩm (cần bài báo GSNN ≥ 0,75, đề tài nghiệm thu, hoặc giáo trình)';
        break;

    default:
        if ($totalHours >= $dinhMuc) {
            $status = 'Hoàn thành';
        }
        $conditionMessage = 'Không yêu cầu sản phẩm cụ thể, chỉ cần đủ giờ';
        break;
}

// After determining conditionMessage, add this code:
$stmt = $conn->prepare("
    UPDATE tong_hop_nckh 
    SET dieu_kien = :dieu_kien 
    WHERE employeeID = :employeeID AND result_year = :result_year
");
$stmt->execute([
    ':dieu_kien' => $conditionMessage,
    ':employeeID' => $employeeID,
    ':result_year' => $selectedYear
]);

// Hiển thị tiêu đề và thông tin giảng viên
?>
<h2 class="form-title">Thông tin giảng viên NCKH</h2>

<form method="POST" id="yearSelectionForm">
    <div style="text-align: center; margin-bottom: 15px;">
        <label for="selected_year">Năm lưu dữ liệu: </label>
        <select name="selected_year" id="selectedYear" class="year-select" onchange="this.form.submit()">
            <?php
            $currentYear = date('Y');
            for ($i = $currentYear - 5; $i <= $currentYear; $i++) {
                echo "<option value='$i'" . ($selectedYear == $i ? " selected" : "") . ">$i</option>";
            }
            ?>
        </select>
    </div>
</form>

<div class="teacher-info">
    <p><strong>Hạng giảng viên:</strong> <?php echo htmlspecialchars($employee['rankTeacher'] ?? 'Không xác định'); ?></p>
    <p><strong>Định mức giờ NCKH:</strong> <?php echo htmlspecialchars($dinhMuc); ?> giờ</p>
    <p><strong>Tổng giờ NCKH (<?php echo $selectedYear; ?>):</strong> <?php echo number_format($totalHours, 2); ?> giờ</p>
    <p><strong>Trạng thái hoàn thành:</strong> 
        <span style="color: <?php echo $status === 'Hoàn thành' ? 'green' : 'red'; ?>;">
            <?php echo htmlspecialchars($status); ?>
        </span>
    </p>
    <p><strong>Ghi chú về sản phẩm:</strong> <?php echo htmlspecialchars($conditionMessage); ?></p>
    <p><strong>Ghi chú về định mức:</strong> <?php echo htmlspecialchars($specialProductMessage); ?></p>
</div>

<style>
    .form-title {
        text-align: center;
        font-size: 24px;
        color: #223771;
        margin-bottom: 20px;
        font-weight: bold;
    }
    .teacher-info {
        text-align: center;
        font-size: 16px;
        margin: 20px auto;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background-color: #f9f9f9;
        max-width: 600px;
    }
    .teacher-info p {
        margin: 10px 0;
        line-height: 1.6;
    }
    .teacher-info p strong {
        color: #333;
    }
    .year-select {
        font-size: 14px;
        padding: 5px 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    #yearSelectionForm {
        text-align: center;
        margin-bottom: 20px;
    }
    #yearSelectionForm label {
        font-size: 16px;
        font-weight: bold;
        margin-right: 10px;
    }
    #yearSelectionForm select {
        font-size: 14px;
        padding: 5px;
    }
    .infor>a{color: #f8843d }
</style>