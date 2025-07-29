<?php
session_start();
require_once __DIR__ . '/../../connection/connection.php';

// Khởi tạo các biến $subpage và $section
$subpage = isset($_GET['subpage']) ? $_GET['subpage'] : '';
$section = isset($_GET['section']) ? $_GET['section'] : '';

if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login/login.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Giảng viên') { 
    echo "<script> alert('Bạn không có quyền truy cập trang này! Chỉ có vai trò \"Giảng viên\" mới được phép truy cập.'); window.location.href = '../../trangchu.php'; </script>"; 
    exit(); 
}

$currentPage = isset($_GET['page']) ? $_GET['page'] : 'home';
$employeeID = $_SESSION['employeeID'];

// Năm trong sidebar-right để hiển thị thống kê và dữ liệu form
$statsYear = isset($_POST['stats_year']) ? $_POST['stats_year'] : date('Y');
// Năm trong form để lưu dữ liệu (main-content)
$selectedYear = isset($_POST['selected_year']) ? $_POST['selected_year'] : (date('Y') - 1);

// Lấy thông tin chức danh và vị trí lãnh đạo từ bảng employee
$stmt = $conn->prepare("SELECT academicTitle, leadershipPosition FROM employee WHERE employeeID = :employeeID");
$stmt->execute([':employeeID' => $employeeID]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// Kiểm tra giá trị note từ bảng bai_bao_history
$stmt = $conn->prepare("SELECT note FROM bai_bao_history WHERE employeeID = :employeeID AND result_year = :result_year ORDER BY id DESC LIMIT 1");
$stmt->execute([
    ':employeeID' => $employeeID,
    ':result_year' => date('Y') // Use current year initially
]);
$noteValue = $stmt->fetchColumn();

// Thiết lập giờ chuẩn dựa trên giá trị note
$standardTeachingHours = 330; // Giá trị mặc định
$standardResearchHours = 590; // Giá trị mặc định
$standardOtherTasksHours = 180; // Giá trị mặc định

switch ($noteValue) {
    case 1:
        $standardTeachingHours = 270;
        $standardResearchHours = 770;
        break;
    case 2:
        $standardTeachingHours = 300;
        $standardResearchHours = 680;
        break;
    default:
        // Giữ nguyên giá trị mặc định
        break;
}

// Đặt đoạn này SAU khi đã xác định chuẩn từ note
if (
    isset($employee['academicTitle']) && 
    (
        $employee['academicTitle'] === 'Giảng viên (tập sự)' ||
        $employee['academicTitle'] === 'Trợ giảng (tập sự)'
    )
) {
    $standardResearchHours = 0;
}

// Hàm tính số giờ giảng dạy mục tiêu dựa trên chức danh và vai trò
function calculateTargetTeachingHours($academicTitle, $leadershipPosition, $conn, $employeeID) {
    global $standardTeachingHours; // Sử dụng biến toàn cục
    
    $baseHours = $standardTeachingHours;
    
    switch ($academicTitle) {
        case 'Giảng viên':
            $baseHours = $standardTeachingHours;
            break;
        case 'Giảng viên (tập sự)':
            $baseHours = 165;
            break;
        case 'Trợ giảng':
            $baseHours = 165;
            break;
        case 'Trợ giảng (tập sự)':
            $baseHours = 140;
            break;
        default:
            $baseHours = $standardTeachingHours;
    }

    $targetHours = $baseHours;

    if ($academicTitle === 'Giảng viên') {
        $leadershipPercentages = [
            'Chủ tịch Hội đồng trường, Hiệu trưởng' => 0.15, // 15%
            'Phó chủ tịch Hội đồng trường, Phó hiệu trưởng' => 0.20, // 20%
            'Trưởng phòng, Giám đốc trung tâm, Thư ký Hội đồng trường' => 0.25, // 25%
            'Phó trưởng phòng, Phó Giám đốc trung tâm' => 0.30, // 30%
            'Trưởng khoa, Trưởng Bộ môn trực thuộc trường (≥ 40 giảng viên hoặc ≥ 800 người học)' => 0.60, // 60%
            'Trưởng khoa, Trưởng Bộ môn trực thuộc trường (< 40 giảng viên hoặc < 800 người học)' => 0.70, // 70%
            'Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường (≥ 40 giảng viên hoặc ≥ 800 SV)' => 0.70, // 70%
            'Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường (< 40 giảng viên hoặc < 800 SV)' => 0.80, // 80%
            'Trưởng bộ môn trực thuộc khoa' => 0.80, // 80%
            'Phó trưởng bộ môn trực thuộc khoa' => 0.85, // 85%
            'Chủ nhiệm lớp, Cố vấn học tập (được cộng dồn)' => 0.85, // 85%
            'Bí thư đảng ủy' => 0.15, // 15%
            'Phó bí thư Đảng ủy' => 0.30, // 30%
            'Bí thư chi bộ, Trưởng ban TTND, Trưởng Ban nữ công, Chủ tịch Hội CCB' => 0.85, // 85%
            'Phó Bí thư chi bộ' => 0.90, // 90%
            'Giảng viên làm công tác quốc phòng, quân sự không chuyên trách' => 0.80, // 80%
            'Bí thư đoàn trường' => 0.30, // 30%
            'Phó bí thư đoàn trường' => 0.40, // 40%
            'Bí thư Liên chi đoàn (≥ 1,000 SV)' => 0.60, // 60%
            'Bí thư Liên chi đoàn (500 - 1,000 SV)' => 0.65, // 65%
            'Bí thư Liên chi đoàn (< 500 SV)' => 0.70, // 70%
            'Giảng viên nữ có con nhỏ dưới 12 tháng' => 0.90, // 90%
            'Cán bộ giảng dạy kiêm quản lý phòng thí nghiệm, thực hành công nghệ, phòng máy' => 0.85, // 85%
            'Chủ tịch Công đoàn trường, Phó chủ tịch Công đoàn trường' => 0.8667, // 86.67%
            'Ủy viên BCH công đoàn trường, tổ trưởng, tổ phó tổ công đoàn' => 0.9333, // 93.33%
            'Giảng viên làm thay công tác trợ lý khoa nghỉ việc' => 0.50, // 50%
            'Giảng viên đang là quân nhân dự bị, tự vệ được triệu tập huấn luyện, diễn tập' => 1.00 // 100%
        ];

        if (array_key_exists($leadershipPosition, $leadershipPercentages)) {
            if ($leadershipPosition === 'Giảng viên đang là quân nhân dự bị, tự vệ được triệu tập huấn luyện, diễn tập') {
                // Lấy số ngày diễn tập từ bảng dien_tap
                $stmt = $conn->prepare("SELECT so_ngay FROM dien_tap WHERE employeeID = :employeeID AND result_year = :result_year");
                $stmt->execute([
                    ':employeeID' => $employeeID,
                    ':result_year' => date('Y') - 1 // Lấy dữ liệu của năm trước
                ]);
                $trainingData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($trainingData) {
                    $days = floatval($trainingData['so_ngay']);
                    $targetHours = $standardTeachingHours - ($days * 2.5); // Công thức quy đổi: 1 ngày = 2.5 giờ
                } else {
                    $targetHours = $standardTeachingHours; // Giá trị mặc định nếu không có dữ liệu diễn tập
                }
            } else {
                $targetHours = $standardTeachingHours * $leadershipPercentages[$leadershipPosition];
            }
        }
    }

    return $targetHours;
}

// Hàm mới để tính số giờ nhiệm vụ khác dựa trên chức danh
function calculateOtherTasksHours($academicTitle) {
    global $standardOtherTasksHours;
    
    switch ($academicTitle) {
        case 'Giảng viên':
            return $standardOtherTasksHours;
        case 'Giảng viên (tập sự)':
            return 1265;
        case 'Trợ giảng':
            return 675;
        case 'Trợ giảng (tập sự)':
            return 1340;
        default:
            return $standardOtherTasksHours;
    }
}

$targetTeachingHours = calculateTargetTeachingHours($employee['academicTitle'], $employee['leadershipPosition'], $conn, $employeeID);
$targetOtherTasksHours = calculateOtherTasksHours($employee['academicTitle']);

// Xử lý AJAX request
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'get_stats') {
        $year = $_POST['year'] ?? (date('Y') - 1);
        $tongGioChuan = 0;
        $tongGioNCKH = 0;
        $tongGioNhiemVuKhac = 0;

        // Get note value for the selected year
        $stmt = $conn->prepare("SELECT note FROM bai_bao_history WHERE employeeID = :employeeID AND result_year = :result_year ORDER BY id DESC LIMIT 1");
        $stmt->execute([
            ':employeeID' => $employeeID,
            ':result_year' => $year
        ]);
        $noteValue = $stmt->fetchColumn();

        // Calculate standard hours based on note value
        $standardTeachingHours = 330; // Default value
        $standardOtherTasksHours = 180; // Default value

        switch ($noteValue) {
            case 1:
                $standardTeachingHours = 270;
                $standardResearchHours = 770;
                break;
            case 2:
                $standardTeachingHours = 300;
                $standardResearchHours = 680;
                break;
            default:
                // Keep default values
                break;
        }

        // Get teaching hours
        $tables = ['giangday', 'coi_thi', 'ra_de_thi', 'tot_nghiep', 'hoi_dong'];
        foreach ($tables as $table) {
            $stmt = $conn->prepare("SELECT tong_gio_quy_doi FROM $table WHERE employeeID = :employeeID AND result_year = :result_year");
            $stmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
            $tongGioChuan += floatval($stmt->fetchColumn() ?: 0);
        }

        // Add training days calculation
        $stmt = $conn->prepare("SELECT so_ngay FROM dien_tap WHERE employeeID = :employeeID AND result_year = :result_year");
        $stmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
        $soNgayDienTap = floatval($stmt->fetchColumn() ?: 0);
        $tongGioChuan += ($soNgayDienTap * 2.5);

        // Get research hours
        $stmt = $conn->prepare("SELECT SUM(tong_gio_nckh) FROM tong_hop_nckh WHERE employeeID = :employeeID AND result_year = :result_year");
        $stmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
        $tongGioNCKH = floatval($stmt->fetchColumn() ?: 0);

        // Get other tasks hours
        $stmt = $conn->prepare("SELECT SUM(total_completed_hours) FROM total_hours WHERE employee_id = :employeeID AND year = :result_year");
        $stmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
        $tongGioNhiemVuKhac = floatval($stmt->fetchColumn() ?: 0);

        // Calculate target hours based on employee's academic title and leadership position
        $targetTeachingHours = calculateTargetTeachingHours($employee['academicTitle'], $employee['leadershipPosition'], $conn, $employeeID);
        $targetOtherTasksHours = calculateOtherTasksHours($employee['academicTitle']);

        // Xác định trạng thái hoàn thành
        $trangThaiHoanThanh = ($tongGioChuan >= $targetTeachingHours) ? 'hoàn thành' : 'không hoàn thành';

        // Kiểm tra đã có bản ghi cho employeeID + result_year chưa
        $stmt = $conn->prepare("SELECT COUNT(*) FROM tong_hop_giang_day WHERE employeeID = :employeeID AND result_year = :result_year");
        $stmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            // Update
            $stmt = $conn->prepare("UPDATE tong_hop_giang_day SET tong_gio_giang_day = :tong_gio_giang_day, trang_thai_hoan_thanh = :trang_thai_hoan_thanh, dinh_muc_toi_thieu = :dinh_muc_toi_thieu WHERE employeeID = :employeeID AND result_year = :result_year");
            $stmt->execute([
                ':tong_gio_giang_day' => $tongGioChuan,
                ':trang_thai_hoan_thanh' => $trangThaiHoanThanh,
                ':dinh_muc_toi_thieu' => $targetTeachingHours,
                ':employeeID' => $employeeID,
                ':result_year' => $year
            ]);
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO tong_hop_giang_day (employeeID, tong_gio_giang_day, trang_thai_hoan_thanh, dinh_muc_toi_thieu, result_year) VALUES (:employeeID, :tong_gio_giang_day, :trang_thai_hoan_thanh, :dinh_muc_toi_thieu, :result_year)");
            $stmt->execute([
                ':employeeID' => $employeeID,
                ':tong_gio_giang_day' => $tongGioChuan,
                ':trang_thai_hoan_thanh' => $trangThaiHoanThanh,
                ':dinh_muc_toi_thieu' => $targetTeachingHours,
                ':result_year' => $year
            ]);
        }

        // Nếu là Giảng viên (tập sự) hoặc Trợ giảng (tập sự) thì định mức NCKH = 0
        if (
            isset($employee['academicTitle']) &&
            (
                $employee['academicTitle'] === 'Giảng viên (tập sự)' ||
                $employee['academicTitle'] === 'Trợ giảng (tập sự)'
            )
        ) {
            $dinhMucNCKH = 0;
        } else {
            $dinhMucNCKH = $standardResearchHours;
        }

        $trangThaiNCKH = ($tongGioNCKH >= $dinhMucNCKH) ? 'Hoàn thành' : 'Chưa hoàn thành';

        $stmt = $conn->prepare("UPDATE tong_hop_nckh SET trang_thai_hoan_thanh = :trang_thai_hoan_thanh, dinh_muc_toi_thieu = :dinh_muc_toi_thieu WHERE employeeID = :employeeID AND result_year = :result_year");
        $stmt->execute([
            ':trang_thai_hoan_thanh' => $trangThaiNCKH,
            ':dinh_muc_toi_thieu' => $dinhMucNCKH,
            ':employeeID' => $employeeID,
            ':result_year' => $year
        ]);

        // Lấy tổng giờ nhiệm vụ khác từ bảng total_hours
        $stmt = $conn->prepare("SELECT total_completed_hours FROM total_hours WHERE employee_id = :employeeID AND year = :year");
        $stmt->execute([
            ':employeeID' => $employeeID,
            ':year' => $year
        ]);
        $totalCompletedOtherTasks = floatval($stmt->fetchColumn() ?: 0);

        // Xác định trạng thái hoàn thành nhiệm vụ khác
        $trangThaiOtherTasks = ($totalCompletedOtherTasks >= $targetOtherTasksHours) ? 'Hoàn thành' : 'Chưa hoàn thành';

        // Cập nhật vào bảng total_hours
        $stmt = $conn->prepare("UPDATE total_hours SET trang_thai_hoan_thanh = :trang_thai_hoan_thanh, dinh_muc_toi_thieu = :dinh_muc_toi_thieu WHERE employee_id = :employeeID AND year = :year");
        $stmt->execute([
            ':trang_thai_hoan_thanh' => $trangThaiOtherTasks,
            ':dinh_muc_toi_thieu' => $targetOtherTasksHours,
            ':employeeID' => $employeeID,
            ':year' => $year
        ]);

        echo json_encode([
            'tongGioChuan' => $tongGioChuan,
            'tongGioNCKH' => $tongGioNCKH,
            'tongGioNhiemVuKhac' => $tongGioNhiemVuKhac,
            'dinhMucNCKH' => $dinhMucNCKH,
            'targetTeachingHours' => $targetTeachingHours,
            'targetOtherTasksHours' => $targetOtherTasksHours
        ]);
        exit;
    } elseif ($_POST['action'] === 'get_teaching_data') {
        $year = $_POST['year'] ?? (date('Y') - 1);
        $stmt = $conn->prepare("SELECT * FROM giangday WHERE employeeID = :employeeID AND result_year = :result_year");
        $stmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) {
            $data = [
                'muc1_tong_tiet' => '', 'muc1_sv_tren_40' => 0, 'muc1_tong_sv' => '',
                'muc2_tong_tiet' => '', 'muc3_tong_tiet' => '', 'muc4_tong_tiet' => '',
                'muc5_tong_tiet' => '', 'muc5_sv_tren_40' => 0, 'muc5_tong_sv' => '',
                'muc6_tong_tiet' => '', 'muc6_sv_tren_30' => 0, 'muc6_tong_sv' => '',
                'muc7_tong_ngay' => '', 'muc7_sv_tren_25' => 0, 'muc7_tong_sv' => '',
                'muc8_tong_tin_chi' => '', 'muc9_tong_ngay' => '', 'muc9_sv_tren_40' => 0,
                'muc9_tong_sv' => '', 'muc9_them_gv' => 0,
            ];
        }
        echo json_encode($data);
        exit;
    } elseif ($_POST['action'] === 'check_data_exists') {
        $year = $_POST['year'] ?? (date('Y') - 1);
        $stmt = $conn->prepare("SELECT COUNT(*) FROM giangday WHERE employeeID = :employeeID AND result_year = :result_year");
        $stmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
        $exists = $stmt->fetchColumn();
        echo json_encode(['exists' => $exists > 0]);
        exit;
    } elseif ($_POST['action'] === 'save_training_days') {
        $days = floatval($_POST['training_days']);
        $_SESSION['training_days'] = $days;
        $targetHours = 330 - ($days * 2.5);
        echo json_encode(['targetTeachingHours' => $targetHours]);
        exit;
    }
}

