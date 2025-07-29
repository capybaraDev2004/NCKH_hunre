<?php
// Kiểm tra đường dẫn tới connection.php
$connectionPath = __DIR__ . '/../../../connection/connection.php';
if (!file_exists($connectionPath)) {
    die("Lỗi: Không tìm thấy file connection.php tại: " . $connectionPath);
}

// Kết nối cơ sở dữ liệu
include_once $connectionPath;

// Kiểm tra xem biến $conn có được tạo không
if (!isset($conn)) {
    die("Lỗi: Không thể kết nối tới cơ sở dữ liệu. Kiểm tra file connection.php.");
}



// Kiểm tra đăng nhập
if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login.php");
    exit();
}

$employeeID = $_SESSION['employeeID'];

// Lấy danh sách các năm từ bảng task_registrations
try {
    $stmt = $conn->prepare("SELECT DISTINCT year FROM task_registrations WHERE employee_id = ? ORDER BY year DESC");
    $stmt->execute([$employeeID]);
    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    echo "Lỗi: " . $e->getMessage();
    exit();
}

// Lấy năm được chọn từ form (nếu có), mặc định là năm đầu tiên trong danh sách
$selectedYear = isset($_POST['year']) ? (int)$_POST['year'] : (!empty($years) ? $years[0] : date('Y'));

// Debug: Hiển thị năm được chọn
error_log("Selected Year: " . $selectedYear);

