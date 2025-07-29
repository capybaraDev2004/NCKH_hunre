<?php
// Kiểm tra session
if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login/login.php");
    exit();
}
require_once __DIR__ . '/../../../connection/connection.php';

$employeeID = $_SESSION['employeeID'];
// Lấy năm từ dropdown, mặc định là năm hiện tại
$selectedYear = isset($_POST['selected_year']) ? $_POST['selected_year'] : date('Y');

// Lấy thông tin giảng viên
$stmt = $conn->prepare("SELECT academicTitle, leadershipPosition, rankTeacher FROM employee WHERE employeeID = :employeeID");
$stmt->execute([':employeeID' => $employeeID]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// Kiểm tra nếu không tìm thấy giảng viên
if ($employee === false) {
    die("Không tìm thấy thông tin giảng viên.");
}

// Xác định định mức tối thiểu
$dinhMuc = ($employee['academicTitle'] === 'Giảng viên (tập sự)') ? 0 : 590;
if (!empty($employee['leadershipPosition'])) {
    $dinhMuc = 590;
}

// Lấy danh sách hướng dẫn sinh viên NCKH
$stmt = $conn->prepare("SELECT ID, NoiDung, KhoiLuongGio FROM huongdansvnckh");
$stmt->execute();
$huongDanList = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; // Nếu không có dữ liệu, trả về mảng rỗng

// Lấy danh sách giảng viên
$stmtGV = $conn->prepare("SELECT employeeID, teacherID, fullName FROM employee WHERE role = 'Giảng viên'");
$stmtGV->execute();
$giangVienList = $stmtGV->fetchAll(PDO::FETCH_ASSOC);

// Xử lý tìm kiếm
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$filteredHuongDanList = $huongDanList;
if ($searchQuery) {
    $filteredHuongDanList = array_filter($huongDanList, function($item) use ($searchQuery) {
        return stripos($item['NoiDung'], $searchQuery) !== false;
    });
}
?>

<h2 class="form-title">Hướng dẫn sinh viên nghiên cứu khoa học</h2>

<!-- Năm lưu dữ liệu -->
<div style="text-align: center; margin-bottom: 15px;">
    <label for="selected_year">Năm lưu dữ liệu: </label>
    <form id="yearForm" method="POST">
        <select name="selected_year" id="selectedYear" class="year-select" onchange="this.form.submit()">
            <?php
            $currentYear = date('Y');
            for ($i = $currentYear - 5; $i <= $currentYear; $i++) {
                echo "<option value='$i'" . ($selectedYear == $i ? " selected" : "") . ">$i</option>";
            }
            ?>
        </select>
    </form>
</div>

<!-- Form tìm kiếm -->
<div class="search-container">
    <label for="search">Tìm kiếm: </label>
        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Nhập nội dung hướng dẫn..." onkeyup="searchTable()" class="search-input">
    </div>
</div>

<!-- Bảng danh sách hướng dẫn -->
<form method="POST" id="huongDanForm">
    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;" id="huongDanTable">
        <thead>
            <tr style="background-color: #223771; color: white;">
                <th style="padding: 10px; width: 5%;">STT</th>
                <th style="padding: 10px; width: 70%;">Nội dung hướng dẫn</th>
                <th style="padding: 10px; width: 15%;">Khối lượng giờ</th>
                <th style="padding: 10px; width: 10%;">Đăng ký</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filteredHuongDanList as $huongDan): ?>
                <tr data-huong-dan-id="<?php echo $huongDan['ID']; ?>">
                    <td style="padding: 10px; text-align: center;"><?php echo htmlspecialchars($huongDan['ID']); ?></td>
                    <td style="padding: 10px; text-align: left;"><?php echo htmlspecialchars($huongDan['NoiDung']); ?></td>
                    <td style="padding: 10px; text-align: center;" class="khoi-luong-gio" data-huong-dan-id="<?php echo $huongDan['ID']; ?>">
                        <?php echo htmlspecialchars($huongDan['KhoiLuongGio']); ?>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <button type="button" name="selected_huong_dan_id" value="<?php echo $huongDan['ID']; ?>" class="register-btn">Đăng ký</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</form>

