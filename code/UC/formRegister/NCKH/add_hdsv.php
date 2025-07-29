<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Tắt hiển thị lỗi
ini_set('log_errors', 1); // Bật ghi log lỗi
ini_set('error_log', __DIR__ . '/error.log'); // Đặt file log

session_start();
require_once __DIR__ . '/../../../connection/connection.php';

// Đảm bảo không có output nào trước header
ob_start();
header('Content-Type: application/json');

// Debug
error_log("POST data: " . print_r($_POST, true));

// Xử lý lỗi
function handleError($message) {
    ob_clean(); // Xóa bất kỳ output nào
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['employeeID'])) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ']);
    exit;
}

$employeeID = $_SESSION['employeeID'];
$type = $_POST['type'] ?? ''; // Loại dữ liệu: 'vietsach', 'nckhcc', 'huongdansv', hoặc 'vietbaibao'
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT); // nckh_id, sach_id, huong_dan_id, hoặc bai_bao_id
$noiDung = htmlspecialchars($_POST['noi_dung']);
$tenSanPham = htmlspecialchars($_POST['ten_san_pham']);
$soLuong = floatval($_POST['so_luong']);
$vaiTro = isset($_POST['vai_tro']) ? htmlspecialchars($_POST['vai_tro']) : null; // vai_tro có thể không tồn tại
$soTacGia = intval($_POST['so_tac_gia']);
$phanTramDongGop = floatval($_POST['phan_tram_dong_gop']);
$selectedYear = filter_input(INPUT_POST, 'selected_year_form', FILTER_VALIDATE_INT);
$nationPoint = isset($_POST['nation_point']) ? htmlspecialchars($_POST['nation_point']) : '';
$studentInfor = isset($_POST['student_infor']) ? htmlspecialchars($_POST['student_infor']) : '';

$thanhVienArr = isset($_POST['thanh_vien']) ? $_POST['thanh_vien'] : [];
$roleThanhVienArr = isset($_POST['role_thanh_vien']) ? $_POST['role_thanh_vien'] : [];
$phanTramDongGopArr = isset($_POST['phan_tram_dong_gop']) ? $_POST['phan_tram_dong_gop'] : [];

$diemTapChi = isset($_POST['diem_tap_chi']) ? floatval($_POST['diem_tap_chi']) : null;
$maSoXuatBan = isset($_POST['ma_so_xuat_ban']) ? htmlspecialchars($_POST['ma_so_xuat_ban']) : null;
$tenDonViXuatBan = isset($_POST['ten_don_vi_xuat_ban']) ? htmlspecialchars($_POST['ten_don_vi_xuat_ban']) : null;
$tenHoiThao = isset($_POST['ten_hoi_thao']) ? htmlspecialchars($_POST['ten_hoi_thao']) : null;

// Debug
error_log("Type: " . $type);
error_log("ID: " . $id);
error_log("Selected Year: " . $selectedYear);

if ($id === false || $id === null || $selectedYear === false || $selectedYear === null || !in_array($type, ['vietsach', 'nckhcc', 'huongdansv', 'vietbaibao'])) {
    echo json_encode(['success' => false, 'message' => 'ID hoặc năm không hợp lệ']);
    exit;
}

// Kiểm tra dữ liệu đầu vào
if ($soLuong < 0 || $soTacGia < 1 || $soTacGia > 2 || $phanTramDongGop < 0 || $phanTramDongGop > 100) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu đầu vào không hợp lệ']);
    exit;
}

// Kiểm tra quyền cập nhật: người lưu phải có trong danh sách tác giả
if (!in_array($employeeID, $thanhVienArr)) {
    echo json_encode(['success' => false, 'message' => 'Bạn không được phép cập nhật kết quả của người dùng khác!']);
    exit;
}

