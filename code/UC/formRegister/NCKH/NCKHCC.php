<?php
// Kiểm tra session
if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login/login.php");
    exit();
}
require_once __DIR__ . '/../../../connection/connection.php';

$employeeID = $_SESSION['employeeID'];
// Lấy năm từ dropdown, mặc định là năm hiện tại - 1
$selectedYear = isset($_POST['selected_year']) ? $_POST['selected_year'] : (date('Y'));

// Lấy thông tin giảng viên
$stmt = $conn->prepare("SELECT academicTitle, leadershipPosition, rankTeacher FROM employee WHERE employeeID = :employeeID");
$stmt->execute([':employeeID' => $employeeID]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// Xác định định mức tối thiểu
$dinhMuc = ($employee['academicTitle'] === 'Giảng viên (tập sự)') ? 0 : 590;
if (!empty($employee['leadershipPosition'])) {
    $dinhMuc = 590;
}

// Lấy danh sách sản phẩm NCKH các cấp
$stmt = $conn->prepare("SELECT ID, NoiDung, KhoiLuongGio FROM nghiencuukhoahoccaccap");
$stmt->execute();
$nckhList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách giảng viên
$stmtGV = $conn->prepare("SELECT employeeID, teacherID, fullName FROM employee WHERE role = 'Giảng viên'");
$stmtGV->execute();
$giangVienList = $stmtGV->fetchAll(PDO::FETCH_ASSOC);

// Xử lý tìm kiếm
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$filteredNckhList = $nckhList;
if ($searchQuery) {
    $filteredNckhList = array_filter($nckhList, function($item) use ($searchQuery) {
        return stripos($item['NoiDung'], $searchQuery) !== false;
    });
}
?>

<h2 class="form-title">Nghiên cứu khoa học các cấp</h2>

<!-- Năm lưu dữ liệu -->
<div style="text-align: center; margin-bottom: 15px;">
    <label for="selected_year">Năm lưu dữ liệu: </label>
    <form id="yearForm" method="POST">
        <select name="selected_year" id="selectedYear" class="year-select" onchange="this.form.submit()">
            <?php
            $currentYear = date('Y');
            for ($i = $currentYear - 5; $i <= $currentYear + 1; $i++) {
                echo "<option value='$i'" . ($selectedYear == $i ? " selected" : "") . ">$i</option>";
            }
            ?>
        </select>
    </form>
</div>

<!-- Form tìm kiếm -->
<div class="search-container">
    <label for="search">Tìm kiếm: </label>
    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Nhập nội dung NCKH..." onkeyup="searchTable()" class="search-input">
</div>

<!-- Bảng danh sách sản phẩm NCKH -->
<form method="POST" id="nckhForm">
    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;" id="nckhTable">
        <thead>
            <tr style="background-color: #223771; color: white;">
                <th style="padding: 10px; width: 5%;">STT</th>
                <th style="padding: 10px; width: 70%;">Nội dung nghiên cứu khoa học</th>
                <th style="padding: 10px; width: 15%;">Khối lượng giờ</th>
                <th style="padding: 10px; width: 10%;">Đăng ký</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filteredNckhList as $nckh): ?>
                <tr data-nckh-id="<?php echo $nckh['ID']; ?>">
                    <td style="padding: 10px; text-align: center;"><?php echo htmlspecialchars($nckh['ID']); ?></td>
                    <td style="padding: 10px; text-align: left;"><?php echo htmlspecialchars($nckh['NoiDung']); ?></td>
                    <td style="padding: 10px; text-align: center;" class="khoi-luong-gio" data-nckh-id="<?php echo $nckh['ID']; ?>">
                        <?php echo htmlspecialchars($nckh['KhoiLuongGio']); ?>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <button type="button" name="selected_nckh_id" value="<?php echo $nckh['ID']; ?>" class="register-btn">Đăng ký</button>
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
            <th style="padding: 10px; width: 20%;">Nội dung NCKH</th>
            <th style="padding: 10px; width: 20%;">Tên sản phẩm</th>
            <th style="padding: 10px; width: 7%;">Số lượng</th>
            <th style="padding: 10px; width: 8%;">Vai trò</th>
            <th style="padding: 10px; width: 7%;">Số tác giả</th>
            <th style="padding: 10px; width: 8%;">Phần trăm đóng góp</th>
            <th style="padding: 10px; width: 7%;">Giờ quy đổi</th>
            <th style="padding: 10px; width: 10%;">Ngày cập nhật</th>
            <th style="padding: 10px; width: 5%;">Hành động</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $historyStmt = $conn->prepare("
            SELECT vh.*, v.KhoiLuongGio 
            FROM nckhcc_history vh
            LEFT JOIN nghiencuukhoahoccaccap v ON vh.nckh_id = v.ID
            WHERE vh.employeeID = :employeeID AND vh.result_year = :result_year
        ");
        $historyStmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
        $historyData = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($historyData) {
            $stt = 1;
            foreach ($historyData as $row) {
                $tongGio = $row['KhoiLuongGio'] * $row['so_luong'];
                $gioQuyDoi = $row['gio_quy_doi'];
                $errorMessage = '';

                if ($row['so_luong'] < 0) {
                    $errorMessage = 'Số lượng không hợp lệ';
                    $gioQuyDoi = 0;
                } elseif ($row['so_tac_gia'] < 1) {
                    $errorMessage = 'Số tác giả không hợp lệ';
                    $gioQuyDoi = 0;
                } elseif ($row['phan_tram_dong_gop'] < 0 || $row['phan_tram_dong_gop'] > 100) {
                    $errorMessage = 'Phần trăm không hợp lệ';
                    $gioQuyDoi = 0;
                }

                echo "<tr data-nckh-id='{$row['nckh_id']}' data-id='{$row['id']}'>
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
    .nckhcc>a{color: #f8843d !important}

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

    .styled-form .form-group {
        display: flex;
        align-items: center;
        margin-bottom: 12px;
        gap: 12px;
    }

    .form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 10px;
        justify-content: center;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        margin-bottom: 0;
    }
    .form-group label {
        margin-bottom: 4px;
        font-weight: 500;
        text-align: left;
    }
    .form-group.small-width {
        width: 217px;
        min-width: 210px;
        max-width: 240px;
    }
    .form-group.half-width {
        flex: 1 1 200px;
        min-width: 180px;
    }
    .form-group.full-width {
        flex: 1 1 100%;
    }
    .form-group.tacgia-width {
        width: 100px;
        min-width: 100px;
        max-width: 120px;
    }
    .form-group.thanhvien-width {
        width: 350px;
        min-width: 250px;
        max-width: 400px;
    }
    .form-group.phantram-width {
        width: 80px;
        min-width: 80px;
        max-width: 100px;
    }
    .xac-nhan-btn {
        background: #223771;
        color: #fff;
        border-radius: 4px;
        padding: 8px 0;
        border: none;
        cursor: pointer;
        font-size: 14px;
        transition: background 0.2s;
        margin-top: 38px;

    }
    .xac-nhan-btn:hover {
        background: #f8843d;
    }

    .role_thanh_vien{
        border: 1px solid #d2ddfd;
        height: 35px;
        border-radius: 5px;
        width: 100px;
        text-align: center;
        justify-content: center;
    }

    #dynamic-authors-row {
        align-items: flex-start !important;
    }

    #dynamic-authors-row .form-group {
        margin-bottom: 0;
    }

    #dynamic-authors-row .xac-nhan-btn {
        margin-top: 35px !important;
        height: 61px;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 100px;
        max-width: 120px;
        padding: 10px 10px;
        box-sizing: border-box;
    }

    #dynamic-authors-row .form-group[style*="align-self: flex-end"] {
        align-self: flex-start !important;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        height: 36px;
        display: flex;
        align-items: center;
    }

    /* New styles for searchable select */
    .select-container {
        position: relative;
        width: 220px;
    }
    .select-container input[type="text"] {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }
    .select-container select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        max-height: 200px;
        position: absolute;
        top: 40px;
        left: 0;
        display: none;
        background: white;
        z-index: 10;
    }
    .select-container select option {
        padding: 5px;
    }
    .select-container select:focus {
        outline: none;
    }
