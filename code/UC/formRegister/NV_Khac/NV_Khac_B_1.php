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
                           WHERE employee_id = ? AND year = ? AND section = 'b1'");
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
    // === Thay đổi: Xóa toàn bộ dữ liệu cũ trước khi lưu mới để ghi đè ===
    $delete_sql = "DELETE FROM task_registrations WHERE employee_id = ? AND year = ? AND section = 'b1'";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->execute([$employee_id, $selected_year]);
    // === Kết thúc thay đổi ===

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
                    VALUES (?, ?, ?, ?, 'b1', ?, ?)";
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
                                      WHERE employee_id = ? AND task_id = ? AND year = ? AND section = 'b1'";
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
<!-- Phần HTML và JavaScript giữ nguyên như file đã chỉnh sửa trước đó -->

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký nhiệm vụ khác - Mục 1</title>
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

    /* === Thay đổi 3: Thêm style giống NV_Khac_B_2_3 === */
    .b1>a {
        color: #f8843d;
    }

    /* === Kết thúc Thay đổi 3 === */

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
            <h2 class="form-title">Đăng ký nhiệm vụ khác - Mục 1</h2>
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
                        <tr class="task-group" data-group="group-b-1">
                            <td>1</td>
                            <td>Tham gia các hoạt động phục vụ công tác đào tạo, khoa học, phục vụ người học và phục vụ
                                cộng đồng</td>
                            <td class="checkbox-column"><input type="checkbox" class="group-checkbox"
                                    data-group="group-b-1"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                        $tasks_1 = [
                            '1.1_ho_tro_giai_dap_sinh_vien' => ['Hỗ trợ giải đáp thắc mắc của sinh viên qua email, diễn đàn', 'Theo thời gian thực tế'],
                            '1.2_xay_dung_chuong_trinh_dao_tao' => ['Xây dựng chương trình đào tạo theo Quyết định của Trường, không được chi kinh phí, không tính xây dựng đề cương chi tiết (từ khi bắt đầu đến khi chương trình được phê duyệt)', '500 giờ/1 chương trình'],
                            '1.3_nguoi_bien_soan_de_cuong_chi_tiet' => ['Xây dựng mới đề cương chi tiết không được chi kinh phí - Người biên soạn', '30 giờ/đề cương'],
                            '1.3_nguoi_nhan_xet_de_cuong_chi_tiet' => ['Xây dựng mới đề cương chi tiết không được chi kinh phí - Người nhận xét', '10 giờ/đề cương'],
                            '1.4_ra_soat_cap_nhat_chuong_trinh_dao_tao' => ['Rà soát, cập nhật chương trình đào tạo theo Quyết định của Trường, không được chi kinh phí, không tính xây dựng đề cương chi tiết (từ khi bắt đầu đến khi chương trình được phê duyệt)', '250 giờ/1 chương trình'],
                            '1.5_ra_soat_cap_nhat_de_cuong_chi_tiet' => ['Rà soát, cập nhật đề cương chi tiết không được chi kinh phí', '15 giờ/đề cương'],
                            '1.6_ra_soat_cap_nhat_de_cuong_chi_tiet_hang_ky' => ['Rà soát, cập nhật đề cương chi tiết hàng kỳ', '5 giờ/đề cương'],
                            '1.8_chu_tri_bao_cao_chuyen_de_truong' => ['Tổ chức buổi báo cáo chuyên đề, học thuật cho Trường - Chủ trì', '30 giờ/lần'],
                            '1.8_thanh_vien_ban_to_chuc_bao_cao_chuyen_de_truong' => ['Tổ chức buổi báo cáo chuyên đề, học thuật cho Trường - Thành viên trong Ban tổ chức', '15 giờ/lần'],
                            '1.9_chu_tri_bao_cao_chuyen_de_khoa' => ['Tổ chức buổi báo cáo chuyên đề, học thuật cho Khoa - Chủ trì', '10 giờ/lần'],
                            '1.9_thanh_vien_ban_to_chuc_bao_cao_chuyen_de_khoa' => ['Tổ chức buổi báo cáo chuyên đề, học thuật cho Khoa - Thành viên trong Ban tổ chức', '05 giờ/lần'],
                            '1.10_viet_bai_bao_cao_hoi_nghi_hoi_thao_trong_nuoc' => ['Viết bài, hoặc báo cáo tại hội nghị, hội thảo, ... về lĩnh vực giáo dục hoặc chuyên ngành (không xuất bản ấn phẩm), dưới danh nghĩa Trường - Bài trong nước', '20 giờ/1 bài'],
                            '1.10_viet_bai_bao_cao_hoi_nghi_hoi_thao_quoc_te' => ['Viết bài, hoặc báo cáo tại hội nghị, hội thảo, ... về lĩnh vực giáo dục hoặc chuyên ngành (không xuất bản ấn phẩm), dưới danh nghĩa Trường - Bài quốc tế', '40 giờ/1 bài'],
                            '1.11_thanh_vien_nhom_nghien_cuu_manh' => ['Thành viên nhóm nghiên cứu mạnh, chuyên sâu, dự án quốc tế/quốc gia/tỉnh/thành không do Trường quyết định nhưng phải mang tên Trường (có hoạt động thực tế được công nhận)', 'Tối đa 50 giờ/năm/đề tài, dự án,...'],
                            '1.12_thanh_vien_ban_thu_ky_hoi_dong_tu_danh_gia' => ['Thành viên Ban thư ký của Hội đồng tự đánh giá chương trình đào tạo (chỉ tính với các thành viên là giảng viên)', '4500 giờ/chương trình'],
                            '1.13_bao_cao_tong_hop_tieu_chuan' => ['Tham gia viết báo cáo đánh giá CTĐT/Trường - Báo cáo tổng hợp tiêu chuẩn', '100 giờ/tiêu chuẩn'],
                            '1.13_bao_cao_tieu_chi' => ['Tham gia viết báo cáo đánh giá CTĐT/Trường - Báo cáo tiêu chí', '50 giờ/tiêu chí'],
                            '1.13_ho_tro_thu_thap_cung_cap_minh_chung' => ['Tham gia viết báo cáo đánh giá CTĐT/Trường - Hỗ trợ thu thập và cung cấp minh chứng', '50 giờ/tiêu chuẩn'],
                            '1.14_ho_tro_cong_tac_doi_ngoai' => ['Hỗ trợ trường, khoa trong công tác đối ngoại (không thuộc chức năng nhiệm vụ của vị trí đương nhiệm), hỗ trợ tư vấn cho các đơn vị khác trong trường,... (có xác nhận của trường)', 'Tối đa 50 giờ làm việc/năm'],
                            '1.15_ho_tro_phat_bang_tot_nghiep' => ['Hỗ trợ phát bằng tốt nghiệp cho sinh viên', '4 giờ/buổi/1 giảng viên'],
                            '1.16_ho_tro_cong_tac_sinh_vien' => ['Hỗ trợ Trường, khoa trong công tác sinh viên, cựu sinh viên và hướng nghiệp, giao lưu ra bên ngoài...', 'Tối đa 50 giờ làm việc/năm'],
                            '1.17_len_lop_tuan_sinh_hoat_cong_dan' => ['Lên lớp tuần sinh hoạt công dân', 'Theo thời khóa biểu tuần sinh hoạt công dân'],
                            '1.18_tham_gia_chi_dao_cau_lac_bo' => ['Tham gia chỉ đạo, cố vấn cho các câu lạc bộ được Nhà trường cho phép thành lập', '5 giờ/1 tháng/1 câu lạc bộ'],
                            '1.19_xet_duyet_de_xuat_nghien_cuu_khoa_hoc_nguoi_hoc' => ['Xét duyệt đề xuất nghiên cứu khoa học của người học ở khoa/bộ môn', '2 giờ/1 đề cương'],
                            '1.20_xet_duyet_de_xuat_nckh_cap_co_so' => ['Xét duyệt đề xuất NCKH cấp cơ sở không sử dụng NSNN của GV ở khoa/bộ môn', '3 giờ/1 đề cương'],
                            '1.21_thanh_vien_hoi_dong_xet_duyet_thuyet_minh' => ['Thành viên Hội đồng xét duyệt thuyết minh, hội đồng nghiệm thu đề tài NCKH cấp cơ sở không sử dụng NSNN', '4 giờ/đề tài'],
                            '1.22_thanh_vien_hoi_dong_xet_duyet_thuyet_minh_sinh_vien' => ['Thành viên Hội đồng xét duyệt thuyết minh; Hội đồng nghiệm thu đề tài NCKH của sinh viên', '2.0 giờ/đề tài/người'],
                            '1.23_tham_du_cuoc_hop_trao_doi_chuyen_mon' => ['Tham dự các cuộc họp, làm việc, trao đổi chuyên môn với khách trong nước và quốc tế tại trường theo phân công của Khoa, Trường', '3.0 giờ/buổi'],
                            '1.24_tham_du_doan_cong_tac_quoc_te' => ['Tham dự các đoàn công tác quốc gia, quốc tế, cuộc hội thảo, làm việc, trao đổi chuyên môn ngoài trường nhưng mang danh nghĩa của trường và không sử dụng ngân sách của trường', 'Theo thực tế'],
                            '1.25_tham_gia_to_chuc_hoi_thao_quoc_te' => ['Tham gia quá trình tổ chức hội thảo quốc tế; quốc gia theo yêu cầu của chủ trường', '100 giờ/người/1 năm'],
                            '1.26_thanh_vien_ban_thu_ky_hoi_thao_tieng_viet' => ['Thành viên Ban thư ký hội thảo khoa học do Trường chủ trì - Bài tiếng Việt', '05 giờ/bài'],
                            '1.26_thanh_vien_ban_thu_ky_hoi_thao_tieng_anh' => ['Thành viên Ban thư ký hội thảo khoa học do Trường chủ trì - Bài tiếng Anh', '10 giờ/bài'],
                            '1.27_gop_y_nhan_xet_bai_ky_yeu_tieng_viet' => ['Góp ý, nhận xét bài đăng trong Kỷ yếu hội thảo khoa học do Trường chủ trì không có kinh phí hỗ trợ - Bài tiếng Việt', '10 giờ/bài'],
                            '1.27_gop_y_nhan_xet_bai_ky_yeu_tieng_anh' => ['Góp ý, nhận xét bài đăng trong Kỷ yếu hội thảo khoa học do Trường chủ trì không có kinh phí hỗ trợ - Bài tiếng Anh', '15 giờ/bài'],
                            '1.28_lam_video_quang_ba_hinh_anh' => ['Làm các video phục vụ quảng bá hình ảnh cho các hoạt động đào tạo hoặc NCKH hoặc HTQT cho Khoa/Nhà trường', '50 giờ/video'],
                            '1.29_xet_duyet_tien_do_luan_van_thac_sy' => ['Xét duyệt tiến độ luận văn thạc sỹ (trước khi xét điều kiện bảo vệ)', '01 giờ/1 luận văn/thành viên'],
                            '1.30_duyet_de_cuong_khoa_luan_tot_nghiep' => ['Duyệt đề cương Khóa luận tốt nghiệp sinh viên', '01 giờ/đề cương (tính cho cả nhóm duyệt)'],
                            '1.31_xet_duyet_tien_do_khoa_luan_tot_nghiep' => ['Xét duyệt tiến độ, khối lượng khóa luận tốt nghiệp trước bảo vệ', '01 giờ/khóa luận (tính cho cả nhóm duyệt)'],
                            '1.32_tham_gia_gop_y_du_thao_van_ban' => ['Tham gia góp ý các dự thảo văn bản của Bộ, Trường, Khoa (khi được yêu cầu)', 'Theo thực tế mức độ của văn bản'],
                            '1.33_ra_soat_ke_hoach_dao_tao' => ['Rà soát kế hoạch đào tạo', '10 giờ/kỳ'],
                            '1.34_to_chuc_hoi_thao_cap_truong' => ['Tổ chức các hội thảo cấp Trường', 'Theo phê duyệt của nhà trường'],
                            '1.35_lien_he_tham_quan_nhan_thuc' => ['Liên hệ cho SV, HV đi tham quan nhận thức tại cơ sở', '4 giờ/đợt'],
                            '1.36_lien_he_thuc_tap_tot_nghiep' => ['Liên hệ cho SV, HV đi thực tập tốt nghiệp tại cơ sở (đối với các sinh viên không tự xin được nơi thực tập và đăng ký để khoa/bộ môn liên hệ)', '4 giờ/cơ sở'],
                            '1.37_lap_bao_cao_khao_sat' => ['Lập báo cáo khảo sát các đối tượng cấp khoa/bộ môn (01 lần/học kỳ)', '40 giờ/học kỳ'],
                            '1.38_lap_ke_hoach_duy_tri_dam_bao_chat_luong' => ['Lập kế hoạch duy trì và nâng cao hoạt động đảm bảo chất lượng của đơn vị (01 lần/học kỳ)', '12 giờ/học kỳ'],
                            '1.39_lap_bao_cao_tong_ket_dam_bao_chat_luong' => ['Lập báo cáo tổng kết việc thực hiện kế hoạch nhằm duy trì và nâng cao hoạt động đảm bảo chất lượng của đơn vị (01 lần/học kỳ)', '16 giờ/học kỳ'],
                            '1.40_quan_ly_kho_vu_khi' => ['Quản lý kho vũ khí, quân trang và dụng cụ thể dục thể thao', '100 giờ/1 năm'],
                            '1.41_ho_tro_quan_ly_phong_thi_nghiem' => ['Tham gia hỗ trợ công tác quản lý phòng thí nghiệm, thực hành công nghệ, phòng máy', 'Theo phê duyệt']
                        ];
                        foreach ($tasks_1 as $id => $details): 
                            $task_id = str_replace('.', '_', $id); // === Thay đổi 4: Chuẩn hóa task_id trong HTML ===
                        ?>
                        <tr class="sub-task" data-group="group-b-1">
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

    // === Thay đổi 5: Cập nhật hàm calculateHours giống NV_Khac_B_2_3 ===
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
    // === Kết thúc Thay đổi 5 ===

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
    </script>
</body>

</html>