try {
    // Xác định bảng và truy vấn dựa trên type
    $table = $type === 'vietsach' ? 'vietsach' : ($type === 'nckhcc' ? 'nghiencuukhoahoccaccap' : ($type === 'huongdansv' ? 'huongdansvnckh' : 'vietbaibao'));
    $historyTable = $type === 'vietsach' ? 'vietsach_history' : ($type === 'nckhcc' ? 'nckhcc_history' : ($type === 'huongdansv' ? 'huongdansv_history' : 'bai_bao_history'));

    // Lấy khối lượng giờ
    $stmt = $conn->prepare("SELECT KhoiLuongGio FROM $table WHERE ID = :id");
    $stmt->execute([':id' => $id]);
    $khoiLuongGio = $stmt->fetchColumn() ?: 0;

    // Đếm số bản ghi đã có cho đề tài này
    $checkStmt = $conn->prepare("
        SELECT COUNT(*) FROM $historyTable
        WHERE nckh_id = :nckh_id AND result_year = :result_year AND ten_san_pham = :ten_san_pham
    ");
    $checkStmt->execute([
        ':nckh_id' => $id,
        ':result_year' => $selectedYear,
        ':ten_san_pham' => $tenSanPham
    ]);
    $soBanGhiHienTai = $checkStmt->fetchColumn();

    // Nếu đã đủ số tác giả, tuyệt đối không cho lưu thêm
    if ($soBanGhiHienTai >= $soTacGia) {
        echo json_encode(['success' => false, 'message' => 'Đã có bản ghi về đề tài, bạn vui lòng kiểm tra lại!']);
        exit;
    }

    // Nếu số bản ghi sắp lưu sẽ vượt quá số tác giả, cũng không cho lưu
    if ($soBanGhiHienTai + count($thanhVienArr) > $soTacGia) {
        echo json_encode(['success' => false, 'message' => 'Đã có bản ghi về đề tài, bạn vui lòng kiểm tra lại!']);
        exit;
    }

    $numMembers = count($thanhVienArr);

    // Kiểm tra trùng mã giảng viên trong danh sách thành viên
    if (count($thanhVienArr) !== count(array_unique($thanhVienArr))) {
        echo json_encode(['success' => false, 'message' => 'Có thành viên bị trùng mã giảng viên!']);
        exit;
    }

    // Lấy teacherID và fullName từ bảng employee dựa vào employeeID session
    $stmtName = $conn->prepare("SELECT teacherID, fullName FROM employee WHERE employeeID = :employeeID");
    $stmtName->execute([':employeeID' => $employeeID]);
    $row = $stmtName->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $teacherID = $row['teacherID'];
        $hoTenChuNhom = $row['fullName'];
        $thanhVienChuNhom = $teacherID . ' - ' . $hoTenChuNhom;
    } else {
        $thanhVienChuNhom = $employeeID . ' - ';
    }

    // Lưu cho tất cả thành viên trong nhóm
    for ($i = 0; $i < count($thanhVienArr); $i++) {
        $tacGiaID = $thanhVienArr[$i];
        $vaiTroTacGia = $roleThanhVienArr[$i];
        $phanTramTacGia = floatval($phanTramDongGopArr[$i]);

        // Tính giờ quy đổi cho từng tác giả
        $tongGio = $khoiLuongGio * $soLuong;
        $gioQuyDoi = ($phanTramTacGia == 0) ? ($soTacGia > 0 ? $tongGio / $soTacGia : 0) : ($tongGio * $phanTramTacGia) / 100;

        $sql = "
            INSERT INTO $historyTable (
                employeeID, result_year, nckh_id, noi_dung, ten_san_pham, so_luong, vai_tro, so_tac_gia, 
                phan_tram_dong_gop, gio_quy_doi, nation_point, student_infor,
                diem_tap_chi, ma_so_xuat_ban, ten_don_vi_xuat_ban, ten_hoi_thao, thanh_vien_chu_nhom
            )
            VALUES (
                :employeeID, :result_year, :id, :noi_dung, :ten_san_pham, :so_luong, :vai_tro, :so_tac_gia, 
                :phan_tram_dong_gop, :gio_quy_doi, :nation_point, :student_infor,
                :diem_tap_chi, :ma_so_xuat_ban, :ten_don_vi_xuat_ban, :ten_hoi_thao, :thanh_vien_chu_nhom
            )
            ON DUPLICATE KEY UPDATE 
                noi_dung = VALUES(noi_dung), 
                ten_san_pham = VALUES(ten_san_pham), 
                so_luong = VALUES(so_luong),
                vai_tro = VALUES(vai_tro), 
                so_tac_gia = VALUES(so_tac_gia), 
                phan_tram_dong_gop = VALUES(phan_tram_dong_gop),
                gio_quy_doi = VALUES(gio_quy_doi), 
                nation_point = VALUES(nation_point), 
                student_infor = VALUES(student_infor),
                diem_tap_chi = VALUES(diem_tap_chi), 
                ma_so_xuat_ban = VALUES(ma_so_xuat_ban),
                ten_don_vi_xuat_ban = VALUES(ten_don_vi_xuat_ban), 
                ten_hoi_thao = VALUES(ten_hoi_thao),
                thanh_vien_chu_nhom = VALUES(thanh_vien_chu_nhom),
                ngay_cap_nhat = NOW()
        ";

        // Thêm debug để kiểm tra giá trị student_infor
        error_log("Student Info before insert: " . $studentInfor);

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':employeeID' => $tacGiaID,
            ':result_year' => $selectedYear,
            ':id' => $id,
            ':noi_dung' => $noiDung,
            ':ten_san_pham' => $tenSanPham,
            ':so_luong' => $soLuong,
            ':vai_tro' => $vaiTroTacGia,
            ':so_tac_gia' => $soTacGia,
            ':phan_tram_dong_gop' => $phanTramTacGia,
            ':gio_quy_doi' => $gioQuyDoi,
            ':nation_point' => $nationPoint,
            ':student_infor' => $studentInfor,
            ':diem_tap_chi' => $diemTapChi,
            ':ma_so_xuat_ban' => $maSoXuatBan,
            ':ten_don_vi_xuat_ban' => $tenDonViXuatBan,
            ':ten_hoi_thao' => $tenHoiThao,
            ':thanh_vien_chu_nhom' => $thanhVienChuNhom,
        ]);
    }

    // Đảm bảo không có output nào khác
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Lưu thành công!'
    ]);
} catch (Exception $e) {
    handleError('Có lỗi xảy ra: ' . $e->getMessage());
}
exit;
?>