</style>

<script>
function searchTable() {
    const searchValue = document.getElementById('search').value.toLowerCase();
    const rows = document.querySelectorAll('#nckhTable tbody tr');
    rows.forEach(row => {
        const noiDung = row.cells[1].textContent.toLowerCase();
        row.style.display = noiDung.includes(searchValue) ? '' : 'none';
    });
}

// Đưa danh sách giảng viên sang JS
const GIANG_VIEN_LIST = <?php echo json_encode($giangVienList); ?>;

// Xử lý hiển thị form dưới dòng được chọn
document.querySelectorAll('.register-btn').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();

        const existingForm = document.querySelector('.dynamic-form');
        if (existingForm) existingForm.remove();

        const nckhId = this.value;
        const row = this.closest('tr');
        const noiDung = row.cells[1].textContent;
        const khoiLuongGio = parseFloat(row.cells[2].textContent);
        const selectedYear = document.getElementById('selectedYear').value;

        const formRow = document.createElement('tr');
        formRow.className = 'dynamic-form';
        formRow.innerHTML = `
            <td colspan="4">
                <h3>Đăng ký NCKH: ${noiDung} (Năm ${selectedYear})</h3>
                <form id="saveNckhForm" class="styled-form">
                    <input type="hidden" name="type" value="nckhcc">
                    <input type="hidden" name="id" value="${nckhId}">
                    <input type="hidden" name="noi_dung" value="${noiDung}">
                    <input type="hidden" name="selected_year_form" value="${selectedYear}">

                    <!-- Hàng 1: Tên sản phẩm -->
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="ten_san_pham">Tên sản phẩm:</label>
                            <input type="text" name="ten_san_pham" id="ten_san_pham" class="form-input" required>
                        </div>
                    </div>

                    <!-- Hàng 2: Số lượng, Vai trò, Điểm tạp chí, Mã số xuất bản -->
                    <div class="form-row">
                        <div class="form-group small-width">
                        <label for="vai_tro">Quốc tế:</label>
                        <select name="nation_point" id="nation_point" class="form-input" required>
                            <option value="Q1">Q1</option>
                            <option value="Q2">Q2</option>
                            <option value="Q3">Q3</option>
                            <option value="Q4">Q4</option>
                            <option value="ISSN và điểm HĐ chức danh GSNN < 0,5">ISSN và điểm HĐ chức danh GSNN < 0,5</option>
                            <option value="ISSN và điểm HĐ chức danh GSNN là 0,5">ISSN và điểm HĐ chức danh GSNN là 0,5</option>
                            <option value="ISSN và điểm HĐ chức danh GSNN là 0,75">ISSN và điểm HĐ chức danh GSNN là 0,75</option>
                            <option value="ISSN và điểm HĐ chức danh GSNN >= 1,0">ISSN và điểm HĐ chức danh GSNN >= 1,0</option>
                        </select>
                        </div>

                        <div class="form-group small-width">
                            <label for="so_luong">Số lượng:</label>
                            <input type="number" name="so_luong" id="so_luong" class="form-input" min="0" step="0.1" required>
                            <span class="error-message" id="so_luong_error">Số lượng phải lớn hơn hoặc bằng 0</span>
                        </div>

                        <div class="form-group small-width">
                            <label for="diem_tap_chi">Điểm tạp chí:</label>
                            <input type="number" name="diem_tap_chi" id="diem_tap_chi" class="form-input" min="0" step="0.01">
                        </div>
                        <div class="form-group small-width">
                            <label for="ma_so_xuat_ban">Mã số xuất bản:</label>
                            <input type="text" name="ma_so_xuat_ban" id="ma_so_xuat_ban" class="form-input">
                        </div>
                    </div>

                    <!-- Hàng 3: Tên đơn vị xuất bản, Tên hội thảo -->
                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="ten_don_vi_xuat_ban">Tên đơn vị xuất bản:</label>
                            <input type="text" name="ten_don_vi_xuat_ban" id="ten_don_vi_xuat_ban" class="form-input" required>
                        </div>
                        <div class="form-group half-width">
                            <label for="ten_hoi_thao">Tên hội thảo:</label>
                            <input type="text" name="ten_hoi_thao" id="ten_hoi_thao" class="form-input">
                        </div>
                    </div>

                    <!-- Hàng 4: Số tác giả, Tên thành viên, Phần trăm đóng góp, Xác nhận -->
                    <div class="form-row" id="dynamic-authors-row" style="justify-content: flex-start;">
                        <div class="form-group tacgia-width" style="margin-right: 12px;">
                            <label for="so_tac_gia">Số tác giả:</label>
                            <input type="number" name="so_tac_gia" id="so_tac_gia" class="form-input" min="1" required>
                            <span class="error-message" id="so_tac_gia_error">Số tác giả phải lớn hơn 0</span>
                        </div>
                        <div class="form-group" style="align-self: flex-start; margin-right: 24px; height: 36px; display: flex; align-items: center;">
                            <button type="button" class="xac-nhan-btn" style="width: 100px; margin-top: 0;">Xác nhận</button>
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
        const xacNhanBtn = formRow.querySelector('.xac-nhan-btn');

        // Sự kiện cho nút xác nhận: sinh dòng thành viên
        xacNhanBtn.addEventListener('click', function() {
            const soTacGia = parseInt(soTacGiaInput.value) || 0;
            authorsFields.innerHTML = '';
            if (soTacGia < 1) {
                formRow.querySelector('#so_tac_gia_error').style.display = 'block';
                return;
            } else {
                formRow.querySelector('#so_tac_gia_error').style.display = 'none';
            }
            for (let i = 0; i < soTacGia; i++) {
                const row = document.createElement('div');
                row.className = 'form-row';
                row.style.display = 'flex';
                row.style.gap = '16px';
                const selectId = `thanh_vien_${i}`;
                row.innerHTML = `
                    <div class="select-container">
                        <input type="text" id="search_${selectId}" placeholder="Tìm giảng viên..." onkeyup="filterOptions('${selectId}')" onclick="showOptions('${selectId}')">
                        <select id="options_${selectId}" name="thanh_vien[]" size="20" onchange="selectOption('${selectId}')" required>
                            <option value="">-- Chọn giảng viên --</option>
                            ${GIANG_VIEN_LIST.map(gv => `<option value="${gv.employeeID}">${gv.fullName} (${gv.teacherID})</option>`).join('')}
                        </select>
                    </div>
                    <input type="number" name="phan_tram_dong_gop[]" class="form-input" min="0" max="100" placeholder="%" style="width: 80px;" required>
                    <select class="role_thanh_vien form-input" name="role_thanh_vien[]" style="width: 100px;" required>
                        <option>Tác giả</option>
                        <option>Thành viên</option>
                    </select>
                `;
                authorsFields.appendChild(row);
            }
        });

        // Xử lý lưu bằng AJAX
        formRow.querySelector('#saveNckhForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            const phanTramInputs = formRow.querySelectorAll('input[name="phan_tram_dong_gop[]"]');
            let tongPhanTram = 0;
            phanTramInputs.forEach(input => {
                tongPhanTram += parseFloat(input.value) || 0;
            });
            if (phanTramInputs.length > 0 && tongPhanTram !== 100) {
                alert('Tổng phần trăm đóng góp của tất cả thành viên phải bằng 100!');
                return;
            }

            fetch('/nckh_more/Nghiepvugiangvien/code/UC/formRegister/NCKH/add.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
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
                alert('Có lỗi xảy ra khi lưu: ' + error.message);
            });
        });
    });
});

// Xử lý xóa dữ liệu
function handleDelete() {
    const id = this.getAttribute('data-id');
    const row = this.closest('tr');
    if (confirm('Bạn có chắc chắn muốn xóa mục này không?')) {
        const formData = new FormData();
        formData.append('action', 'delete_nckh');
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

// Add new functions for searchable select
function filterOptions(selectId) {
    let input = document.getElementById(`search_${selectId}`).value.toLowerCase();
    let select = document.getElementById(`options_${selectId}`);
    let options = select.options;

    // Show select when there's input
    select.style.display = input.length > 0 ? 'block' : 'none';

    // Filter options
    for (let i = 0; i < options.length; i++) {
        let text = options[i].text.toLowerCase();
        options[i].style.display = text.includes(input) ? '' : 'none';
    }
}

function showOptions(selectId) {
    let select = document.getElementById(`options_${selectId}`);
    let input = document.getElementById(`search_${selectId}`).value;
    // Show all options when clicking input if empty
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
    // Update input value when selecting an option
    if (select.selectedIndex !== -1) {
        input.value = select.options[select.selectedIndex].text;
    }
    // Hide select after selection
    select.style.display = 'none';
}

// Hide select when clicking outside
document.addEventListener('click', function(event) {
    let containers = document.querySelectorAll('.select-container');
    containers.forEach(container => {
        if (!container.contains(event.target)) {
            let selectId = container.querySelector('select').id.split('_')[1];
            document.getElementById(`options_${selectId}`).style.display = 'none';
        }
    });
});
</script>   