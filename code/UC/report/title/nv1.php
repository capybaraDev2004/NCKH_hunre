<?php
session_start();

// Khởi tạo session nếu chưa có
if (!isset($_SESSION['table1'])) {
    $_SESSION['table1'] = [];
}
if (!isset($_SESSION['table2'])) {
    $_SESSION['table2'] = [];
}

// Xử lý thêm dữ liệu cho Bảng 1
if (isset($_POST['add_table1'])) {
    $row = [
        'STT' => $_POST['t1_stt'] ?? '',
        'Giảng viên' => $_POST['t1_giang_vien'] ?? '',
        'Học Phần' => $_POST['t1_hoc_phan'] ?? '',
        'Lớp' => $_POST['t1_lop'] ?? '',
        'Số TC' => $_POST['t1_so_tc'] ?? '',
        'Sỹ số' => $_POST['t1_sy_so'] ?? '',
        'Hình thức học (trực tiếp/trực tuyến)' => $_POST['t1_hinh_thuc_hoc'] ?? '',
        'Hình thức học (LT/TH)' => $_POST['t1_hinh_thuc_lt_th'] ?? '',
        'Số tiết (Lớp đông)' => $_POST['t1_so_tiet_lop_dong'] ?? '',
        'Số tiết (Ngoài giờ)' => $_POST['t1_so_tiet_ngoai_gio'] ?? '',
        'Số tiết (Giảng dạy bằng tiếng anh)' => $_POST['t1_so_tiet_tieng_anh'] ?? '',
        'Số tiết (Cao học)' => $_POST['t1_so_tiet_cao_hoc'] ?? '',
        'Số tiết (Thực hành)' => $_POST['t1_so_tiet_thuc_hanh'] ?? '',
        'Quy đổi giờ chuẩn' => $_POST['t1_quy_doi_gio_chuan'] ?? '',
        'Tổng' => $_POST['t1_tong'] ?? '',
        'KHOA/BỘ MÔN' => $_POST['t1_khoa_bo_mon'] ?? '',
        'Ghi chú' => $_POST['t1_ghi_chu'] ?? '',
        'NĂM HỌC' => $_POST['t1_nam_hoc'] ?? ''
    ];
    $_SESSION['table1'][] = $row;
}