<!-- Lịch sử nộp dữ liệu -->
<h3 style="margin-top: 30px;">Lịch sử nộp dữ liệu (Năm <?php echo $selectedYear; ?>)</h3>
<table style="width: 100%; border-collapse: collapse; margin-top: 10px;" id="historyTable">
    <thead>
        <tr style="background-color: #223771; color: white;">
            <th style="padding: 10px; width: 5%;">STT</th>
            <th style="padding: 10px; width: 20%;">Nội dung hướng dẫn</th>
            <th style="padding: 10px; width: 20%;">Tên đề tài</th>
            <th style="padding: 10px; width: 7%;">Số lượng</th>
            <th style="padding: 10px; width: 10%;">Vai trò</th>
            <th style="padding: 10px; width: 7%;">Số giảng viên hướng dẫn</th>
            <th style="padding: 10px; width: 10%;">Phần trăm đóng góp</th>
            <th style="padding: 10px; width: 10%;">Giờ quy đổi</th>
            <th style="padding: 10px; width: 15%;">Ngày cập nhật</th>
            <th style="padding: 10px; width: 10%;">Hành động</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $historyStmt = $conn->prepare("
            SELECT vh.*, v.KhoiLuongGio 
            FROM huongdansv_history vh
            LEFT JOIN huongdansvnckh v ON vh.nckh_id = v.ID
            WHERE vh.employeeID = :employeeID AND vh.result_year = :result_year
        ");
        $historyStmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
        $historyData = $historyStmt->fetchAll(PDO::FETCH_ASSOC) ?: []; // Nếu không có dữ liệu, trả về mảng rỗng

        if (!empty($historyData)) {
            $stt = 1;
            foreach ($historyData as $row) {
                $tongGio = $row['KhoiLuongGio'] * $row['so_luong'];
                $gioQuyDoi = $row['gio_quy_doi'];
                $errorMessage = '';

                if ($row['so_luong'] < 0) {
                    $errorMessage = 'Số lượng không hợp lệ';
                    $gioQuyDoi = 0;
                } elseif ($row['so_tac_gia'] < 1 && $row['so_tac_gia'] > 2) {
                    $errorMessage = 'Số giảng viên hướng dẫn không hợp lệ';
                    $gioQuyDoi = 0;
                } elseif ($row['phan_tram_dong_gop'] < 0 || $row['phan_tram_dong_gop'] > 100) {
                    $errorMessage = 'Phần trăm không hợp lệ';
                    $gioQuyDoi = 0;
                }

                echo "<tr data-huong-dan-id='{$row['nckh_id']}' data-id='{$row['id']}'>
                    <td style='padding: 10px; text-align: center;'>{$stt}</td>
                    <td style='padding: 10px; text-align: left;'>{$row['noi_dung']}</td>
                    <td style='padding: 10px; text-align: left;'>{$row['ten_san_pham']}</td>
                    <td style='padding: 10px; text-align: center;'>{$row['so_luong']}</td>
                    <td style='padding: 10px; text-align: center;'>{$row['vai_tro']}</td>
                    <td style='padding: 10px; text-align: center;'>{$row['so_tac_gia']}</td>
                    <td style='padding: 10px; text-align: center;'>{$row['phan_tram_dong_gop']}%</td>
                    <td style='padding: 10px; text-align: center;" . ($errorMessage ? " color: red;" : "") . "'>
                        " . number_format($gioQuyDoi, 2) . " " . ($errorMessage ? "<br><small>($errorMessage)</small>" : "") . "
                    </td>
                    <td style='padding: 10px; text-align: center;'>{$row['ngay_cap_nhat']}</td>
                    <td style='padding: 10px; text-align: center;'>
                        <button class='delete-btn' data-id='{$row['id']}'>Xóa</button>
                    </td>
                </tr>";
                $stt++;
            }
        } else {
            echo "<tr><td colspan='10' style='padding: 10px; text-align: center; color: #888;'>Chưa có dữ liệu nộp cho năm $selectedYear.</td></tr>";
        }
        ?>
    </tbody>
</table>

<style>
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { padding: 10px; text-align: center; border: 1px solid #ddd; }
    th { background-color: #223771; color: white; }
    .form-input { font-size: 14px; padding: 8px; width: 100%; box-sizing: border-box; }
    .form-actions { text-align: center; margin-top: 15px; }
    .submit-btn, .reset-btn, .register-btn, .delete-btn { font-size: 14px; padding: 8px 15px; margin: 0 5px; cursor: pointer; border: none; border-radius: 4px; }
    .register-btn, .submit-btn { background-color: #223771; color: white; }
    .register-btn:hover, .submit-btn:hover { background-color: #f8843d; }
    .reset-btn { background-color: #666; color: white; }
    .reset-btn:hover { background-color: #999; }
    .delete-btn { background-color: #d9534f; color: white; }
    .delete-btn:hover { background-color: #c9302c; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; }
    .dynamic-form { padding: 15px; background: #f9f9f9; border: 1px solid #ddd; display: none; }
    .dynamic-form h3 { margin-top: 0; text-align: center; }
    .error-message { color: red; display: none; margin-top: 5px; }
    .guide>a{color: #f8843d }
    /* New styles for year selection and search */
    .year-selection-container {
        background: #f5f5f5;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .year-selection-container label {
        font-weight: bold;
        color: #223771;
        margin-right: 10px;
    }

    .year-select {
        padding: 8px 12px;
        border: 2px solid #223771;
        border-radius: 6px;
        background-color: white;
        color: #223771;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .year-select:hover {
        border-color: #f8843d;
    }

    .year-select:focus {
        outline: none;
        border-color: #f8843d;
        box-shadow: 0 0 0 2px rgba(248, 132, 61, 0.2);
    }
    .search-container {
        background: #f5f5f5;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .search-container label {
        font-weight: bold;
        color: #223771;
        margin-right: 10px;
    }

    .search-input {
        padding: 8px 12px;
        border: 2px solid #223771;
        border-radius: 6px;
        width: 300px;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .search-input:focus {
        outline: none;
        border-color: #f8843d;
        box-shadow: 0 0 0 2px rgba(248, 132, 61, 0.2);
    }

    .search-input::placeholder {
        color: #999;
    }

    .form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 10px;
        align-items: center;
    }
    .form-group.small-width {
        width: 220px;
        min-width: 150px;
        max-width: 250px;
    }
    .form-group.large-width {
        width: 450px;
        min-width: 300px;
        max-width: 500px;
    }
    .form-group.xs-width {
        width: 80px;
        min-width: 60px;
        max-width: 100px;
    }
    .form-group.tacgia-width {
        width: 100px;
        min-width: 100px;
        max-width: 120px;
    }
    .select-container {
        position: relative;
        width: 270px;
    }
    .select-container input[type="text"] {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }
    .select-container select {
        width: calc(100% - 2px);
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        max-height: 200px;
        position: absolute;
        top: 38px;
        left: 0;
        display: none;
        background: white;
        z-index: 10;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    .select-container select option {
        padding: 5px;
    }
    .select-container select:focus {
        outline: none;
    }
    .role_thanh_vien {
        border: 1px solid #ccc;
        height: 36px;
        border-radius: 4px;
        width: 120px;
        padding: 0 8px;
        font-size: 14px;
        box-sizing: border-box;
        cursor: pointer;
    }
    .xac-nhan-btn {
        background: #223771;
        color: #fff;
        border-radius: 4px;
        padding: 8px 15px;
        border: none;
        cursor: pointer;
        font-size: 14px;
        transition: background 0.2s;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: -50px;

    }
    .xac-nhan-btn:hover {
        background: #f8843d;
    }
    .form-row.author-count-row {
        display: flex;
        align-items: flex-end;
        gap: 16px;
        margin-bottom: 10px;
    }
    .form-row.author-count-row .form-group {
        margin-bottom: 0;
    }
    .form-row.author-input-row {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 8px;
    }
    .form-row.author-input-row .form-group {
        margin-bottom: 0;
    }
    .author-input-row label {
        display: none;
    }
    .form-row#dynamic-authors-row {
        align-items: flex-start;
        justify-content: flex-start;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        margin-bottom: 4px;
        font-weight: 500;
        text-align: left;
    }
    .form-input.role-width {
        width: 180px;
        min-width: 100px;
        max-width: 210px;
        border: 1px solid #ccc;
        height: 36px;
        border-radius: 4px;
        padding: 0 8px;
        font-size: 14px;
        box-sizing: border-box;
        cursor: pointer;
    }
    .author-input-row input[name="phan_tram_dong_gop[]"] {
        width: 80px;
        min-width: 60px;
        max-width: 100px;
        height: 36px;
        padding: 8px;
        box-sizing: border-box;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
    }
</style>

<script>
function searchTable() {
    const searchValue = document.getElementById('search').value.toLowerCase();
    const rows = document.querySelectorAll('#huongDanTable tbody tr');
    rows.forEach(row => {
        const noiDung = row.cells[1].textContent.toLowerCase();
        row.style.display = noiDung.includes(searchValue) ? '' : 'none';
    });
}

// Xử lý hiển thị form dưới dòng được chọn
document.querySelectorAll('.register-btn').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();

        const existingForm = document.querySelector('.dynamic-form');
        if (existingForm) existingForm.remove();

        const huongDanId = this.value;
        const row = this.closest('tr');
        const noiDung = row.cells[1].textContent;
        const khoiLuongGio = parseFloat(row.cells[2].textContent);
        const selectedYear = document.getElementById('selectedYear').value;

        const formRow = document.createElement('tr');
        formRow.className = 'dynamic-form';
        formRow.innerHTML = `
            <td colspan="4">
                <h3>Đăng ký hướng dẫn: ${noiDung} (Năm ${selectedYear})</h3>
                <form id="saveHuongDanForm" class="styled-form">
                    <!-- Hàng 1: Tên đề tài, Số lượng, Thông tin nhóm SV -->
                    <div class="form-row">
                        <div class="form-group small-width">
                            <label for="ten_san_pham">Tên đề tài:</label>
                            <input type="text" name="ten_san_pham" id="ten_san_pham" class="form-input" required>
                        </div>
                        <div class="form-group xs-width">
                            <label for="so_luong">Số lượng:</label>
                            <input type="number" name="so_luong" id="so_luong" class="form-input" min="0" step="0.1" required>
                            <span class="error-message" id="so_luong_error_top">Số lượng phải lớn hơn hoặc bằng 0</span>
                        </div>
                        <div class="form-group large-width">
                            <label for="student_infor">Thông tin nhóm SV:</label>
                            <input type="text" name="student_infor" id="student_infor" class="form-input" placeholder="Tên trưởng nhóm + lớp của sinh viên" required>
                        </div>
                    </div>

                    <!-- Hàng Số giảng viên hướng dẫn, Xác nhận VÀ khu vực sinh các dòng nhập giảng viên hướng dẫn -->
                    <div class="form-row" id="dynamic-authors-row" style="justify-content: flex-start; align-items: flex-start;">
                        <div style="display: flex; flex-direction: row; align-items: flex-end; gap: 16px; margin-right: 24px;">
                            <div class="form-group xs-width">
                                <label for="so_tac_gia" style="width: 500px;">Số giảng viên hướng dẫn:</label>
                                <input type="number" name="so_tac_gia" id="so_tac_gia" class="form-input" min="1" max="2" required>
                                <span class="error-message" id="so_tac_gia_error">Số giảng viên hướng dẫn phải là 1 hoặc 2</span>
                            </div>
                             <div class="form-group" style="margin-bottom: 0;">
                                <button type="button" class="xac-nhan-btn" style="height: 36px;">Xác nhận</button>
                            </div>
                        </div>
                        <div id="authors-fields" style="display: flex; flex-direction: column; flex: 1 1 0; gap: 8px;"></div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="submit-btn">Lưu</button>
                        <button type="button" class="reset-btn" onclick="this.closest('.dynamic-form').remove()">Hủy</button>
                    </div>
                </form>
            </td>
        `;

        row.insertAdjacentElement('afterend', formRow);
        formRow.style.display = 'table-row';

        const soLuongInput = formRow.querySelector('#so_luong');
        const soTacGiaInput = formRow.querySelector('#so_tac_gia');
        const authorsFields = formRow.querySelector('#authors-fields');

        const GIANG_VIEN_LIST = <?php echo json_encode($giangVienList); ?>;

        const xacNhanBtn = formRow.querySelector('.xac-nhan-btn');
        xacNhanBtn.addEventListener('click', function() {
            const soTacGia = parseInt(soTacGiaInput.value);
            if (soTacGia < 1 || soTacGia > 2) {
                alert('Số giảng viên hướng dẫn phải là 1 hoặc 2');
                return;
            }
            authorsFields.innerHTML = '';
            for (let i = 0; i < soTacGia; i++) {
                const authorRow = document.createElement('div');
                authorRow.className = 'form-row author-input-row';
                authorRow.innerHTML = `
                    <div class="select-container">
                        <input type="text" id="search_thanh_vien_${i}" placeholder="Tìm giảng viên..." onkeyup="filterOptions('thanh_vien_${i}')" onclick="showOptions('thanh_vien_${i}')">
                        <select id="options_thanh_vien_${i}" name="thanh_vien[]" size="5" onchange="selectOption('thanh_vien_${i}')" required>
                            <option value="">-- Chọn giảng viên --</option>
                            ${GIANG_VIEN_LIST.map(gv => `<option value="${gv.employeeID}">${gv.fullName} (${gv.teacherID})</option>`).join('')}
                        </select>
                    </div>
                    <input type="number" name="phan_tram_dong_gop[]" class="form-input xs-width" min="0" max="100" placeholder="%" required>
                    <select class="form-input role-width" name="role_thanh_vien[]" required>
                        <option value="giảng viên hướng dẫn">giảng viên hướng dẫn</option>
                    </select>
                `;
                authorsFields.appendChild(authorRow);
            }
        });

        formRow.querySelector('#saveHuongDanForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            const phanTramInputs = authorsFields.querySelectorAll('input[name="phan_tram_dong_gop[]"]');
            let tongPhanTram = 0;
            let allPercentagesEntered = true;
            let hasPercentageErrors = false;
            if (phanTramInputs.length === 0 && parseInt(soTacGiaInput.value) > 0) {
                alert('Vui lòng bấm "Xác nhận" và nhập thông tin giảng viên hướng dẫn.');
                return;
            }

            phanTramInputs.forEach(input => {
                const phanTram = parseFloat(input.value);
                if (isNaN(phanTram) || input.value.trim() === '') {
                    allPercentagesEntered = false;
                }
                if (isNaN(phanTram) || phanTram < 0 || phanTram > 100) {
                    hasPercentageErrors = true;
                }
                tongPhanTram += phanTram || 0;
            });

            if (hasPercentageErrors) {
                alert('Phần trăm đóng góp không hợp lệ (phải từ 0 đến 100).');
                return;
            }

            if (phanTramInputs.length > 0 && tongPhanTram !== 100) {
                alert('Tổng phần trăm đóng góp của tất cả thành viên phải bằng 100%!');
                return;
            }

            const tenSanPham = formRow.querySelector('#ten_san_pham').value.trim();
            const soLuong = parseFloat(formRow.querySelector('#so_luong').value);
            const soTacGia = parseInt(formRow.querySelector('#so_tac_gia').value);
            const studentInfor = formRow.querySelector('#student_infor').value.trim();
            const selectedAuthors = authorsFields.querySelectorAll('select[name="thanh_vien[]"]');
            const phanTramAuthors = authorsFields.querySelectorAll('input[name="phan_tram_dong_gop[]"]');
            const roleAuthors = authorsFields.querySelectorAll('select[name="role_thanh_vien[]"]');

            if (!tenSanPham || isNaN(soLuong) || soLuong < 0 || isNaN(soTacGia) || soTacGia < 1 || !studentInfor) {
                alert('Vui lòng điền đầy đủ các trường bắt buộc (Tên đề tài, Số lượng, Thông tin nhóm SV, Số giảng viên hướng dẫn).');
                return;
            }

            if (selectedAuthors.length !== soTacGia || phanTramAuthors.length !== soTacGia || roleAuthors.length !== soTacGia) {
                alert('Số lượng thông tin giảng viên hướng dẫn không khớp với Số giảng viên hướng dẫn đã nhập.');
                return;
            }

            let authorsDataComplete = true;
            selectedAuthors.forEach(select => { if (!select.value) authorsDataComplete = false; });
            phanTramAuthors.forEach(input => { if (input.value.trim() === '') authorsDataComplete = false; });
            roleAuthors.forEach(select => { if (!select.value) authorsDataComplete = false; });

            if (!authorsDataComplete) {
                alert('Vui lòng chọn đầy đủ Giảng viên và nhập Phần trăm đóng góp, Vai trò cho tất cả giảng viên hướng dẫn.');
                return;
            }

            formData.append('type', 'huongdansv');
            formData.append('id', huongDanId);
            formData.append('selected_year_form', selectedYear);
            formData.append('noi_dung', noiDung);
            formData.append('student_infor', studentInfor);
            formData.append('ten_san_pham', tenSanPham);
            formData.append('so_luong', soLuong);
            formData.append('so_tac_gia', soTacGia);

            selectedAuthors.forEach((select, index) => {
                formData.append(`thanh_vien[${index}]`, select.value);
                formData.append(`phan_tram_dong_gop[${index}]`, phanTramAuthors[index].value);
                formData.append(`role_thanh_vien[${index}]`, roleAuthors[index].value);
            });

            fetch('/nckh_more/Nghiepvugiangvien/code/UC/formRegister/NCKH/add_hdsv.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new TypeError("Oops, we haven't got JSON!");
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Lưu thành công!');
                    window.location.reload();
                } else {
                    alert('Lưu thất bại! ' + (data.message || 'Vui lòng thử lại.'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (error instanceof TypeError) {
                    alert('Lỗi: Server không trả về dữ liệu JSON hợp lệ. Vui lòng kiểm tra lại kết nối.');
                } else {
                    alert('Có lỗi xảy ra khi lưu: ' + error.message);
                }
            });
        });
    });
});

function handleDelete() {
    const id = this.getAttribute('data-id');
    const row = this.closest('tr');
    if (confirm('Bạn có chắc chắn muốn xóa mục này không?')) {
        const formData = new FormData();
        formData.append('action', 'delete_huong_dan');
        formData.append('id', id);

        fetch('/nckh_more/Nghiepvugiangvien/code/UC/formRegister/NCKH/delete.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Xóa thành công!');
                window.location.reload();
            } else {
                alert('Xóa thất bại! ' + (data.message || 'Vui lòng thử lại.'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi xóa: ' + error.message);
        });
    }
}

document.querySelectorAll('.delete-btn').forEach(button => {
    button.addEventListener('click', handleDelete);
});

function filterOptions(selectId) {
    let input = document.getElementById(`search_${selectId}`).value.toLowerCase();
    let select = document.getElementById(`options_${selectId}`);
    let options = select.options;
    select.style.display = input.length > 0 ? 'block' : 'none';
    for (let i = 0; i < options.length; i++) {
        let text = options[i].text.toLowerCase();
        options[i].style.display = text.includes(input) ? '' : 'none';
    }
}
function showOptions(selectId) {
    let select = document.getElementById(`options_${selectId}`);
    let input = document.getElementById(`search_${selectId}`).value;
    select.style.display = 'block';
    if (!input) {
        for (let i = 0; i < select.options.length; i++) {
            select.options[i].style.display = '';
        }
    }
}
function selectOption(selectId) {
    let select = document.getElementById(`options_${selectId}`);
    let input = document.getElementById(`search_${selectId}`);
    if (select.selectedIndex !== -1) {
        input.value = select.options[select.selectedIndex].text;
    }
    select.style.display = 'none';
}
document.addEventListener('click', function(event) {
    let containers = document.querySelectorAll('.select-container');
    containers.forEach(container => {
        if (!container.contains(event.target)) {
            let select = container.querySelector('select');
            if (select) select.style.display = 'none';
        }
    });
});
</script>