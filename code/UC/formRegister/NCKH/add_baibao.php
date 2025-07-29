<?php
session_start();
require_once __DIR__ . '/../../../connection/connection.php';

header('Content-Type: application/json');

// Hàm gửi phản hồi JSON
function sendResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// Kiểm tra phương thức và session
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['employeeID'])) {
    sendResponse(false, 'Yêu cầu không hợp lệ');
}

// Nhận dữ liệu JSON từ client
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendResponse(false, 'Dữ liệu JSON không hợp lệ');
}

// Lấy thông tin từ dữ liệu JSON
$employeeID = $_SESSION['employeeID'];
$type = $data['type'] ?? '';
$id = filter_var($data['id'], FILTER_VALIDATE_INT);
$noiDung = htmlspecialchars($data['noi_dung']);
$tenSanPham = htmlspecialchars($data['ten_san_pham']);
$soLuong = floatval($data['so_luong']);
$soTacGia = intval($data['so_tac_gia']);
$selectedYear = filter_var($data['selected_year_form'], FILTER_VALIDATE_INT);
$nationPoint = isset($data['nation_point']) ? htmlspecialchars($data['nation_point']) : '';
$diemTapChi = isset($data['diem_tap_chi']) ? floatval($data['diem_tap_chi']) : null;
$maSoXuatBan = isset($data['ma_so_xuat_ban']) ? htmlspecialchars($data['ma_so_xuat_ban']) : null;
$tenDonViXuatBan = isset($data['ten_don_vi_xuat_ban']) ? htmlspecialchars($data['ten_don_vi_xuat_ban']) : null;
$tenHoiThao = isset($data['ten_hoi_thao']) ? htmlspecialchars($data['ten_hoi_thao']) : null;
$authors = $data['authors'] ?? [];

// Kiểm tra dữ liệu đầu vào
if ($id === false || $id === null || $selectedYear === false || $selectedYear === null || !in_array($type, ['vietsach', 'nckhcc', 'huongdansv', 'vietbaibao'])) {
    sendResponse(false, 'ID hoặc năm không hợp lệ');
}

if ($soLuong < 0 || $soTacGia < 1 || empty($authors)) {
    sendResponse(false, 'Dữ liệu đầu vào không hợp lệ');
}

// Kiểm tra quyền cập nhật: người lưu phải có trong danh sách tác giả
$thanhVienIds = array_column($authors, 'thanh_vien');
if (!in_array($employeeID, $thanhVienIds)) {
    sendResponse(false, 'Bạn không được phép cập nhật kết quả của người dùng khác!');
}

// Kiểm tra trùng mã giảng viên
if (count($thanhVienIds) !== count(array_unique($thanhVienIds))) {
    sendResponse(false, 'Có thành viên bị trùng mã giảng viên!');
}

try {
    // Bắt đầu transaction
    $conn->beginTransaction();

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

    // Kiểm tra số bản ghi
    if ($soBanGhiHienTai >= $soTacGia) {
        $conn->rollBack();
        sendResponse(false, 'Đã có bản ghi về đề tài, bạn vui lòng kiểm tra lại!');
    }
    if ($soBanGhiHienTai + count($authors) > $soTacGia) {
        $conn->rollBack();
        sendResponse(false, 'Đã có bản ghi về đề tài, số lượng tác giả vượt quá giới hạn!');
    }

    // Lấy thông tin giảng viên
    $stmtName = $conn->prepare("SELECT teacherID, fullName FROM employee WHERE employeeID = :employeeID");
    $stmtName->execute([':employeeID' => $employeeID]);
    $row = $stmtName->fetch(PDO::FETCH_ASSOC);

    $thanhVienChuNhom = $row ? ($row['teacherID'] . ' - ' . $row['fullName']) : ($employeeID . ' - ');

    // Xóa các bản ghi cũ của nhóm này nếu có
    $deleteStmt = $conn->prepare("
        DELETE FROM $historyTable 
        WHERE nckh_id = :nckh_id 
        AND result_year = :result_year 
        AND ten_san_pham = :ten_san_pham
    ");
    $deleteStmt->execute([
        ':nckh_id' => $id,
        ':result_year' => $selectedYear,
        ':ten_san_pham' => $tenSanPham
    ]);

    // Lưu dữ liệu cho từng thành viên
    foreach ($authors as $author) {
        $tacGiaID = $author['thanh_vien'];
        $vaiTroTacGia = htmlspecialchars($author['role_thanh_vien']);
        $phanTramTacGia = floatval($author['phan_tram_dong_gop']);
        $note = htmlspecialchars($author['note']);

        if ($phanTramTacGia < 0 || $phanTramTacGia > 100) {
            $conn->rollBack();
            sendResponse(false, 'Phần trăm đóng góp không hợp lệ cho thành viên ' . $tacGiaID);
        }

        // Tính giờ quy đổi
        $tongGio = $khoiLuongGio * $soLuong;
        $gioQuyDoi = ($phanTramTacGia == 0) ? ($soTacGia > 0 ? $tongGio / $soTacGia : 0) : ($tongGio * $phanTramTacGia) / 100;

        $sql = "
            INSERT INTO $historyTable (
                employeeID, result_year, nckh_id, noi_dung, ten_san_pham, so_luong, vai_tro, so_tac_gia, phan_tram_dong_gop, gio_quy_doi, nation_point, note,
                diem_tap_chi, ma_so_xuat_ban, ten_don_vi_xuat_ban, ten_hoi_thao, thanh_vien_chu_nhom
            )
            VALUES (
                :employeeID, :result_year, :id, :noi_dung, :ten_san_pham, :so_luong, :vai_tro, :so_tac_gia, :phan_tram_dong_gop, :gio_quy_doi, :nation_point, :note,
                :diem_tap_chi, :ma_so_xuat_ban, :ten_don_vi_xuat_ban, :ten_hoi_thao, :thanh_vien_chu_nhom
            )
        ";
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
            ':note' => $note,
            ':diem_tap_chi' => $diemTapChi,
            ':ma_so_xuat_ban' => $maSoXuatBan,
            ':ten_don_vi_xuat_ban' => $tenDonViXuatBan,
            ':ten_hoi_thao' => $tenHoiThao,
            ':thanh_vien_chu_nhom' => $thanhVienChuNhom,
        ]);
    }

    // Commit transaction
    $conn->commit();
    sendResponse(true, 'Lưu thành công!');
} catch (PDOException $e) {
    // Rollback nếu có lỗi
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    sendResponse(false, 'Lỗi cơ sở dữ liệu: ' . $e->getMessage());
}
?>