// Xử lý thêm dữ liệu cho Bảng 2
if (isset($_POST['add_table2'])) {
    $row = [
        'STT' => $_POST['t2_stt'] ?? '',
        'Giảng viên' => $_POST['t2_giang_vien'] ?? '',
        'Xây dựng NH câu hỏi thi' => $_POST['t2_xay_dung_nhch'] ?? '',
        'HD Thẩm định xây NHCHT' => $_POST['t2_tham_dinh_nhch'] ?? '',
        'Ra đề' => $_POST['t2_ra_de'] ?? '',
        'Phản biện đề' => $_POST['t2_phan_bien_de'] ?? '',
        'Chấm thi kết thúc học phần' => $_POST['t2_cham_thi'] ?? '',
        'Chấm báo cáo khóa luận tốt nghiệp' => $_POST['t2_cham_bao_cao'] ?? '',
        'Coi thi' => $_POST['t2_coi_thi'] ?? '',
        'Số SV (Hướng dẫn khóa luận TN)' => $_POST['t2_so_sv_khoa_luan'] ?? '',
        'Quy đổi ra tiền (Hướng dẫn khóa luận TN)' => $_POST['t2_quy_doi_khoa_luan'] ?? '',
        'Số HV (Hướng dẫn đề án TN)' => $_POST['t2_so_hv_de_an'] ?? '',
        'Quy đổi ra tiền (Hướng dẫn đề án TN)' => $_POST['t2_quy_doi_de_an'] ?? '',
        'Số QĐ (Hội đồng thẩm CTĐT)' => $_POST['t2_so_qd_hoi_dong'] ?? '',
        'Chủ tịch HĐ' => $_POST['t2_chu_tich_hd'] ?? '',
        'Thư ký HĐ' => $_POST['t2_thu_ky_hd'] ?? '',
        'Phản biện' => $_POST['t2_phan_bien_hd'] ?? '',
        'Ủy viên' => $_POST['t2_uy_vien_hd'] ?? '',
        'Quy đổi ra tiền (Hội đồng thẩm CTĐT)' => $_POST['t2_quy_doi_hd'] ?? '',
        'Số TC (Xây dựng bài giảng điện tử)' => $_POST['t2_so_tc_bai_giang'] ?? '',
        'Mức độ 1' => $_POST['t2_muc_do_1'] ?? '',
        'Mức độ 2' => $_POST['t2_muc_do_2'] ?? '',
        'Mức độ 3' => $_POST['t2_muc_do_3'] ?? '',
        'Mức độ 4' => $_POST['t2_muc_do_4'] ?? '',
        'Quy đổi ra tiền (Xây dựng bài giảng điện tử)' => $_POST['t2_quy_doi_bai_giang'] ?? '',
        'Số QĐ (Xây dựng bài giảng điện tử)' => $_POST['t2_so_qd_bai_giang'] ?? '',
        'Tỷ lệ đào tạo trực tuyến của học phần (%)' => $_POST['t2_ty_le_dao_tao_truc_tuyen'] ?? '',
        'Tỷ lệ mức độ đóng góp tham gia xây dựng BGĐT (%)' => $_POST['t2_ty_le_dong_gop'] ?? '',
        'KHOA/BỘ MÔN' => $_POST['t2_khoa_bo_mon'] ?? '',
        'Môn học' => $_POST['t2_mon_hoc'] ?? '',
        'Tình trạng' => $_POST['t2_tinh_trang'] ?? ''
    ];
    $_SESSION['table2'][] = $row;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhập Dữ Liệu Giảng Dạy</title>
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link rel="stylesheet" href="../../../assets/css/index.css">
    <style>
    body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
    h2, h3 { text-align: center; color: #223771; }
    .form-section { margin-bottom: 30px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 24px rgba(34, 55, 113, 0.1); }
    .form-group { margin-bottom: 15px; }
    label { display: block; margin-bottom: 5px; font-weight: 600; color: #223771; }
    input, select { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #d1d5db; font-size: 15px; background: #f8fafc; color: #223771; outline: none; }
    input:focus, select:focus { border: 1.5px solid #223771; }
    .form-row { display: flex; gap: 10px; flex-wrap: wrap; }
    .form-row .form-group { flex: 1; min-width: 200px; }
    button { background-color: #223771; color: white; padding: 12px; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; }
    button:hover { background-color: #f8843d; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; border: 1px solid #000000; }
    th, td { border: 1px solid #000000; padding: 8px; text-align: center; font-size: 12px; }
    th { background-color: #f2f2f2; font-weight: 600; }
    .back-link { display: inline-block; margin-top: 20px; color: #223771; text-decoration: none; font-weight: 600; }
    .back-link:hover { color: #f8843d; }
    .group-header { background-color: #e6f4ea; font-weight: bold; }
    </style>
</head>
<body>
    <h2>NHẬP DỮ LIỆU TỔNG HỢP KHỐI LƯỢNG GIẢNG DẠY</h2>
    <a href="../report.php" class="back-link">Quay lại trang báo cáo</a>

    <!-- Form Bảng 1 -->
    <div class="form-section">
        <h3>I. BẢNG 1: TỔNG HỢP KHỐI LƯỢNG GIỜ ĐỨNG LỚP TRỰC TIẾP</h3>
        <form action="" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="t1_stt">STT:</label>
                    <input type="number" id="t1_stt" name="t1_stt">
                </div>
                <div class="form-group">
                    <label for="t1_giang_vien">Giảng viên:</label>
                    <input type="text" id="t1_giang_vien" name="t1_giang_vien">
                </div>
                <div class="form-group">
                    <label for="t1_hoc_phan">Học Phần:</label>
                    <input type="text" id="t1_hoc_phan" name="t1_hoc_phan">
                </div>
                <div class="form-group">
                    <label for="t1_lop">Lớp:</label>
                    <input type="text" id="t1_lop" name="t1_lop">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="t1_so_tc">Số TC:</label>
                    <input type="number" id="t1_so_tc" name="t1_so_tc">
                </div>
                <div class="form-group">
                    <label for="t1_sy_so">Sỹ số:</label>
                    <input type="number" id="t1_sy_so" name="t1_sy_so">
                </div>
                <div class="form-group">
                    <label for="t1_hinh_thuc_hoc">Hình thức học (trực tiếp/trực tuyến):</label>
                    <select id="t1_hinh_thuc_hoc" name="t1_hinh_thuc_hoc">
                        <option value="Trực tiếp">Trực tiếp</option>
                        <option value="Trực tuyến">Trực tuyến</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="t1_hinh_thuc_lt_th">Hình thức học (LT/TH):</label>
                    <select id="t1_hinh_thuc_lt_th" name="t1_hinh_thuc_lt_th">
                        <option value="LT">LT</option>
                        <option value="TH">TH</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="t1_so_tiet_lop_dong">Số tiết (Lớp đông):</label>
                    <input type="number" id="t1_so_tiet_lop_dong" name="t1_so_tiet_lop_dong">
                </div>
                <div class="form-group">
                    <label for="t1_so_tiet_ngoai_gio">Số tiết (Ngoài giờ):</label>
                    <input type="number" id="t1_so_tiet_ngoai_gio" name="t1_so_tiet_ngoai_gio">
                </div>
                <div class="form-group">
                    <label for="t1_so_tiet_tieng_anh">Số tiết (Giảng dạy bằng tiếng anh):</label>
                    <input type="number" id="t1_so_tiet_tieng_anh" name="t1_so_tiet_tieng_anh">
                </div>
                <div class="form-group">
                    <label for="t1_so_tiet_cao_hoc">Số tiết (Cao học):</label>
                    <input type="number" id="t1_so_tiet_cao_hoc" name="t1_so_tiet_cao_hoc">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="t1_so_tiet_thuc_hanh">Số tiết (Thực hành):</label>
                    <input type="number" id="t1_so_tiet_thuc_hanh" name="t1_so_tiet_thuc_hanh">
                </div>
                <div class="form-group">
                    <label for="t1_quy_doi_gio_chuan">Quy đổi giờ chuẩn:</label>
                    <input type="number" id="t1_quy_doi_gio_chuan" name="t1_quy_doi_gio_chuan">
                </div>
                <div class="form-group">
                    <label for="t1_tong">Tổng:</label>
                    <input type="number" id="t1_tong" name="t1_tong">
                </div>
                <div class="form-group">
                    <label for="t1_khoa_bo_mon">KHOA/BỘ MÔN:</label>
                    <input type="text" id="t1_khoa_bo_mon" name="t1_khoa_bo_mon">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="t1_ghi_chu">Ghi chú:</label>
                    <input type="text" id="t1_ghi_chu" name="t1_ghi_chu">
                </div>
                <div class="form-group">
                    <label for="t1_nam_hoc">NĂM HỌC:</label>
                    <input type="text" id="t1_nam_hoc" name="t1_nam_hoc" value="2025">
                </div>
            </div>
            <button type="submit" name="add_table1">Thêm Dữ Liệu Bảng 1</button>
        </form>

        <!-- Hiển thị dữ liệu Bảng 1 -->
        <table>
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Giảng viên</th>
                    <th>Học Phần</th>
                    <th>Lớp</th>
                    <th>Số TC</th>
                    <th>Sỹ số</th>
                    <th>Hình thức học (trực tiếp/trực tuyến)</th>
                    <th>Hình thức học (LT/TH)</th>
                    <th>Số tiết (Lớp đông)</th>
                    <th>Số tiết (Ngoài giờ)</th>
                    <th>Số tiết (Giảng dạy bằng tiếng anh)</th>
                    <th>Số tiết (Cao học)</th>
                    <th>Số tiết (Thực hành)</th>
                    <th>Quy đổi giờ chuẩn</th>
                    <th>Tổng</th>
                    <th>KHOA/BỘ MÔN</th>
                    <th>Ghi chú</th>
                    <th>NĂM HỌC</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_SESSION['table1'] as $row): ?>
                    <tr>
                        <?php foreach ($row as $value): ?>
                            <td><?php echo htmlspecialchars($value); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Form Bảng 2 -->
    <div class="form-section">
        <h3>II. BẢNG 2: TỔNG HỢP KHỐI LƯỢNG GIỜ QUY ĐỔI TỪ CÔNG TÁC KHÁC</h3>
        <form action="" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="t2_stt">STT:</label>
                    <input type="number" id="t2_stt" name="t2_stt">
                </div>
                <div class="form-group">
                    <label for="t2_giang_vien">Giảng viên:</label>
                    <input type="text" id="t2_giang_vien" name="t2_giang_vien">
                </div>
            </div>

            <!-- Dữ liệu tổng hợp của phòng KT&ĐBCLGD -->
            <h4>Dữ liệu tổng hợp của phòng KT&ĐBCLGD</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="t2_xay_dung_nhch">Xây dựng NH câu hỏi thi:</label>
                    <input type="number" id="t2_xay_dung_nhch" name="t2_xay_dung_nhch">
                </div>
                <div class="form-group">
                    <label for="t2_tham_dinh_nhch">HD Thẩm định xây NHCHT:</label>
                    <input type="number" id="t2_tham_dinh_nhch" name="t2_tham_dinh_nhch">
                </div>
                <div class="form-group">
                    <label for="t2_ra_de">Ra đề:</label>
                    <input type="number" id="t2_ra_de" name="t2_ra_de">
                </div>
                <div class="form-group">
                    <label for="t2_phan_bien_de">Phản biện đề:</label>
                    <input type="number" id="t2_phan_bien_de" name="t2_phan_bien_de">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="t2_cham_thi">Chấm thi kết thúc học phần:</label>
                    <input type="number" id="t2_cham_thi" name="t2_cham_thi">
                </div>
                <div class="form-group">
                    <label for="t2_cham_bao_cao">Chấm báo cáo khóa luận tốt nghiệp:</label>
                    <input type="number" id="t2_cham_bao_cao" name="t2_cham_bao_cao">
                </div>
            </div>

            <!-- Coi thi -->
            <h4>Coi thi</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="t2_coi_thi">Coi thi:</label>
                    <input type="number" id="t2_coi_thi" name="t2_coi_thi">
                </div>
            </div>

            <!-- Hướng dẫn khóa luận TN -->
            <h4>Hướng dẫn khóa luận TN</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="t2_so_sv_khoa_luan">Số SV:</label>
                    <input type="number" id="t2_so_sv_khoa_luan" name="t2_so_sv_khoa_luan">
                </div>
                <div class="form-group">
                    <label for="t2_quy_doi_khoa_luan">Quy đổi ra tiền:</label>
                    <input type="number" id="t2_quy_doi_khoa_luan" name="t2_quy_doi_khoa_luan">
                </div>
            </div>

            <!-- Hướng dẫn đề án TN -->
            <h4>Hướng dẫn đề án TN</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="t2_so_hv_de_an">Số HV:</label>
                    <input type="number" id="t2_so_hv_de_an" name="t2_so_hv_de_an">
                </div>
                <div class="form-group">
                    <label for="t2_quy_doi_de_an">Quy đổi ra tiền:</label>
                    <input type="number" id="t2_quy_doi_de_an" name="t2_quy_doi_de_an">
                </div>
            </div>

            <!-- Hội đồng thẩm CTĐT -->
            <h4>Hội đồng thẩm CTĐT</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="t2_so_qd_hoi_dong">Số QĐ:</label>
                    <input type="number" id="t2_so_qd_hoi_dong" name="t2_so_qd_hoi_dong">
                </div>
                <div class="form-group">
                    <label for="t2_chu_tich_hd">Chủ tịch HĐ:</label>
                    <input type="number" id="t2_chu_tich_hd" name="t2_chu_tich_hd">
                </div>
                <div class="form-group">
                    <label for="t2_thu_ky_hd">Thư ký HĐ:</label>
                    <input type="number" id="t2_thu_ky_hd" name="t2_thu_ky_hd">
                </div>
                <div class="form-group">
                    <label for="t2_phan_bien_hd">Phản biện:</label>
                    <input type="number" id="t2_phan_bien_hd" name="t2_phan_bien_hd">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="t2_uy_vien_hd">Ủy viên:</label>
                    <input type="number" id="t2_uy_vien_hd" name="t2_uy_vien_hd">
                </div>
                <div class="form-group">
                    <label for="t2_quy_doi_hd">Quy đổi ra tiền:</label>
                    <input type="number" id="t2_quy_doi_hd" name="t2_quy_doi_hd">
                </div>
            </div>

            <!-- Xây dựng bài giảng điện tử -->
            <h4>Xây dựng bài giảng điện tử</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="t2_so_tc_bai_giang">Số TC:</label>
                    <input type="number" id="t2_so_tc_bai_giang" name="t2_so_tc_bai_giang">
                </div>
                <div class="form-group">
                    <label for="t2_muc_do_1">Mức độ 1:</label>
                    <input type="number" id="t2_muc_do_1" name="t2_muc_do_1">
                </div>
                <div class="form-group">
                    <label for="t2_muc_do_2">Mức độ 2:</label>
                    <input type="number" id="t2_muc_do_2" name="t2_muc_do_2">
                </div>
                <div class="form-group">
                    <label for="t2_muc_do_3">Mức độ 3:</label>
                    <input type="number" id="t2_muc_do_3" name="t2_muc_do_3">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="t2_muc_do_4">Mức độ 4:</label>
                    <input type="number" id="t2_muc_do_4" name="t2_muc_do_4">
                </div>
                <div class="form-group">
                    <label for="t2_quy_doi_bai_giang">Quy đổi ra tiền:</label>
                    <input type="number" id="t2_quy_doi_bai_giang" name="t2_quy_doi_bai_giang">
                </div>
                <div class="form-group">
                    <label for="t2_so_qd_bai_giang">Số QĐ:</label>
                    <input type="number" id="t2_so_qd_bai_giang" name="t2_so_qd_bai_giang">
                </div>
                <div class="form-group">
                    <label for="t2_ty_le_dao_tao_truc_tuyen">Tỷ lệ đào tạo trực tuyến của học phần (%):</label>
                    <input type="number" id="t2_ty_le_dao_tao_truc_tuyen" name="t2_ty_le_dao_tao_truc_tuyen">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="t2_ty_le_dong_gop">Tỷ lệ mức độ đóng góp tham gia xây dựng BGĐT (%):</label>
                    <input type="number" id="t2_ty_le_dong_gop" name="t2_ty_le_dong_gop">
                </div>
            </div>

            <!-- Thông tin bổ sung -->
            <h4>Thông tin bổ sung</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="t2_khoa_bo_mon">KHOA/BỘ MÔN:</label>
                    <input type="text" id="t2_khoa_bo_mon" name="t2_khoa_bo_mon">
                </div>
                <div class="form-group">
                    <label for="t2_mon_hoc">Môn học:</label>
                    <input type="text" id="t2_mon_hoc" name="t2_mon_hoc">
                </div>
                <div class="form-group">
                    <label for="t2_tinh_trang">Tình trạng:</label>
                    <input type="text" id="t2_tinh_trang" name="t2_tinh_trang">
                </div>
            </div>

            <button type="submit" name="add_table2">Thêm Dữ Liệu Bảng 2</button>
        </form>

        <!-- Hiển thị dữ liệu Bảng 2 -->
        <table>
            <thead>
                <tr>
                    <th rowspan="2">STT</th>
                    <th rowspan="2">Giảng viên</th>
                    <th colspan="6" class="group-header">Dữ liệu tổng hợp của phòng KT&ĐBCLGD</th>
                    <th rowspan="2">Coi thi</th>
                    <th colspan="2" class="group-header">Hướng dẫn khóa luận TN</th>
                    <th colspan="2" class="group-header">Hướng dẫn đề án TN</th>
                    <th colspan="6" class="group-header">Hội đồng thẩm CTĐT</th>
                    <th colspan="9" class="group-header">Xây dựng bài giảng điện tử</th>
                    <th rowspan="2">KHOA/BỘ MÔN</th>
                    <th rowspan="2">Môn học</th>
                    <th rowspan="2">Tình trạng</th>
                </tr>
                <tr>
                    <th>Xây dựng NH câu hỏi thi</th>
                    <th>HD Thẩm định xây NHCHT</th>
                    <th>Ra đề</th>
                    <th>Phản biện đề</th>
                    <th>Chấm thi kết thúc học phần</th>
                    <th>Chấm báo cáo khóa luận tốt nghiệp</th>
                    <th>Số SV</th>
                    <th>Quy đổi ra tiền</th>
                    <th>Số HV</th>
                    <th>Quy đổi ra tiền</th>
                    <th>Số QĐ</th>
                    <th>Chủ tịch HĐ</th>
                    <th>Thư ký HĐ</th>
                    <th>Phản biện</th>
                    <th>Ủy viên</th>
                    <th>Quy đổi ra tiền</th>
                    <th>Số TC</th>
                    <th>Mức độ 1</th>
                    <th>Mức độ 2</th>
                    <th>Mức độ 3</th>
                    <th>Mức độ 4</th>
                    <th>Quy đổi ra tiền</th>
                    <th>Số QĐ</th>
                    <th>Tỷ lệ đào tạo trực tuyến của học phần (%)</th>
                    <th>Tỷ lệ mức độ đóng góp tham gia xây dựng BGĐT (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_SESSION['table2'] as $row): ?>
                    <tr>
                        <?php foreach ($row as $value): ?>
                            <td><?php echo htmlspecialchars($value); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>