// Xử lý dữ liệu từ form giảng dạy  
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentPage === 'teaching' && isset($_POST['selected_year']) && !isset($_POST['action'])) {
    $tongGioChuan = 0;

    function tinhGioQuyDoi($tong, $standardHour, $svTren, $tongSV, $maxHour, $themGV = false, $svThem = 0) {
        $tong = floatval($tong);
        $tongSV = floatval($tongSV);
        $gio = $standardHour;
        if ($svTren && $tongSV > $svThem) {
            $gio = min($maxHour, $standardHour + ($tongSV - $svThem) * ($themGV ? 0.02 : 0.01));
        }
        if ($themGV && $svTren) $gio /= 2;
        return $tong * $gio;
    }

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

    if ($selectedYear != (date('Y') - 1)) {
        echo "<script>showPopup('Chỉ được phép cập nhật dữ liệu cho năm hiện tại - 1 (" . (date('Y') - 1) . ")', 'error');</script>";
    } else {
        if ($exists) {
            // Thực hiện cập nhật trực tiếp không cần xác nhận
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
                ':employeeID' => $employeeID, ':result_year' => $selectedYear,
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
            // Thực hiện insert mới
            $stmt = $conn->prepare("
                INSERT INTO giangday (employeeID, result_year, muc1_tong_tiet, muc1_sv_tren_40, muc1_tong_sv, muc1_gio_quy_doi, muc2_tong_tiet, muc2_gio_quy_doi, muc3_tong_tiet, muc3_gio_quy_doi, muc4_tong_tiet, muc4_gio_quy_doi, muc5_tong_tiet, muc5_sv_tren_40, muc5_tong_sv, muc5_gio_quy_doi, muc6_tong_tiet, muc6_sv_tren_30, muc6_tong_sv, muc6_gio_quy_doi, muc7_tong_ngay, muc7_sv_tren_25, muc7_tong_sv, muc7_gio_quy_doi, muc8_tong_tin_chi, muc8_gio_quy_doi, muc9_tong_ngay, muc9_sv_tren_40, muc9_tong_sv, muc9_them_gv, muc9_gio_quy_doi, tong_gio_quy_doi)
                VALUES (:employeeID, :result_year, :muc1_tong_tiet, :muc1_sv_tren_40, :muc1_tong_sv, :muc1_gio_quy_doi, :muc2_tong_tiet, :muc2_gio_quy_doi, :muc3_tong_tiet, :muc3_gio_quy_doi, :muc4_tong_tiet, :muc4_gio_quy_doi, :muc5_tong_tiet, :muc5_sv_tren_40, :muc5_tong_sv, :muc5_gio_quy_doi, :muc6_tong_tiet, :muc6_sv_tren_30, :muc6_tong_sv, :muc6_gio_quy_doi, :muc7_tong_ngay, :muc7_sv_tren_25, :muc7_tong_sv, :muc7_gio_quy_doi, :muc8_tong_tin_chi, :muc8_gio_quy_doi, :muc9_tong_ngay, :muc9_sv_tren_40, :muc9_tong_sv, :muc9_them_gv, :muc9_gio_quy_doi, :tong_gio_quy_doi)
            ");
            $stmt->execute([
                ':employeeID' => $employeeID, ':result_year' => $selectedYear,
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

// Lấy dữ liệu thống kê tổng giờ từ các bảng cho sidebar-right
$tongGioChuan = 0;
$tongGioNCKH = 0;
$tongGioNhiemVuKhac = 0;

// Get note value for the current year
$stmt = $conn->prepare("SELECT note FROM bai_bao_history WHERE employeeID = :employeeID AND result_year = :result_year ORDER BY id DESC LIMIT 1");
$stmt->execute([
    ':employeeID' => $employeeID,
    ':result_year' => date('Y')
]);
$noteValue = $stmt->fetchColumn();

// Calculate standard hours based on note value
$standardTeachingHours = 330; // Default value
$standardResearchHours = 590; // Default value
$standardOtherTasksHours = 180; // Default value

switch ($noteValue) {
    case 1:
        $standardTeachingHours = 270;
        $standardResearchHours = 770;
        break;
    case 2:
        $standardTeachingHours = 300;
        $standardResearchHours = 680;
        break;
    default:
        // Keep default values
        break;
}

$tables = ['giangday', 'coi_thi', 'ra_de_thi', 'tot_nghiep', 'hoi_dong'];
foreach ($tables as $table) {
    $stmt = $conn->prepare("SELECT tong_gio_quy_doi FROM $table WHERE employeeID = :employeeID AND result_year = :result_year");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $statsYear]);
    $tongGioChuan += floatval($stmt->fetchColumn() ?: 0);
}

// Add training days calculation
$stmt = $conn->prepare("SELECT so_ngay FROM dien_tap WHERE employeeID = :employeeID AND result_year = :result_year");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $statsYear]);
$soNgayDienTap = floatval($stmt->fetchColumn() ?: 0);
$tongGioChuan += ($soNgayDienTap * 2.5);

$stmt = $conn->prepare("SELECT SUM(tong_gio_nckh) FROM tong_hop_nckh WHERE employeeID = :employeeID AND result_year = :result_year");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $statsYear]);
$tongGioNCKH = floatval($stmt->fetchColumn() ?: 0);

