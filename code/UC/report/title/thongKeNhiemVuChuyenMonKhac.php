<?php
session_start();

// Khởi tạo session nếu chưa có
if (!isset($_SESSION['pl4_data'])) {
    $_SESSION['pl4_data'] = [];
}

// Xử lý thêm dữ liệu
if (isset($_POST['add_pl4'])) {
    $row = [
        'Định mức của chức danh' => $_POST['pl4_dinh_muc'] ?? '',
        'Mã số' => $_POST['pl4_ma_so'] ?? '',
        'Tên nhiệm vụ' => $_POST['pl4_ten_nhiem_vu'] ?? '',
        'Tỷ lệ tham gia' => $_POST['pl4_ty_le_tham_gia'] ?? '',
        'Giờ quy đổi' => $_POST['pl4_gio_quy_doi'] ?? '',
        'Giờ làm NV3' => $_POST['pl4_gio_lam_nv3'] ?? '',
        'Minh chứng' => $_POST['pl4_minh_chung'] ?? ''
    ];
    $_SESSION['pl4_data'][] = $row;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống Kê Nhiệm Vụ Chuyên Môn Khác</title>
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
    </style>
</head>
<body>
    <h2>THỐNG KÊ NHIỆM VỤ CHUYÊN MÔN KHÁC NĂM 2023</h2>
    <a href="../report.php" class="back-link">Quay lại trang báo cáo</a>

    <!-- Form nhập liệu -->
    <div class="form-section">
        <h3>PHỤ LỤC 4: THỐNG KÊ NHIỆM VỤ CHUYÊN MÔN KHÁC</h3>
        <form action="" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="pl4_dinh_muc">Định mức của chức danh:</label>
                    <select id="pl4_dinh_muc" name="pl4_dinh_muc">
                        <option value="360">Giảng viên (hạng III) = 360</option>
                        <option value="270">Giảng viên chính (hạng II) = 270</option>
                        <option value="180">Giảng viên cao cấp (hạng I) = 180</option>
                        <option value="1355">Giảng viên tập sự, trợ giảng = 1355</option>
                        <option value="677.5">Trợ giảng tập sự = 677.5</option>
                        <option value="270">Giảng viên Bộ môn GDTC&GDQP = 270</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="pl4_ma_so">Mã số:</label>
                    <input type="text" id="pl4_ma_so" name="pl4_ma_so">
                </div>
                <div class="form-group">
                    <label for="pl4_ten_nhiem_vu">Tên nhiệm vụ:</label>
                    <input type="text" id="pl4_ten_nhiem_vu" name="pl4_ten_nhiem_vu">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="pl4_ty_le_tham_gia">Tỷ lệ tham gia (%):</label>
                    <input type="number" id="pl4_ty_le_tham_gia" name="pl4_ty_le_tham_gia">
                </div>
                <div class="form-group">
                    <label for="pl4_gio_quy_doi">Giờ quy đổi:</label>
                    <input type="number" step="0.01" id="pl4_gio_quy_doi" name="pl4_gio_quy_doi">
                </div>
                <div class="form-group">
                    <label for="pl4_gio_lam_nv3">Giờ làm NV3:</label>
                    <input type="number" step="0.01" id="pl4_gio_lam_nv3" name="pl4_gio_lam_nv3">
                </div>
                <div class="form-group">
                    <label for="pl4_minh_chung">Minh chứng:</label>
                    <input type="text" id="pl4_minh_chung" name="pl4_minh_chung">
                </div>
            </div>
            <button type="submit" name="add_pl4">Thêm Dữ Liệu</button>
        </form>

        <!-- Hiển thị dữ liệu -->
        <table>
            <thead>
                <tr>
                    <th>Định mức của chức danh</th>
                    <th>Mã số</th>
                    <th>Tên nhiệm vụ</th>
                    <th>Tỷ lệ tham gia</th>
                    <th>Giờ quy đổi</th>
                    <th>Giờ làm NV3</th>
                    <th>Minh chứng</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_SESSION['pl4_data'] as $row): ?>
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