// Lấy danh sách nhiệm vụ đã đăng ký theo năm được chọn
try {
    $stmt = $conn->prepare("SELECT * FROM task_registrations WHERE employee_id = ? AND year = ? ORDER BY section, task_id");
    $stmt->execute([$employeeID, $selectedYear]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Ghi log số lượng nhiệm vụ trả về
    error_log("Number of tasks for year $selectedYear: " . count($tasks));
    foreach ($tasks as $task) {
        error_log("Task: " . json_encode($task));
    }
} catch (PDOException $e) {
    echo "Lỗi: " . $e->getMessage();
    exit();
}

// Tính lại tổng số giờ từ task_registrations và cập nhật bảng total_hours
$totalHoursCalculated = 0;
foreach ($tasks as $task) {
    $totalHoursCalculated += $task['total_hours'];
}

// Cập nhật bảng total_hours
try {
    $stmt = $conn->prepare("INSERT INTO total_hours (employee_id, year, total_completed_hours) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE total_completed_hours = ?");
    $stmt->execute([$employeeID, $selectedYear, $totalHoursCalculated, $totalHoursCalculated]);
} catch (PDOException $e) {
    echo "Lỗi khi cập nhật total_hours: " . $e->getMessage();
    exit();
}

// Lấy tổng số giờ hoàn thành của năm được chọn
try {
    $stmt = $conn->prepare("SELECT total_completed_hours FROM total_hours WHERE employee_id = ? AND year = ?");
    $stmt->execute([$employeeID, $selectedYear]);
    $totalHours = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    echo "Lỗi: " . $e->getMessage();
    exit();
}

// Hàm để lấy nội dung nhiệm vụ và chỉ số dựa trên task_id và section
function getTaskContent($task_id, $section) {
    $taskContents = [
        // Section a (Hội đồng, công tác đoàn thể)
        'a' => [
            '1_chu_tich_thu_ky_hoi_dong_khoa_hoc_dao_tao' => ['index' => '1', 'content' => 'Chủ tịch, thư ký Hội đồng Khoa học và Đào tạo (tính khoản cho cả năm)'],
            '1_uy_vien_hoi_dong_khoa_hoc_dao_tao' => ['index' => '1', 'content' => 'Ủy viên Hội đồng Khoa học và Đào tạo (tính khoản cho cả năm)'],
            '2_chu_tich_thu_ky_hoi_dong_khoa' => ['index' => '2', 'content' => 'Chủ tịch, thư ký Hội đồng Khoa'],
            '2_uy_vien_hoi_dong_khoa' => ['index' => '2', 'content' => 'Ủy viên Hội đồng Khoa'],
            '3_chu_tich_thu_ky_hoi_dong_dam_bao_chat_luong' => ['index' => '3', 'content' => 'Chủ tịch, thư ký Hội đồng Đảm bảo chất lượng trường'],
            '3_uy_vien_hoi_dong_dam_bao_chat_luong' => ['index' => '3', 'content' => 'Ủy viên Hội đồng Đảm bảo chất lượng trường'],
            '4_chu_tich_pho_chu_tich_hoi_dong_danh_gia_ctdt' => ['index' => '4', 'content' => 'Chủ tịch, Phó Chủ tịch Hội đồng Đánh giá chương trình đào tạo'],
            '4_thu_ky_hoi_dong_danh_gia_ctdt' => ['index' => '4', 'content' => 'Thư ký Hội đồng Đánh giá chương trình đào tạo'],
            '4_uy_vien_hoi_dong_danh_gia_ctdt' => ['index' => '4', 'content' => 'Ủy viên Hội đồng Đánh giá chương trình đào tạo'],
            '5.1_chu_nhiem_uy_ban_kiem_tra_dang_uy_truong' => ['index' => '5.1', 'content' => 'Chủ nhiệm Ủy ban kiểm tra Đảng ủy Trường'],
            '5.1_pho_chu_nhiem_uy_ban_kiem_tra_dang_uy_truong' => ['index' => '5.1', 'content' => 'Phó Chủ nhiệm Ủy ban kiểm tra Đảng ủy Trường'],
            '5.1_uy_vien_uy_ban_kiem_tra_dang_uy_truong' => ['index' => '5.1', 'content' => 'Ủy viên Ủy ban kiểm tra Đảng ủy Trường'],
            '5.2_chi_bo_duoi_20_dang_vien' => ['index' => '5.2', 'content' => 'Chi ủy viên Chi bộ trực thuộc (Chi bộ dưới 20 Đảng viên)'],
            '5.2_chi_bo_20_dang_vien_tro_len' => ['index' => '5.2', 'content' => 'Chi ủy viên Chi bộ trực thuộc (Chi bộ từ 20 Đảng viên trở lên)'],
            '5.2_chi_bo_40_dang_vien_tro_len' => ['index' => '5.2', 'content' => 'Chi ủy viên Chi bộ trực thuộc (Chi bộ từ 40 Đảng viên trở lên)'],
            '5.3_chu_nhiem_uy_ban_kiem_tra_cong_doan_truong' => ['index' => '5.3', 'content' => 'Chủ nhiệm Ủy ban kiểm tra Công đoàn Trường'],
            '5.3_pho_chu_nhiem_uy_ban_kiem_tra_cong_doan_truong' => ['index' => '5.3', 'content' => 'Phó Chủ nhiệm Ủy ban kiểm tra Công đoàn Trường'],
            '5.3_uy_vien_uy_ban_kiem_tra_cong_doan_truong' => ['index' => '5.3', 'content' => 'Ủy viên Ủy ban kiểm tra Công đoàn Trường'],
            '5.4_pho_chu_nhiem_ban_thanh_tra_nhan_dan' => ['index' => '5.4', 'content' => 'Phó Chủ nhiệm Ban Thanh tra Nhân dân'],
            '5.4_uy_vien_ban_thanh_tra_nhan_dan' => ['index' => '5.4', 'content' => 'Ủy viên Ban Thanh tra Nhân dân'],
            '5.5_pho_chu_nhiem_ban_nu_cong_truong' => ['index' => '5.5', 'content' => 'Phó Chủ nhiệm Ban Nữ công Trường'],
            '5.5_uy_vien_ban_nu_cong_truong' => ['index' => '5.5', 'content' => 'Ủy viên Ban Nữ công Trường'],
            '5.6_to_truong_to_nu_cong' => ['index' => '5.6', 'content' => 'Tổ trưởng Tổ Nữ công'],
            '6_chu_tich_cong_doan_truong' => ['index' => '6', 'content' => 'Chủ tịch Công đoàn Trường'],
            '6_pho_chu_tich_cong_doan_truong' => ['index' => '6', 'content' => 'Phó Chủ tịch Công đoàn Trường'],
            '6_uy_vien_cong_doan_truong' => ['index' => '6', 'content' => 'Ủy viên Công đoàn Trường'],
            '7_chu_tich_cong_doan_bo_phan' => ['index' => '7', 'content' => 'Chủ tịch Công đoàn bộ phận'],
            '7_pho_chu_tich_cong_doan_bo_phan' => ['index' => '7', 'content' => 'Phó Chủ tịch Công đoàn bộ phận'],
            '7_uy_vien_cong_doan_bo_phan' => ['index' => '7', 'content' => 'Ủy viên Công đoàn bộ phận'],
            '8_bi_thu_doan_thanh_nien_truong' => ['index' => '8', 'content' => 'Bí thư Ban Chấp hành Đoàn Thanh niên Trường'],
            '8_pho_bi_thu_doan_thanh_nien_truong' => ['index' => '8', 'content' => 'Phó Bí thư Ban Chấp hành Đoàn Thanh niên Trường'],
            '8_uy_vien_bch_doan_thanh_nien_truong' => ['index' => '8', 'content' => 'Ủy viên Ban Chấp hành Đoàn Thanh niên Trường'],
            '9_uy_vien_bch_hoi_cuu_chien_binh_truong' => ['index' => '9', 'content' => 'Ủy viên Ban Chấp hành Hội Cựu chiến binh Trường'],
        ],
        // Section b1 (Mục 1)
        'b1' => [
            '1.1' => ['index' => '1.1', 'content' => 'Hỗ trợ sinh viên trong học tập, nghiên cứu khoa học, rèn luyện'],
            '1.1_ho_tro_giai_dap_sinh_vien' => ['index' => '1.1', 'content' => 'Hỗ trợ giải đáp sinh viên'], // Sửa để khớp với task_id
            '1.2_xay_dung_chuong_trinh_dao_tao' => ['index' => '1.2', 'content' => 'Xây dựng chương trình đào tạo'],
            '1.3' => ['index' => '1.3', 'content' => 'Hỗ trợ sinh viên trong các hoạt động phong trào, đoàn thể'],
            '1.4' => ['index' => '1.4', 'content' => 'Tư vấn học tập, nghề nghiệp cho sinh viên'],
            '1.5' => ['index' => '1.5', 'content' => 'Hỗ trợ sinh viên tham gia các cuộc thi học thuật, sáng tạo'],
            '1.6' => ['index' => '1.6', 'content' => 'Tham gia tổ chức hội thảo, tọa đàm cho sinh viên'],
            '1.7' => ['index' => '1.7', 'content' => 'Tham gia các hoạt động ngoại khóa, tình nguyện cùng sinh viên'],
            '1.8' => ['index' => '1.8', 'content' => 'Hỗ trợ sinh viên khởi nghiệp, đổi mới sáng tạo'],
            '1.9' => ['index' => '1.9', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn học tập cho sinh viên'],
            '1.10' => ['index' => '1.10', 'content' => 'Tham gia xây dựng ngân hàng câu hỏi thi, kiểm tra'],
            '1.11' => ['index' => '1.11', 'content' => 'Tham gia xây dựng tài liệu học tập điện tử'],
            '1.12' => ['index' => '1.12', 'content' => 'Tham gia xây dựng bài giảng E-learning'],
            '1.13' => ['index' => '1.13', 'content' => 'Tham gia xây dựng video bài giảng'],
            '1.14' => ['index' => '1.14', 'content' => 'Tham gia xây dựng tài liệu thực hành, thí nghiệm'],
            '1.15' => ['index' => '1.15', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn thực tập'],
            '1.16' => ['index' => '1.16', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn đồ án, khóa luận'],
            '1.17' => ['index' => '1.17', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn nghiên cứu khoa học'],
            '1.18' => ['index' => '1.18', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn tự học'],
            '1.19' => ['index' => '1.19', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng phần mềm học tập'],
            '1.20' => ['index' => '1.20', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng thiết bị học tập'],
            '1.21' => ['index' => '1.21', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng thư viện điện tử'],
            '1.22' => ['index' => '1.22', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng phòng thí nghiệm'],
            '1.23' => ['index' => '1.23', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng phòng thực hành'],
            '1.24' => ['index' => '1.24', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng phòng máy tính'],
            '1.25' => ['index' => '1.25', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng phòng học đa năng'],
            '1.26' => ['index' => '1.26', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng phòng học trực tuyến'],
            '1.27' => ['index' => '1.27', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống học trực tuyến'],
            '1.28' => ['index' => '1.28', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống thi trực tuyến'],
            '1.29' => ['index' => '1.29', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống quản lý học tập'],
            '1.30' => ['index' => '1.30', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống quản lý sinh viên'],
            '1.31' => ['index' => '1.31', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống quản lý giảng viên'],
            '1.32' => ['index' => '1.32', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống quản lý điểm'],
            '1.33' => ['index' => '1.33', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống quản lý lịch học'],
            '1.34' => ['index' => '1.34', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống quản lý phòng học'],
            '1.35' => ['index' => '1.35', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống quản lý thiết bị'],
            '1.36' => ['index' => '1.36', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống quản lý tài liệu'],
            '1.37' => ['index' => '1.37', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống quản lý tài chính'],
            '1.38' => ['index' => '1.38', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống quản lý nhân sự'],
            '1.39' => ['index' => '1.39', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống quản lý dự án'],
            '1.40' => ['index' => '1.40', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống quản lý sự kiện'],
            '1.41' => ['index' => '1.41', 'content' => 'Tham gia xây dựng tài liệu hướng dẫn sử dụng hệ thống quản lý hội thảo'],
        ],
        // Section b2_3 (Mục 2 và 3)
        'b2_3' => [
            '2.1_dang_tin_khoa' => ['index' => '2.1', 'content' => 'Đăng tin cấp khoa trên các trang thông tin của trường, khoa'],
            '2.1_dang_tin_truong' => ['index' => '2.1', 'content' => 'Đăng tin cấp trường trên các trang thông tin của trường, khoa'],
            '2.2_quan_tri_web_fanpage_muc_1' => ['index' => '2.2', 'content' => 'Quản trị web, fanpage khoa - Mức 1 (tốt)'],
            '2.2_quan_tri_web_fanpage_muc_2' => ['index' => '2.2', 'content' => 'Quản trị web, fanpage khoa - Mức 2 (khá)'],
            '2.2_quan_tri_web_fanpage_muc_3' => ['index' => '2.2', 'content' => 'Quản trị web, fanpage khoa - Mức 3 (trung bình)'],
            '2.3_truyen_thong_quang_ba' => ['index' => '2.3', 'content' => 'Tham gia hoạt động truyền thông, quảng bá hình ảnh của Khoa, Trường'],
            '2.4_tuyen_sinh_nhap_hoc' => ['index' => '2.4', 'content' => 'Tham gia công tác tuyển sinh, nhập học của trường'],
            '2.5_ho_tro_quang_ba_tuyen_sinh' => ['index' => '2.5', 'content' => 'Hỗ trợ công tác quảng bá tuyển sinh của trường'],
            '3.1_chung_chi_chuyen_mon' => ['index' => '3.1', 'content' => 'Chứng chỉ/chứng nhận chuyên môn, nghiệp vụ phục vụ trực tiếp'],
            '3.2_chung_chi_nang_cao_chat_luong' => ['index' => '3.2', 'content' => 'Chứng chỉ hỗ trợ nâng cao chất lượng và hiệu quả công việc'],
            '3.3_hoi_thao_trong_nuoc' => ['index' => '3.3', 'content' => 'Tham dự hội thảo chuyên ngành trong nước'],
            '3.3_hoi_thao_quoc_te' => ['index' => '3.3', 'content' => 'Tham dự hội thảo chuyên ngành quốc tế'],
            '3.4_sinh_hoat_khoa' => ['index' => '3.4', 'content' => 'Sinh hoạt chuyên môn cấp khoa'],
            '3.4_sinh_hoat_truong' => ['index' => '3.4', 'content' => 'Sinh hoạt chuyên môn cấp trường'],
            '3.5_seminar_nguoi_trinh_bay' => ['index' => '3.5', 'content' => 'Thực hiện seminar chuyên môn - Người trình bày'],
            '3.5_seminar_nguoi_tham_du' => ['index' => '3.5', 'content' => 'Tham gia seminar chuyên môn - Người tham dự'],
            '3.6_sinh_hoat_chuyen_mon_bo_mon' => ['index' => '3.6', 'content' => 'Tham gia sinh hoạt chuyên môn bộ môn'],
            '3.7_du_gio_chuyen_mon' => ['index' => '3.7', 'content' => 'Dự giờ chuyên môn'],
            '3.8_tap_huan_ly_luan_chinh_tri' => ['index' => '3.8', 'content' => 'Tham gia tập huấn, bồi dưỡng giảng viên Lý luận chính trị'],
            '3.9_hop_chi_bo' => ['index' => '3.9', 'content' => 'Họp chi bộ'],
            '3.10_hop_cong_doan' => ['index' => '3.10', 'content' => 'Họp công đoàn'],
            '3.11_hop_doan_thanh_nien' => ['index' => '3.11', 'content' => 'Họp Đoàn thanh niên'],
            '3.12_hop_trao_doi_chuyen_mon' => ['index' => '3.12', 'content' => 'Tham dự các cuộc họp, trao đổi chuyên môn với khách'],
            '3.13_cong_tac_ngoai_truong' => ['index' => '3.13', 'content' => 'Tham dự đoàn công tác quốc gia, quốc tế ngoài trường'],
            '3.14_nghien_cuu_sinh' => ['index' => '3.14', 'content' => 'Giảng viên làm nghiên cứu sinh trong nước'],
            '3.15_tro_giang_cao_hoc' => ['index' => '3.15', 'content' => 'Trợ giảng đi học cao học đúng chuyên ngành'],
        ],
        // Section b456 (Mục 4, 5, 6)
        'b456' => [
            '4.1' => ['index' => '4.1', 'content' => 'Tham gia các hoạt động văn hóa, văn nghệ, thể dục thể thao của trường'],
            '4.2' => ['index' => '4.2', 'content' => 'Tham gia các hoạt động văn hóa, văn nghệ, thể dục thể thao của khoa'],
            '4.3' => ['index' => '4.3', 'content' => 'Tham gia các sự kiện chung của trường (khai giảng, bế giảng, kỷ niệm thành lập trường, ngày nhà giáo Việt Nam,...)'],
            '4.4' => ['index' => '4.4', 'content' => 'Tham gia các sự kiện chung của khoa'],
            '4.5' => ['index' => '4.5', 'content' => 'Tham gia tổ chức các sự kiện văn hóa, văn nghệ, thể dục thể thao của trường'],
            '4.6' => ['index' => '4.6', 'content' => 'Tham gia tổ chức các sự kiện văn hóa, văn nghệ, thể dục thể thao của khoa'],
            '5.1' => ['index' => '5.1', 'content' => 'Hướng dẫn viên chức tập sự'],
            '5.2' => ['index' => '5.2', 'content' => 'Hỗ trợ viên chức tập sự trong công việc'],
            '6.1' => ['index' => '6.1', 'content' => 'Tham gia các công việc khác theo phân công của trường'],
            '6.2' => ['index' => '6.2', 'content' => 'Tham gia các công việc khác theo phân công của khoa'],
            '6.3' => ['index' => '6.3', 'content' => 'Các công việc đột xuất khác'],
        ],
    ];

    if (isset($taskContents[$section][$task_id])) {
        return $taskContents[$section][$task_id];
    }
    // Nếu không tìm thấy task_id, trả về chỉ số hợp lý hơn
    $index = preg_match('/^\d+\.\d+/', $task_id, $matches) ? $matches[0] : $task_id;
    return ['index' => $index, 'content' => 'Nhiệm vụ không xác định (task_id: ' . $task_id . ')'];
}

// Hàm để lấy thư mục minh chứng dựa trên section
function getEvidenceFolder($section) {
    if ($section === 'a') {
        return 'NV_Khac_A';
    } else {
        return 'NV_Khac_B';
    }
}

// Hàm kiểm tra xem file có phải là hình ảnh không
function isImageFile($filePath) {
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    return in_array($extension, $imageExtensions);
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tổng hợp nhiệm vụ khác</title>
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        border: 1px solid #223771;
    }

    th,
    td {
        border: 1px solid #223771;
        padding: 8px;
        text-align: center;
    }

    th {
        background-color: #223771;
        color: #ffffff;
        font-weight: bold;
    }

    tr {
        background-color: #E6F0FA;
    }

    .total-hours {
        font-weight: bold;
        margin-top: 20px;
        font-size: 1.2em;
    }

    .evidence-link {
        color: blue;
        text-decoration: underline;
    }

    .section-title {
        background-color: #f0f4ff;
        font-weight: bold;
        text-align: left;
        padding: 10px;
        font-style: italic;
    }

    /* CSS cho form chọn năm */
    form {
        margin: 20px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    label[for="year"] {
        font-weight: 600;
        color: #223771;
        font-size: 16px;
    }

    select#year {
        padding: 8px 15px;
        border: 2px solid #223771;
        border-radius: 5px;
        font-size: 15px;
        color: #223771;
        background-color: #fff;
        cursor: pointer;
        outline: none;
        transition: all 0.3s ease;
        min-width: 120px;
    }

    select#year:hover {
        border-color: #3857A3;
        box-shadow: 0 0 5px rgba(34, 55, 113, 0.2);
    }

    select#year:focus {
        border-color: #3857A3;
        box-shadow: 0 0 8px rgba(34, 55, 113, 0.3);
    }

    /* Tùy chỉnh arrow dropdown */
    select#year {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23223771' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 16px;
        padding-right: 40px;
    }
    </style>
</head>

<body>
    <h1 class="form-title">Tổng hợp nhiệm vụ khác</h1>

    <!-- Form chọn năm -->
    <form method="POST" action="">
        <label for="year">Năm học được điều chỉnh:</label>
        <select name="year" id="year" onchange="this.form.submit()">
            <?php foreach ($years as $year): ?>
            <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>>
                <?php echo $year; ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- Hiển thị tổng số giờ hoàn thành -->
    <div class="total-hours">
        Tổng số giờ hoàn thành năm <?php echo $selectedYear; ?>: <?php echo number_format($totalHours, 2); ?> giờ
    </div>

    <!-- Bảng hiển thị danh sách nhiệm vụ -->
    <table class="teaching-registration-table">
        <thead>
            <tr>
                <th>STT</th>
                <th>Chỉ số</th>
                <th>Nội dung nhiệm vụ</th>
                <th>Số lượng</th>
                <th>Số giờ</th>
                <th>Minh chứng</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Phân loại nhiệm vụ theo section
            $sections = [
                'a' => 'A. Tham gia các hội đồng, công tác đoàn thể',
               'b1' => 'B.1. Tham gia các hoạt động phục vụ công tác đào tạo, khoa học, phục vụ người học và cộng đồng',
                'b2_3' => 'B.2 & B.3. Hoạt động phục vụ tuyển sinh, truyền thông, học tập, hội họp',
                'b456' => 'B.4, B.5, B.6. Hoạt động văn thể mỹ, hướng dẫn tập sự, công việc khác'
            ];

            $stt = 1;

            foreach ($sections as $section => $sectionTitle) {
                $sectionTasks = array_filter($tasks, function($task) use ($section) {
                    return $task['section'] === $section;
                });

                if (!empty($sectionTasks)) {
                    echo '<tr><td colspan="6" class="section-title">' . htmlspecialchars($sectionTitle) . '</td></tr>';
                    foreach ($sectionTasks as $task) {
                        $taskInfo = getTaskContent($task['task_id'], $task['section']);
                        $evidenceFolder = getEvidenceFolder($task['section']);
                        $evidenceFile = $task['evidence_path'];
                        ?>
            <tr>
                <td><?php echo $stt++; ?></td>
                <td><?php echo htmlspecialchars($taskInfo['index']); ?></td>
                <td style="text-align: left;"><?php echo htmlspecialchars($taskInfo['content']); ?></td>
                <td><?php echo htmlspecialchars($task['quantity']); ?></td>
                <td><?php echo number_format($task['total_hours'], 2); ?></td>
                <td>
                    <?php if (!empty($evidenceFile)): ?>
                    <?php
                            $evidenceFullPath = __DIR__ . "/../uploads/{$evidenceFolder}/{$evidenceFile}";
                            $evidencePath = "NV_Khac/download.php?folder=" . urlencode($evidenceFolder) . "&file=" . urlencode($evidenceFile);
                            $isImage = isImageFile($evidenceFile);
                            // Debug: Ghi log đường dẫn file minh chứng
                            error_log("Evidence Path: $evidenceFullPath, Exists: " . (file_exists($evidenceFullPath) ? 'Yes' : 'No'));
                            ?>
                    <?php if (file_exists($evidenceFullPath)): ?>
                    <a href="<?php echo $evidencePath; ?>" class="evidence-link"
                        <?php echo $isImage ? 'target="_blank"' : ''; ?>>
                        Xem minh chứng
                    </a>
                    <?php else: ?>
                    Không có (File không tồn tại)
                    <?php endif; ?>
                    <?php else: ?>
                    Không có
                    <?php endif; ?>
                </td>
            </tr>
            <?php
                    }
                }
            }

            // Nếu không có nhiệm vụ nào
            if (empty($tasks)) {
                echo '<tr><td colspan="6" style="text-align: center;">Không có nhiệm vụ nào trong năm ' . $selectedYear . '.</td></tr>';
            }
            ?>
        </tbody>
    </table>
</body>

</html>