$stmt = $conn->prepare("SELECT SUM(total_completed_hours) FROM total_hours WHERE employee_id = :employeeID AND year = :result_year");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $statsYear]);
$tongGioNhiemVuKhac = floatval($stmt->fetchColumn() ?: 0);

try {
    $stmt = $conn->prepare("SELECT image FROM employee WHERE employeeID = :employeeID");
    $stmt->execute([':employeeID' => $employeeID]);
    $uploadDir = '../assets/uploads/';
    $defaultImage = '../../assets/images/avatar-default.png';
    $imageName = $employeeID . '.jpg';
    $imagePath = $uploadDir . $imageName;
    $avatarSrc = file_exists($imagePath) ? $imagePath : $defaultImage;
} catch (Exception $e) {
    error_log("Lỗi: " . $e->getMessage());
    $avatarSrc = '../../assets/images/avatar-default.png';
}

// Thêm code tự động lưu giá trị mặc định cho bảng giangday
$currentYear = date('Y');

// Kiểm tra xem đã có dữ liệu cho năm hiện tại chưa
$checkExistingData = $conn->prepare("SELECT COUNT(*) FROM giangday WHERE employeeID = :employeeID AND result_year = :result_year");
$checkExistingData->execute([
    ':employeeID' => $employeeID,
    ':result_year' => $currentYear
]);
$exists = $checkExistingData->fetchColumn();

