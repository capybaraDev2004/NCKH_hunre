<?php
// Kiểm tra session
if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login/login.php");
    exit();
}
require_once __DIR__ . '/../../../connection/connection.php';

$employeeID = $_SESSION['employeeID'];
// Lấy năm từ dropdown, mặc định là năm hiện tại - 1
$selectedYear = isset($_POST['selected_year']) ? $_POST['selected_year'] : (date('Y') - 1);

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
            <th style="padding: 10px; width: 7%;">Số tác giả</th>
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
                } elseif ($row['so_tac_gia'] < 1) {
                    $errorMessage = 'Số tác giả không hợp lệ';
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
                <form id="saveHuongDanForm">
                    <input type="hidden" name="type" value="huongdansv">
                    <input type="hidden" name="id" value="${huongDanId}">
                    <input type="hidden" name="noi_dung" value="${noiDung}">
                    <input type="hidden" name="selected_year_form" value="${selectedYear}">
                    <div class="form-group">
                        <label for="ten_san_pham">Tên đề tài:</label>
                        <input type="text" name="ten_san_pham" id="ten_san_pham" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="so_luong">Số lượng:</label>
                        <input type="number" name="so_luong" id="so_luong" class="form-input" min="0" step="0.1" required>
                        <span class="error-message" id="so_luong_error">Số lượng phải lớn hơn hoặc bằng 0</span>
                    </div>
                    <div class="form-group">
                        <label for="vai_tro">Vai trò:</label>
                        <select name="vai_tro" id="vai_tro" class="form-input" required>
                            <option value="Chủ nhiệm">Chủ nhiệm</option>
                            <option value="Thành viên">Thành viên</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="so_tac_gia">Số tác giả:</label>
                        <input type="number" name="so_tac_gia" id="so_tac_gia" class="form-input" min="1" required>
                        <span class="error-message" id="so_tac_gia_error">Số tác giả phải lớn hơn 0</span>
                    </div>
                    <div class="form-group">
                        <label for="phan_tram_dong_gop">Phần trăm đóng góp (%):</label>
                        <input type="number" name="phan_tram_dong_gop" id="phan_tram_dong_gop" class="form-input" min="0" max="100" required>
                        <span class="error-message" id="phan_tram_error">Phần trăm phải từ 0 đến 100</span>
                    </div>
                    <div class="form-group">
                        <label>Giờ quy đổi:</label>
                        <span id="gio_quy_doi">0</span> giờ
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
        const phanTramInput = formRow.querySelector('#phan_tram_dong_gop');
        const gioQuyDoiSpan = formRow.querySelector('#gio_quy_doi');
        const inputs = [soLuongInput, soTacGiaInput, phanTramInput];

        inputs.forEach(input => {
            input.addEventListener('input', () => {
                const soLuong = parseFloat(soLuongInput.value) || 0;
                const soTacGia = parseInt(soTacGiaInput.value) || 1;
                const phanTram = parseFloat(phanTramInput.value) || 0;
                let gioQuyDoi = 0;

                let hasError = false;
                if (soLuong < 0) {
                    formRow.querySelector('#so_luong_error').style.display = 'block';
                    hasError = true;
                } else {
                    formRow.querySelector('#so_luong_error').style.display = 'none';
                }
                if (soTacGia < 1) {
                    formRow.querySelector('#so_tac_gia_error').style.display = 'block';
                    hasError = true;
                } else {
                    formRow.querySelector('#so_tac_gia_error').style.display = 'none';
                }
                if (phanTram < 0 || phanTram > 100) {
                    formRow.querySelector('#phan_tram_error').style.display = 'block';
                    hasError = true;
                } else {
                    formRow.querySelector('#phan_tram_error').style.display = 'none';
                }

                const tongGio = khoiLuongGio * soLuong;
                if (!hasError) {
                    if (phanTram === 0 || phanTramInput.value === '') {
                        gioQuyDoi = soTacGia > 0 ? tongGio / soTacGia : 0;
                    } else {
                        gioQuyDoi = (tongGio * phanTram) / 100;
                    }
                }
                gioQuyDoiSpan.textContent = gioQuyDoi.toFixed(2);
            });
        });

        // Xử lý lưu bằng AJAX
        formRow.querySelector('#saveHuongDanForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('http://localhost/NCKH/Nghi%e1%bb%87p%20v%e1%bb%a5%20gi%e1%ba%a3ng%20vi%c3%aan/code/UC/formRegister/NCKH/add.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Lưu thành công!');
                    const historyTable = document.querySelector('#historyTable tbody');
                    const stt = historyTable.querySelectorAll('tr').length + 1;
                    const newRow = document.createElement('tr');
                    newRow.setAttribute('data-huong-dan-id', data.data.nckh_id);
                    newRow.setAttribute('data-id', data.data.id);
                    newRow.innerHTML = `
                        <td style='padding: 10px; text-align: center;'>${stt}</td>
                        <td style='padding: 10px; text-align: left;'>${data.data.noi_dung}</td>
                        <td style='padding: 10px; text-align: left;'>${data.data.ten_san_pham}</td>
                        <td style='padding: 10px; text-align: center;'>${data.data.so_luong}</td>
                        <td style='padding: 10px; text-align: center;'>${data.data.vai_tro}</td>
                        <td style='padding: 10px; text-align: center;'>${data.data.so_tac_gia}</td>
                        <td style='padding: 10px; text-align: center;'>${data.data.phan_tram_dong_gop}%</td>
                        <td style='padding: 10px; text-align: center;'>${Number(data.data.gio_quy_doi).toFixed(2)}</td>
                        <td style='padding: 10px; text-align: center;'>${data.data.ngay_cap_nhat}</td>
                        <td style='padding: 10px; text-align: center;'>
                            <button class='delete-btn' data-id='${data.data.id}'>Xóa</button>
                        </td>
                    `;
                    historyTable.insertBefore(newRow, historyTable.firstChild);
                    formRow.remove();

                    // Gắn sự kiện xóa cho nút mới
                    newRow.querySelector('.delete-btn').addEventListener('click', handleDelete);
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
        formData.append('action', 'delete_huong_dan');
        formData.append('id', id);

        fetch('http://localhost/NCKH/Nghi%E1%BB%87p%20v%E1%BB%A5%20gi%E1%BA%A3ng%20vi%C3%AAn/code/UC/formRegister/NCKH/delete.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Xóa thành công!');
                row.remove();
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
</script>