// Nếu chưa có dữ liệu cho năm hiện tại, thực hiện insert với giá trị mặc định
if (!$exists) {
    $insertDefaultValues = $conn->prepare("
        INSERT INTO giangday (
            employeeID, result_year, 
            muc1_tong_tiet, muc1_sv_tren_40, muc1_tong_sv, muc1_gio_quy_doi,
            muc2_tong_tiet, muc2_gio_quy_doi,
            muc3_tong_tiet, muc3_gio_quy_doi,
            muc4_tong_tiet, muc4_gio_quy_doi,
            muc5_tong_tiet, muc5_sv_tren_40, muc5_tong_sv, muc5_gio_quy_doi,
            muc6_tong_tiet, muc6_sv_tren_30, muc6_tong_sv, muc6_gio_quy_doi,
            muc7_tong_ngay, muc7_sv_tren_25, muc7_tong_sv, muc7_gio_quy_doi,
            muc8_tong_tin_chi, muc8_gio_quy_doi,
            muc9_tong_ngay, muc9_sv_tren_40, muc9_tong_sv, muc9_them_gv, muc9_gio_quy_doi,
            tong_gio_quy_doi,
            ngay_cap_nhat
        ) VALUES (
            :employeeID, :result_year,
            0, 0, 0, 0,
            0, 0,
            0, 0,
            0, 0, 0, 0,
            0, 0, 0, 0,
            0, 0, 0, 0,
            0, 0,
            0, 0, 0, 0, 0,
            0,
            CURRENT_TIMESTAMP
        )
    ");
    
    $insertDefaultValues->execute([
        ':employeeID' => $employeeID,
        ':result_year' => $currentYear
    ]);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý nghiệp vụ giảng viên</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; overflow-x: hidden; }
        .user-header { position: fixed; top: 0; left: 0; width: 100%; display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background-color: white; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); z-index: 1000; }
        .user-profile { display: flex; align-items: center; gap: 10px; position: relative; cursor: pointer; }
        .user-profile:hover .subnav { display: block; }
        .subnav { display: none; position: absolute; top: 100%; right: 0; background-color: white; min-width: 200px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); border-radius: 4px; z-index: 1000; }
        .subnav li { list-style: none; }
        .subnav li a { display: block; padding: 12px 20px; color: #223771; text-decoration: none; font-size: 14px; transition: all 0.3s ease; }
        .subnav li a:hover { background-color: #f5f5f5; color: #f8843d; }
        .subnav li a i { margin-right: 10px; width: 16px; }
        .icon-symbol { color: #223771; font-size: 16px; }
        .icon-symbol_more { margin-left: 5px; font-size: 12px; color: #6c757d; }
        .user-name-container { text-align: left; }
        .user-name { font-size: 16px; font-weight: bold; color: #223771; }
        .user-role { font-size: 14px; color: #6c757d; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: #223771; display: flex; align-items: center; justify-content: center; }
        .user-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .breadcrumb { font-size: 14px; color: #6c757d; }
        .breadcrumb a { color: #223771; text-decoration: none; }
        .breadcrumb i { margin: 0 8px; }
        .container { display: flex; margin-top: 70px; }
        .sidebar-left { 
            position: fixed; 
            top: 70px; 
            left: 0; 
            width: 250px; 
            background-color: #223771; 
            height: calc(100vh - 70px); 
            padding: 0; 
            z-index: 999;
            overflow-y: auto; /* Thêm thuộc tính này */
        }
        /* Tùy chỉnh thanh cuộn */
        .sidebar-left::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-left::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar-left::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .sidebar-left::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        .sidebar-right { position: fixed; top: 70px; right: 0; width: 250px; background-color: #fff; height: calc(100vh - 70px); padding: 10px; z-index: 999; display: flex; flex-direction: column; align-items: center; overflow-y: auto; }
        .chart-container { 
            width: 150px;  /* Giảm từ 200px xuống 150px */
            height: 150px; /* Giảm từ 200px xuống 150px */
            margin-bottom: 80px;
        }
        .chart-title { 
            color: purple; 
            font-size: 14px;
            text-align: center; 
            margin-bottom: 5px; 
            font-weight: bold;
        }
        .target-hours { 
            font-size: 14px; /* Tăng từ 12px lên 14px */
            text-align: center; 
            margin-top: 5px; 
            font-weight: bold;
        }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-item { padding: 12px 24px; transition: all 0.3s ease; }
        .sidebar-item:hover { background-color: rgba(255, 255, 255, 0.1); }
        .sidebar-link { color: #d2ddfd; text-decoration: none; display: flex; align-items: center; font-size: 14px; padding: 10px 0; }
        .sidebar-link i { width: 20px; text-align: center; margin-right: 8px; }
        .sidebar-link:hover { color: #f8843d; }
        .sidebar-link .toggle-icon { margin-left: auto; cursor: pointer; }
        .sidebar-link.active { color: #f8843d; font-weight: bold; }
        .submenu {
            list-style: none;
            padding-left: 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            opacity: 0;
            visibility: hidden;
        }
        .submenu-item {
            display: none;
            padding: 10px 0;
            transition: opacity 0.3s ease;
        }
        .submenu.submenu-active {
            max-height: 1000px; /* Đủ cao để chứa tất cả submenu-item */
            opacity: 1;
            visibility: visible;
        }
        .submenu-active .submenu-item {
            display: block;
            animation: fadeIn 0.3s ease forwards;
        }
        .submenu-link { color: #d2ddfd; text-decoration: none; font-size: 13px; display: flex; align-items: center; }
        .submenu-link i { width: 20px; text-align: center; margin-right: 8px; }
        .submenu-link:hover { color: #f8843d; }
        .toggle-icon {
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        .toggle-icon.rotate {
            transform: rotate(180deg);
        }
        .main-content { flex: 1; padding: 20px; margin-left: 250px; margin-right: 250px; }
        .research-form { background: white; padding: 20px; }
        .form-title { color: #223771; font-size: 24px; margin-bottom: 20px; text-align: center; }
        .teaching-registration-form { display: grid; gap: 20px; max-width: 800px; margin: 0 auto; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-label { font-size: 16px; color: #223771; font-weight: 500; }
        .form-input { padding: 12px; font-size: 14px; border: 1px solid #d2ddfd; border-radius: 4px; transition: border-color 0.3s ease; }
        .form-input:focus { outline: none; border-color: #f8843d; box-shadow: 0 0 5px rgba(248, 132, 61, 0.3); }
        .form-input::placeholder { color: #6c757d; opacity: 0.8; }
        .form-actions { display: flex; gap: 15px; justify-content: center; margin-top: 20px; }
        .submit-btn, .reset-btn { padding: 12px 30px; font-size: 16px; border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.3s ease; }
        .submit-btn { background-color: #223771; color: white; }
        .submit-btn:hover { background-color: #f8843d; }
        .reset-btn { background-color: #6c757d; color: white; }
        .reset-btn:hover { background-color: #495057; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.5); }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 8px; position: relative; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover, .close:focus { color: black; text-decoration: none; }
        .popup { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3); z-index: 1001; text-align: center; width: 300px; max-width: 400px; min-width: 250px; overflow-y: auto; }
        .popup.success { border: 2px solid #28a745; }
        .popup.error { border: 2px solid #dc3545; }
        .popup.confirm { border: 2px solid #f8843d; }
        .popup p { margin: 0 0 15px 0; font-size: 16px; word-wrap: break-word; }
        .popup button { background-color: #223771; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px; }
        .popup button:hover { background-color: #f8843d; }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
    </style>
</head>
<body>  
    <div class="user-header">
        <div class="breadcrumb">
            <a href="../../trangchu.php">Trang chủ</a>
            <i class="fas fa-chevron-right"></i>
            <a href="formRegister.php">Quản lý nghiệp vụ giảng viên</a>
            <?php
            if (in_array($currentPage, ['teaching', 'council', 'watching', 'graduate', 'question'])) {
                echo '<i class="fas fa-chevron-right"></i><a href="#">Thống kê giảng dạy</a>';
            } elseif ($currentPage === 'research') {
                echo '<i class="fas fa-chevron-right"></i><a href="#">Nghiên cứu khoa học</a>';
            } elseif ($currentPage === 'other_tasks') {
                echo '<i class="fas fa-chevron-right"></i><a href="#">Nhiệm vụ khác</a>';
                if ($subpage === 'nv_khac_a') {
                    echo '<i class="fas fa-chevron-right"></i><a href="#">Tham gia các hội đồng, công tác đoàn thể</a>';
                } elseif ($subpage === 'nv_khac_b') {
                    echo '<i class="fas fa-chevron-right"></i><a href="#">Nhiệm vụ nhà trường phân công</a>';
                    if ($section === 'b1') {
                        echo '<i class="fas fa-chevron-right"></i><a href="#">Mục 1</a>';
                    } elseif ($section === 'b2_3') {
                        echo '<i class="fas fa-chevron-right"></i><a href="#">Mục 2 & 3</a>';
                    } elseif ($section === 'b4_5_6') {
                        echo '<i class="fas fa-chevron-right"></i><a href="#">Mục 4, 5 & 6</a>';
                    }
                }
            }
            ?>
        </div>
        <div class="user-profile">
            <div class="user-name-container">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['fullName']); ?></div>
            </div>
            <div class="user-avatar">
                <img id="avatar-preview" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Avatar">
            </div>
            <i class="icon-symbol_more fas fa-chevron-down"></i>
            <ul class="subnav">
                <li><a href="#" onclick="openAccountModal()"><i class="fas fa-cog"></i>Cài đặt tài khoản</a></li>
                <li><a href="#" onclick="openPasswordModal()"><i class="fas fa-key"></i>Đổi mật khẩu</a></li>
                <li><a href="../../login/logout.php"><i class="fas fa-sign-out-alt"></i>Đăng xuất</a></li>
            </ul>
        </div>
    </div>

    <div class="container">
        <div class="sidebar-left">
            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="#" class="sidebar-link <?php echo $currentPage === 'teaching' ? 'active' : ''; ?>">
                        <i class="fas fa-user-graduate"></i><span>Thống kê giảng dạy</span>
                        <i class="fas fa-chevron-down toggle-icon" onclick="toggleSubmenu(event)"></i>
                    </a>
                    <ul class="submenu" id="teaching-submenu">
                        <li class="submenu-item giangday"><a href="formRegister.php?page=teaching" class="submenu-link"><i class="fas fa-chalkboard-teacher"></i><span>Giảng dạy, hướng dẫn thực hành, thực tập</span></a></li>
                        <li class="submenu-item radethi"><a href="formRegister.php?page=question" class="submenu-link"><i class="fas fa-question-circle"></i><span>Xây dựng ngân hàng câu hỏi thi (NHCHT)</span></a></li>
                        <li class="submenu-item coithi"><a href="formRegister.php?page=watching" class="submenu-link"><i class="fas fa-file-alt"></i><span>Thi học kỳ</span></a></li>
                        <li class="submenu-item totnghiep"><a href="formRegister.php?page=graduate" class="submenu-link"><i class="fas fa-graduation-cap"></i><span>Thực tập tốt nghiệp và thi tốt nghiệp</span></a></li>
                        <li class="submenu-item hoidong"><a href="formRegister.php?page=council" class="submenu-link"><i class="fas fa-book"></i><span>Hội đồng thẩm định và lựa chọn sách phục vụ đào tạo</span></a></li>
                    </ul>
                </li>
                <li class="sidebar-item">
                    <a href="#" class="sidebar-link <?php echo $currentPage === 'research' ? 'active' : ''; ?>">
                        <i class="fas fa-microscope"></i><span>Nghiên cứu khoa học</span>
                        <i class="fas fa-chevron-down toggle-icon" onclick="toggleSubmenu(event)"></i>
                    </a>
                    <ul class="submenu" id="research-submenu">
                        <li class="submenu-item infor"><a href="formRegister.php?page=research_info" class="submenu-link"><i class="fas fa-info-circle"></i><span>Thông tin giảng viên NCKH</span></a></li>
                        <li class="submenu-item nckhcc"><a href="formRegister.php?page=research_levels" class="submenu-link"><i class="fas fa-layer-group"></i><span>Nghiên cứu khoa học các cấp</span></a></li>
                        <li class="submenu-item guide"><a href="formRegister.php?page=student_guidance" class="submenu-link"><i class="fas fa-user-friends"></i><span>Hướng dẫn SV làm nghiên cứu khoa học</span></a></li>
                        <li class="submenu-item article"><a href="formRegister.php?page=article_writing" class="submenu-link"><i class="fas fa-newspaper"></i><span>Viết bài báo</span></a></li>
                        <li class="submenu-item book"><a href="formRegister.php?page=book_writing" class="submenu-link"><i class="fas fa-book"></i><span>Viết sách</span></a></li>
                    </ul>
                </li>
                <li class="sidebar-item">
                    <a href="formRegister.php?page=other_tasks" class="sidebar-link <?php echo $currentPage === 'other_tasks' ? 'active' : ''; ?>">
                        <i class="fas fa-newspaper"></i><span>Nhiệm vụ khác</span>
                        <i class="fas fa-chevron-down toggle-icon" onclick="toggleSubmenu(event, 'other-tasks-submenu')"></i>
                    </a>
                    <ul class="submenu <?php echo $currentPage === 'other_tasks' ? 'submenu-active' : ''; ?>" id="other-tasks-submenu">
                        <li class="submenu-item nv_a"><a href="formRegister.php?page=other_tasks&subpage=nv_khac_a" class="submenu-link"><i class="fas fa-users"></i><span>Tham gia các hội đồng, công tác đoàn thể</span></a></li>
                        <li class="submenu-item">
                            <a href="formRegister.php?page=other_tasks&subpage=nv_khac_b&section=b" class="sidebar-link <?php echo $subpage === 'nv_khac_b' ? 'active' : ''; ?>">
                                <i class="fas fa-tasks"></i><span>Nhiệm vụ nhà trường phân công</span>
                                <i class="fas fa-chevron-down toggle-icon" onclick="toggleSubmenu(event, 'nv-khac-b-submenu')"></i>
                            </a>
                            <ul class="submenu <?php echo $subpage === 'nv_khac_b' ? 'submenu-active' : ''; ?>" id="nv-khac-b-submenu">
                                <li class="submenu-item b1"><a href="formRegister.php?page=other_tasks&subpage=nv_khac_b&section=b1" class="submenu-link <?php echo $section === 'b1' ? 'active' : ''; ?>"><i class="fas fa-circle"></i><span>Mục 1</span></a></li>
                                <li class="submenu-item b2_3"><a href="formRegister.php?page=other_tasks&subpage=nv_khac_b&section=b2_3" class="submenu-link <?php echo $section === 'b2_3' ? 'active' : ''; ?>"><i class="fas fa-circle"></i><span>Mục 2 & 3</span></a></li>
                                <li class="submenu-item b4_5_6"><a href="formRegister.php?page=other_tasks&subpage=nv_khac_b&section=b4_5_6" class="submenu-link <?php echo $section === 'b4_5_6' ? 'active' : ''; ?>"><i class="fas fa-circle"></i><span>Mục 4, 5 & 6</span></a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>

        <div class="sidebar-right">
            <div style="width: 100%; text-align: center;">
                <select id="statsYear" name="stats_year" onchange="updateStats(this.value)" style="padding: 8px; font-size: 14px; border-radius: 4px; background-color: #fff; color: #223771; width: 80%;">
                    <?php
                    for ($i = date('Y')-5; $i <= date('Y'); $i++) {
                        echo "<option value='$i'" . ($i == date('Y') ? " selected" : "") . ">$i</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="chart-container">
                <div class="chart-title">Giảng dạy</div>
                <div class="target-hours" id="teaching-status" style="color: <?php 
                    if ($tongGioChuan == $targetTeachingHours) {
                        echo '#28a745';
                    } elseif ($tongGioChuan > $targetTeachingHours) {
                        echo '#006d77';
                    } else {
                        echo '#dc3545';
                    }
                ?>">
                    <?php
                    if ($tongGioChuan == $targetTeachingHours) {
                        echo "Hoàn thành";
                    } elseif ($tongGioChuan > $targetTeachingHours) {
                        echo "Vượt (+" . number_format($tongGioChuan - $targetTeachingHours, 1) . " giờ)";
                    } else {
                        echo "Không hoàn thành (-" . number_format($targetTeachingHours - $tongGioChuan, 1) . " giờ)";
                    }
                    ?>
                </div>
                <canvas id="teachingChart"></canvas>
            </div>
            <div class="chart-container">
                <div class="chart-title">Thống kê NCKH</div>
                <div class="target-hours" id="research-status" style="color: <?php 
                    if ($tongGioNCKH == $standardResearchHours) {
                        echo '#28a745';  // Màu xanh cho hoàn thành
                    } elseif ($tongGioNCKH > $standardResearchHours) {
                        echo '#006d77';  // Màu xanh dương cho vượt
                    } else {
                        echo '#dc3545';  // Màu đỏ cho Không đạt
                    }
                ?>">
                    <?php
                    if ($tongGioNCKH == $standardResearchHours) {
                        echo "Hoàn thành";
                    } elseif ($tongGioNCKH > $standardResearchHours) {
                        echo "Vượt (+" . number_format($tongGioNCKH - $standardResearchHours, 1) . " giờ)";
                    } else {
                        echo "Không hoàn thành (-" . number_format($standardResearchHours - $tongGioNCKH, 1) . " giờ)";
                    }
                    ?>
                </div>
                <canvas id="researchChart"></canvas>
            </div>
            <div class="chart-container">
                <div class="chart-title">Thống kê NV Khác</div>
                <div class="target-hours" id="other-tasks-status" style="color: <?php 
                    if ($tongGioNhiemVuKhac == $targetOtherTasksHours) {
                        echo '#28a745';  // Màu xanh cho hoàn thành
                    } elseif ($tongGioNhiemVuKhac > $targetOtherTasksHours) {
                        echo '#006d77';  // Màu xanh dương cho vượt
                    } else {
                        echo '#dc3545';  // Màu đỏ cho Không đạt
                    }
                ?>">
                    <?php
                    if ($tongGioNhiemVuKhac == $targetOtherTasksHours) {
                        echo "Hoàn thành";
                    } elseif ($tongGioNhiemVuKhac > $targetOtherTasksHours) {
                        echo "Vượt (+" . number_format($tongGioNhiemVuKhac - $targetOtherTasksHours, 1) . " giờ)";
                    } else {
                        echo "Không hoàn thành (-" . number_format($targetOtherTasksHours - $tongGioNhiemVuKhac, 1) . " giờ)";
                    }
                    ?>
                </div>
                <canvas id="otherTasksChart"></canvas>
            </div>
        </div>

        <div class="main-content">
            <div class="research-form">
                <?php
                switch ($currentPage) {
                    case 'teaching':
                        if (!file_exists('./giangDay/giangDay.php')) {
                            echo '<div style="text-align: center; color: red;"><h2>Lỗi: Không tìm thấy file giangDay.php. Vui lòng kiểm tra thư mục ./giangDay/.</h2></div>';
                            break;
                        }
                        include './giangDay/giangDay.php';
                        break;
                    case 'question':
                        if (!file_exists('./giangDay/raDeThi.php')) die("Lỗi: Không tìm thấy file raDeThi.php");
                        include './giangDay/raDeThi.php';
                        break;
                    case 'watching':
                        if (!file_exists('./giangDay/coiThi.php')) die("Lỗi: Không tìm thấy file coiThi.php");
                        include './giangDay/coiThi.php';
                        break;
                    case 'graduate':
                        if (!file_exists('./giangDay/totNghiep.php')) die("Lỗi: Không tìm thấy file totNghiep.php");
                        include './giangDay/totNghiep.php';
                        break;
                    case 'council':
                        if (!file_exists('./giangDay/hoiDong.php')) die("Lỗi: Không tìm thấy file hoiDong.php");
                        include './giangDay/hoiDong.php';
                        break;
                    case 'research':
                        echo '<h2 class="form-title">Nghiên cứu khoa học</h2>';
                        break;
                    case 'research_info':
                        if (!file_exists('./NCKH/thongTinGVNCKH.php')) die("Lỗi: Không tìm thấy file thongTinGVNCKH.php");
                        include './NCKH/thongTinGVNCKH.php';
                        break;
                    case 'research_levels':
                        if (!file_exists('./NCKH/NCKHCC.php')) die("Lỗi: Không tìm thấy file NCKHCC.php");
                        include './NCKH/NCKHCC.php';
                        break;
                    case 'student_guidance':
                        if (!file_exists('./NCKH/huongDanSV.php')) die("Lỗi: Không tìm thấy file huongDanSV.php");
                        include './NCKH/huongDanSV.php';
                        break;
                    case 'article_writing':
                        if (!file_exists('./NCKH/vietBaiBao.php')) die("Lỗi: Không tìm thấy file vietBaiBao.php");
                        include './NCKH/vietBaiBao.php';
                        break;
                    case 'book_writing':
                        if (!file_exists('./NCKH/vietSach.php')) die("Lỗi: Không tìm thấy file vietSach.php");
                        include './NCKH/vietSach.php';
                        break;
                    case 'other_tasks':
                        $subpage = isset($_GET['subpage']) ? $_GET['subpage'] : 'nv_khac';
                        $section = isset($_GET['section']) ? $_GET['section'] : 'b1';
                        if ($subpage === 'nv_khac_a') {
                            include './NV_Khac/NV_Khac_A.php';
                        } elseif ($subpage === 'nv_khac_b') {
                            switch ($section) {
                                case 'b': include './NV_Khac/NV_Khac_B.php'; break;
                                case 'b1': include './NV_Khac/NV_Khac_B_1.php'; break;
                                case 'b2_3': include './NV_Khac/NV_Khac_B_2_3.php'; break;
                                case 'b4_5_6': include './NV_Khac/NV_Khac_B_4_5_6.php'; break;
                                default: include './NV_Khac/NV_Khac_B_1.php'; break;
                            }
                        } else {
                            include './NV_Khac/NV_Khac.php';
                        }
                        break;
                    default:
                        echo '<h2 class="form-title">Chào mừng đến với hệ thống quản lý nghiệp vụ giảng viên</h2>';
                        break;
                }
                ?>
            </div>
        </div>
    </div>

    <div id="accountModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAccountModal()">×</span>
            <iframe id="accountFrame" src="" width="100%" height="600px" frameborder="0"></iframe>
        </div>
    </div>

    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closePasswordModal()">×</span>
            <iframe id="passwordFrame" src="" width="100%" height="600px" frameborder="0"></iframe>
        </div>
    </div>

    <div class="overlay" id="overlay"></div>
    <div class="popup" id="popup">
        <p id="popup-message"></p>
        <button onclick="closePopup(); location.reload();">Đóng</button>
    </div>

    <div class="popup confirm" id="confirmPopup">
        <p id="confirm-message"></p>
        <button onclick="confirmAction()">Xác nhận</button>
        <button onclick="closeConfirmPopup()">Hủy</button>
    </div>


    <script>
let confirmCallback = null;

function openAccountModal() {
    document.getElementById('accountFrame').src = './accountSetting/account_setting.php';
    document.getElementById('accountModal').style.display = 'block';
}
function closeAccountModal() { document.getElementById('accountModal').style.display = 'none'; document.getElementById('accountFrame').src = ''; }
function openPasswordModal() {
    document.getElementById('passwordFrame').src = './accountSetting/change_password.php';
    document.getElementById('passwordModal').style.display = 'block';
}
function closePasswordModal() { document.getElementById('passwordModal').style.display = 'none'; document.getElementById('passwordFrame').src = ''; }
window.onclick = function(event) {
    if (event.target == document.getElementById('accountModal')) closeAccountModal();
    else if (event.target == document.getElementById('passwordModal')) closePasswordModal();
};
function showPopup(message, type) {
    const popup = document.getElementById('popup'), overlay = document.getElementById('overlay');
    document.getElementById('popup-message').textContent = message;
    popup.className = 'popup ' + type;
    popup.style.display = 'block';
    overlay.style.display = 'block';
}
function closePopup() { document.getElementById('popup').style.display = 'none'; document.getElementById('overlay').style.display = 'none'; }
function showConfirmPopup(message, callback) {
    const confirmPopup = document.getElementById('confirmPopup'), overlay = document.getElementById('overlay');
    document.getElementById('confirm-message').textContent = message;
    confirmPopup.style.display = 'block';
    overlay.style.display = 'block';
    confirmCallback = callback;
}
function closeConfirmPopup() {
    document.getElementById('confirmPopup').style.display = 'none';
    document.getElementById('overlay').style.display = 'none';
    confirmCallback = null;
}
function confirmAction() {
    if (confirmCallback) confirmCallback();
    closeConfirmPopup();
}
function toggleSubmenu(event, submenuId) {
    event.preventDefault();
    event.stopPropagation();
    
    const clickedItem = event.currentTarget.closest('.sidebar-item, .submenu-item');
    const submenu = submenuId ? document.getElementById(submenuId) : clickedItem.querySelector('.submenu');
    const toggleIcon = event.currentTarget;
    
    if (submenu) {
        const isActive = submenu.classList.contains('submenu-active');
        
        // Lưu trạng thái vào localStorage
        if (submenuId) {
            if (isActive) {
                localStorage.removeItem(`submenu_${submenuId}`);
            } else {
                localStorage.setItem(`submenu_${submenuId}`, 'active');
            }
        }
        
        if (submenuId && submenuId.includes('nv-khac-b')) {
            const parentSubmenu = clickedItem.closest('.submenu');
            if (parentSubmenu) {
                parentSubmenu.querySelectorAll('.submenu').forEach(menu => {
                    if (menu !== submenu) {
                        menu.classList.remove('submenu-active');
                        const icon = menu.previousElementSibling?.querySelector('.toggle-icon');
                        if (icon) icon.classList.remove('rotate');
                        // Xóa trạng thái của các submenu khác trong localStorage
                        if (menu.id) {
                            localStorage.removeItem(`submenu_${menu.id}`);
                        }
                    }
                });
            }
        }

        submenu.classList.toggle('submenu-active');
        toggleIcon.classList.toggle('rotate');

        if (!isActive && submenuId) {
            const parentSubmenu = clickedItem.closest('.submenu');
            if (parentSubmenu && !parentSubmenu.classList.contains('submenu-active')) {
                parentSubmenu.classList.add('submenu-active');
                const parentIcon = parentSubmenu.previousElementSibling?.querySelector('.toggle-icon');
                if (parentIcon) parentIcon.classList.add('rotate');
                // Lưu trạng thái của parent submenu
                if (parentSubmenu.id) {
                    localStorage.setItem(`submenu_${parentSubmenu.id}`, 'active');
                }
            }
        }
    }
}

// Thêm hàm để khôi phục trạng thái submenu
function restoreSubmenuState() {
    // Khôi phục trạng thái cho tất cả các submenu
    document.querySelectorAll('.submenu').forEach(submenu => {
        if (submenu.id) {
            const isActive = localStorage.getItem(`submenu_${submenu.id}`);
            if (isActive) {
                submenu.classList.add('submenu-active');
                const toggleIcon = submenu.previousElementSibling?.querySelector('.toggle-icon');
                if (toggleIcon) {
                    toggleIcon.classList.add('rotate');
                }
            } else {
                submenu.classList.remove('submenu-active');
                const toggleIcon = submenu.previousElementSibling?.querySelector('.toggle-icon');
                if (toggleIcon) {
                    toggleIcon.classList.remove('rotate');
                }
            }
        }
    });
}

function updateStats(year) {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_stats&year=' + year
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Lỗi mạng: ${response.status} - ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        // Cập nhật biểu đồ Giảng dạy
        const teachingChart = Chart.getChart('teachingChart');
        const totalTeachingHours = data.tongGioChuan;
        const targetTeachingHours = data.targetTeachingHours;
        let teachingColors, teachingLabel, teachingData, teachingTextColor;

        // Cập nhật status text và màu sắc
        const teachingStatus = document.getElementById('teaching-status');
        if (totalTeachingHours < targetTeachingHours) {
            teachingStatus.textContent = `Không hoàn thành (-${(targetTeachingHours - totalTeachingHours).toFixed(1)} gc)`;
            teachingStatus.style.color = '#dc3545';
        } else if (totalTeachingHours === targetTeachingHours) {
            teachingStatus.textContent = 'Hoàn thành';
            teachingStatus.style.color = '#28a745';
        } else {
            teachingStatus.textContent = `Vượt (+${(totalTeachingHours - targetTeachingHours).toFixed(1)} gc)`;
            teachingStatus.style.color = '#006d77';
        }

        if (totalTeachingHours < targetTeachingHours) {
            teachingColors = ['#dc3545', '#e9ecef'];
            teachingLabel = `Không hoàn thành (-${(targetTeachingHours - totalTeachingHours).toFixed(1)} gc)`;
            teachingData = [totalTeachingHours, targetTeachingHours - totalTeachingHours];
            teachingTextColor = '#dc3545';
        } else if (totalTeachingHours === targetTeachingHours) {
            teachingColors = ['#28a745'];
            teachingLabel = 'Hoàn thành';
            teachingData = [totalTeachingHours, 0];
            teachingTextColor = '#28a745';
        } else {
            teachingColors = ['#006d77'];
            teachingLabel = `Vượt (+${(totalTeachingHours - targetTeachingHours).toFixed(1)} gc)`;
            teachingData = [totalTeachingHours, 0];
            teachingTextColor = '#006d77';
        }

        teachingChart.data.labels = ['Đã đạt', 'Còn lại'];
        teachingChart.data.datasets[0].data = teachingData;
        teachingChart.data.datasets[0].backgroundColor = teachingColors;
        teachingChart.options.plugins.title.text = teachingLabel;
        teachingChart.options.plugins.title.color = teachingTextColor;
        teachingChart.update();

        // Cập nhật biểu đồ NCKH
        const researchChart = Chart.getChart('researchChart');
        const totalResearchHours = data.tongGioNCKH;
        const targetResearchHours = data.dinhMucNCKH;
        let researchColors, researchLabel, researchData, researchTextColor;

        if (totalResearchHours < targetResearchHours) {
            researchColors = ['#dc3545', '#e9ecef'];
            researchLabel = `Không hoàn thành (-${(targetResearchHours - totalResearchHours).toFixed(1)} giờ hc)`;
            researchData = [totalResearchHours, targetResearchHours - totalResearchHours];
            researchTextColor = '#dc3545';
        } else if (totalResearchHours === targetResearchHours) {
            researchColors = ['#28a745'];
            researchLabel = 'Hoàn thành';
            researchData = [totalResearchHours, 0];
            researchTextColor = '#28a745';
        } else {
            researchColors = ['#006d77'];
            researchLabel = `Vượt (+${(totalResearchHours - targetResearchHours).toFixed(1)} giờ hc)`;
            researchData = [totalResearchHours, 0];
            researchTextColor = '#006d77';
        }

        researchChart.data.labels = ['Đã đạt', 'Còn lại'];
        researchChart.data.datasets[0].data = researchData;
        researchChart.data.datasets[0].backgroundColor = researchColors;
        researchChart.options.plugins.title.text = researchLabel;
        researchChart.options.plugins.title.color = researchTextColor;
        researchChart.update();

        // Cập nhật biểu đồ Nhiệm vụ khác (đã sửa)
        const otherTasksChart = Chart.getChart('otherTasksChart');
        const totalOtherTasksHours = data.tongGioNhiemVuKhac;
        const targetOtherTasksHours = data.targetOtherTasksHours;
        let otherTasksColors, otherTasksData;

        if (totalOtherTasksHours < targetOtherTasksHours) {
            otherTasksColors = ['#dc3545', '#e9ecef'];
            otherTasksData = [totalOtherTasksHours, targetOtherTasksHours - totalOtherTasksHours];
        } else if (totalOtherTasksHours === targetOtherTasksHours) {
            otherTasksColors = ['#28a745'];
            otherTasksData = [totalOtherTasksHours, 0];
        } else {
            otherTasksColors = ['#006d77'];
            otherTasksData = [totalOtherTasksHours, 0];
        }

        otherTasksChart.data.labels = ['Đã đạt', 'Còn lại'];
        otherTasksChart.data.datasets[0].data = otherTasksData;
        otherTasksChart.data.datasets[0].backgroundColor = otherTasksColors;
        otherTasksChart.options.plugins.title.display = false; // Ẩn nhãn hoàn toàn
        otherTasksChart.options.cutout = '50%'; // Đảm bảo kích thước to bằng hai biểu đồ kia
        otherTasksChart.update();

        // Cập nhật status cho NCKH
        const researchStatus = document.getElementById('research-status');
        if (data.tongGioNCKH < data.dinhMucNCKH) {
            researchStatus.textContent = `Không hoàn thành (-${(data.dinhMucNCKH - data.tongGioNCKH).toFixed(1)} giờ hc)`;
            researchStatus.style.color = '#dc3545';
        } else if (data.tongGioNCKH === data.dinhMucNCKH) {
            researchStatus.textContent = 'Hoàn thành';
            researchStatus.style.color = '#28a745';
        } else {
            researchStatus.textContent = `Vượt (+${(data.tongGioNCKH - data.dinhMucNCKH).toFixed(1)} giờ hc)`;
            researchStatus.style.color = '#006d77';
        }

        // Cập nhật status cho Nhiệm vụ khác
        const otherTasksStatus = document.getElementById('other-tasks-status');
        if (data.tongGioNhiemVuKhac < data.targetOtherTasksHours) {
            otherTasksStatus.textContent = `Không hoàn thành (-${(data.targetOtherTasksHours - data.tongGioNhiemVuKhac).toFixed(1)} giờ hc)`;
            otherTasksStatus.style.color = '#dc3545';
        } else if (data.tongGioNhiemVuKhac === data.targetOtherTasksHours) {
            otherTasksStatus.textContent = 'Hoàn thành';
            otherTasksStatus.style.color = '#28a745';
        } else {
            otherTasksStatus.textContent = `Vượt (+${(data.tongGioNhiemVuKhac - data.targetOtherTasksHours).toFixed(1)} giờ hc)`;
            otherTasksStatus.style.color = '#006d77';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Khôi phục trạng thái submenu khi trang load
    restoreSubmenuState();
    
    const tongGioChuan = <?php echo $tongGioChuan; ?>;
    const targetTeachingHours = <?php echo $targetTeachingHours; ?>;
    const tongGioNCKH = <?php echo $tongGioNCKH; ?>;
    const targetResearchHours = <?php echo $standardResearchHours; ?>;
    const tongGioNhiemVuKhac = <?php echo $tongGioNhiemVuKhac; ?>;
    const targetOtherTasksHours = <?php echo $targetOtherTasksHours; ?>;

    // Biểu đồ Giảng dạy
    let teachingColors, teachingLabel, teachingData, teachingTextColor;
    if (tongGioChuan < targetTeachingHours) {
        teachingColors = ['#dc3545', '#e9ecef'];
        teachingLabel = `Không hoàn thành (-${(targetTeachingHours - tongGioChuan).toFixed(1)} gc)`;
        teachingData = [tongGioChuan, targetTeachingHours - tongGioChuan];
        teachingTextColor = '#dc3545';
    } else if (tongGioChuan === targetTeachingHours) {
        teachingColors = ['#28a745'];
        teachingLabel = 'Hoàn thành';
        teachingData = [tongGioChuan, 0];
        teachingTextColor = '#28a745';
    } else {
        teachingColors = ['#006d77'];
        teachingLabel = `Vượt (+${(tongGioChuan - targetTeachingHours).toFixed(1)} gc)`;
        teachingData = [tongGioChuan, 0];
        teachingTextColor = '#006d77';
    }

    const teachingChartData = {
        labels: ['Đã đạt', 'Còn lại'],
        datasets: [{
            data: teachingData,
            backgroundColor: teachingColors,
            borderWidth: 0
        }]
    };

    // Biểu đồ NCKH
    let researchColors, researchLabel, researchData, researchTextColor;
    if (tongGioNCKH < targetResearchHours) {
        researchColors = ['#dc3545', '#e9ecef'];
        researchLabel = `Không hoàn thành (-${(targetResearchHours - tongGioNCKH).toFixed(1)} giờ hc)`;
        researchData = [tongGioNCKH, targetResearchHours - tongGioNCKH];
        researchTextColor = '#dc3545';
    } else if (tongGioNCKH === targetResearchHours) {
        researchColors = ['#28a745'];
        researchLabel = 'Hoàn thành';
        researchData = [tongGioNCKH, 0];
        researchTextColor = '#28a745';
    } else {
        researchColors = ['#006d77'];
        researchLabel = `Vượt (+${(tongGioNCKH - targetResearchHours).toFixed(1)} giờ hc)`;
        researchData = [tongGioNCKH, 0];
        researchTextColor = '#006d77';
    }

    const researchChartData = {
        labels: ['Đã đạt', 'Còn lại'],
        datasets: [{
            data: researchData,
            backgroundColor: researchColors,
            borderWidth: 0
        }]
    };

    // Biểu đồ Nhiệm vụ khác (đã sửa)
    let otherTasksColors, otherTasksData;
    if (tongGioNhiemVuKhac < targetOtherTasksHours) {
        otherTasksColors = ['#dc3545', '#e9ecef'];
        otherTasksData = [tongGioNhiemVuKhac, targetOtherTasksHours - tongGioNhiemVuKhac];
    } else if (tongGioNhiemVuKhac === targetOtherTasksHours) {
        otherTasksColors = ['#28a745'];
        otherTasksData = [tongGioNhiemVuKhac, 0];
    } else {
        otherTasksColors = ['#006d77'];
        otherTasksData = [tongGioNhiemVuKhac, 0];
    }

    const otherTasksChartData = {
        labels: ['Đã đạt', 'Còn lại'],
        datasets: [{
            data: otherTasksData,
            backgroundColor: otherTasksColors,
            borderWidth: 0
        }]
    };

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            title: {
                display: true,
                font: { size: 14 },
                padding: { top: 10, bottom: 10 }
            }
        },
        cutout: '50%' // Đảm bảo tất cả biểu đồ có kích thước hình tròn đồng nhất
    };

    new Chart(document.getElementById('teachingChart'), {
        type: 'doughnut',
        data: teachingChartData,
        options: {
            ...chartOptions,
            plugins: {
                ...chartOptions.plugins,
                title: { text: teachingLabel, color: teachingTextColor }
            }
        }
    });
    new Chart(document.getElementById('researchChart'), {
        type: 'pie',
        data: researchChartData,    
        options: {
            ...chartOptions,
            plugins: {
                ...chartOptions.plugins,
                title: { text: researchLabel, color: researchTextColor }
            }
        }
    });
    new Chart(document.getElementById('otherTasksChart'), {
        type: 'doughnut',
        data: otherTasksChartData,
        options: {
            ...chartOptions,
            plugins: {
                ...chartOptions.plugins,
                title: { display: false } // Ẩn nhãn hoàn toàn
            }
        }
    });

    updateStats(<?php echo $statsYear; ?>);
});

// Thêm đoạn code này vào phần script
document.getElementById('selectedYear').addEventListener('change', function() {
    const year = this.value;
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_exam_data&year=' + year
    })
    .then(response => response.json())
    .then(data => {
        // Cập nhật các giá trị vào form
        for(let i = 1; i <= 11; i++) {
            const input = document.querySelector(`input[name="muc${i}_so_cau"]`);
            if(input) {
                input.value = data[`muc${i}_so_cau`] || '';
            }
            
            // Cập nhật radio buttons cho các mục 1-6
            if(i <= 6) {
                const taskType = data[`muc${i}_task_type`];
                if(taskType) {
                    const radio = document.querySelector(`input[name="muc${i}_task_type"][value="${taskType}"]`);
                    if(radio) {
                        radio.checked = true;
                        // Trigger sự kiện change để cập nhật tính toán
                        radio.dispatchEvent(new Event('change'));
                    }
                }
            }
        }
        // Tính lại tổng giờ
        examInputs.forEach((input, index) => {
            calculateConvertedHours(input, standardHourCells[index], convertedHoursCells[index]);
        });
    });
});
</script>
</body>
</html>