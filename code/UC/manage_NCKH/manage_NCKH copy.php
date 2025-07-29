<?php
// Kết nối CSDL - đặt ở đầu file, trước HTML
require_once '../../connection/connection.php';

// Khởi tạo session
session_start();

// Kiểm tra và lấy tên bảng từ session
if (isset($_SESSION['current_table'])) {
    $sql_table = $_SESSION['current_table'];
} else {
    // Nếu chưa có session, mặc định là null
    $sql_table = "nckhcc_history";
}

// Định nghĩa các biến columns và labels
$columns = [
    "id", "employeeID", "result_year", "nckh_id", "noi_dung", "ten_san_pham", "so_luong", "vai_tro", "so_tac_gia", "phan_tram_dong_gop",
    "gio_quy_doi", "ngay_cap_nhat", "note", "diem_tap_chi", "ma_so_xuat_ban", "ten_don_vi_xuat_ban", "ten_hoi_thao", "thanh_vien_chu_nhom", "nation_point", "student_infor"
];
$labels = [
    "STT", "Mã giảng viên", "Năm kết quả", "NCKH ID", "Nội dung", "Tên sản phẩm", "Số lượng", "Vai trò", "Số tác giả", "Phần trăm đóng góp",
    "Giờ quy đổi", "Ngày cập nhật", "Ghi chú", "Điểm tạp chí", "Mã số xuất bản", "Tên đơn vị xuất bản", "Tên hội thảo", "Tên chủ nhóm", "Điểm quốc tế", "Đại diện sinh viên"
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý nghiên cứu khoa học</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap Toggle (Switch) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap4-toggle@3.6.1/css/bootstrap4-toggle.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; }
        .toggle-col { margin-right: 8px; accent-color: #0d6efd; width: 36px; height: 20px; }
        .table-responsive { margin-top: 20px; }
        .filter-row input, .filter-row select { width: 100%; }
        .action-btns { gap: 10px; }
        /* Khung cho các box */
        .box-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            padding: 18px 20px 10px 20px;
            margin-bottom: 22px;
            border: 1px solid #e3e6f0;
        }
        .box-section label, .box-section .fw-bold { font-size: 1.08rem; }
        .toggle-label {
            display: flex;
            align-items: center;
            margin-right: 18px;
            margin-bottom: 8px;
        }
        /* Thêm CSS cho các cột dài */
        #nckhTable th:nth-child(20), #nckhTable td:nth-child(20) {
            min-width: 200px;
            max-width: 200px;
            white-space: normal;
            word-wrap: break-word;
        }
        #nckhTable th:nth-child(2), #nckhTable td:nth-child(2) {
            min-width: 270px;
            max-width: 270px;
            white-space: normal;
            word-wrap: break-word;
        }
        #nckhTable th:nth-child(3), #nckhTable td:nth-child(3) {
            min-width: 100px;
            max-width: 150px;
            white-space: normal;
            word-wrap: break-word;
        }
        #nckhTable th:nth-child(5), #nckhTable td:nth-child(5),
        #nckhTable th:nth-child(15), #nckhTable td:nth-child(15),
        #nckhTable th:nth-child(16), #nckhTable td:nth-child(16),
        #nckhTable th:nth-child(17), #nckhTable td:nth-child(17),
        #nckhTable th:nth-child(20), #nckhTable td:nth-child(20) {
            min-width: 200px;
            max-width: 200px;
            white-space: normal;
            word-wrap: break-word;
        }
        #nckhTable th:nth-child(8), #nckhTable td:nth-child(8) {
            min-width: 200px;
            max-width: 200px;
            white-space: normal;
            word-wrap: break-word;
        }
        #nckhTable th:nth-child(18), #nckhTable td:nth-child(18) {
            min-width: 230px;
            max-width: 230px;
            white-space: normal;
            word-wrap: break-word;
        }
        /* Viền rõ ràng cho bảng */
        #nckhTable {
            border-collapse: separate;
            border-spacing: 0;
            background: #e3f2fd;
        }
        #nckhTable th, #nckhTable td {
            border: 1.5px solid #1976d2 !important;
            background-color: #e3f2fd;
            padding: 8px;
        }
        #nckhTable thead th {
            background: #1565c0;
            color: #fff;
            border-bottom: 2.5px solid #1976d2 !important;
        }
        .filter-group {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        #activeFilters .alert {
            margin-bottom: 0;
            padding: 10px 15px;
            font-size: 0.95rem;
        }
        #activeFilters .alert i {
            margin-right: 8px;
        }
        #currentViewAlert {
            margin: 0 auto;
            max-width: 620px;
            font-size: 1.1rem;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        #currentViewAlert i {
            margin-right: 8px;
        }
        /* Ẩn cột */
        .hidden-column {
            display: none;
        }
        /* Ẩn hàng */
        .hidden-row {
            display: none;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4 text-center fw-bold text-primary">Quản lý nghiên cứu khoa học</h1>
    <!-- Nút nhúng -->
    <div class="d-flex justify-content-center mb-4 action-btns">
        <button class="btn btn-outline-primary" data-table="nckhcc_history"><i class="fas fa-flask"></i> Nghiên cứu khoa học các cấp</button>
        <button class="btn btn-outline-success" data-table="huongdansv_history"><i class="fas fa-user-graduate"></i> Hướng dẫn sinh viên làm nghiên cứu khoa học</button>
        <button class="btn btn-outline-warning" data-table="bai_bao_history"><i class="fas fa-file-alt"></i> Viết bài báo</button>
        <button class="btn btn-outline-info" data-table="vietsach_history"><i class="fas fa-book"></i> Viết sách</button>
    </div>
    <!-- Thêm alert box cho thông báo -->
    <div id="currentViewAlert" class="alert alert-primary text-center mb-4" style="display: none;">
        <i class="fas fa-info-circle"></i> <span id="currentViewText"></span>
    </div>
    <!-- Bộ tắt bật cột -->
    <div class="box-section">
        <label class="fw-bold mb-2">Tắt/bật cột:</label>
        <div class="d-flex flex-wrap">
            <?php
            foreach ($labels as $i => $label) {
                echo '<label class="toggle-label"><input type="checkbox" class="toggle-col form-check-input" data-column="'.$i.'" checked> <span>'.$label.'</span></label>';
            }
            ?>
        </div>
    </div>
    <!-- Bộ lọc -->
    <div class="box-section">
        <label class="fw-bold mb-2">Bộ lọc:</label>
        <div class="row filter-row">
            <?php
            // Lấy dữ liệu từ database
            $sql = "SELECT DISTINCT 
                    result_year,
                    diem_tap_chi,
                    ma_so_xuat_ban,
                    ten_don_vi_xuat_ban,
                    ten_hoi_thao,
                    nation_point
                    FROM $sql_table 
                    ORDER BY result_year DESC";
            try {
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Các trường cần filter
                $filterFields = [
                    'result_year' => 'Năm kết quả',
                    'diem_tap_chi' => 'Điểm tạp chí',
                    'ma_so_xuat_ban' => 'Mã số xuất bản',
                    'ten_don_vi_xuat_ban' => 'Tên đơn vị xuất bản',
                    'ten_hoi_thao' => 'Tên hội thảo',
                    'nation_point' => 'Điểm quốc tế'
                ];

                // Lấy các giá trị unique cho mỗi trường filter
                $columnValues = array();
                foreach ($filterFields as $col => $label) {
                    $columnValues[$col] = array();
                }
                foreach ($results as $row) {
                    foreach ($filterFields as $col => $label) {
                        if (!empty($row[$col]) && !in_array($row[$col], $columnValues[$col])) {
                            $columnValues[$col][] = $row[$col];
                        }
                    }
                }

                // Render filter cho từng trường
                foreach ($filterFields as $col => $label) {
                    echo '<div class="col-md-2 mb-2">';
                    echo '<label class="form-label">'.$label.'</label>';
                    echo '<select class="form-select filter-select" data-column="'.$col.'">';
                    echo '<option value="">Tất cả</option>';
                    if (!empty($columnValues[$col])) {
                        sort($columnValues[$col]);
                        foreach ($columnValues[$col] as $value) {
                            echo '<option value="'.htmlspecialchars($value).'">'.htmlspecialchars($value).'</option>';
                        }
                    }
                    echo '</select>';
                    echo '</div>';
                }
            } catch(PDOException $e) {
                echo '<div class="col-12 text-danger">Lỗi: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>
        <!-- Thêm phần hiển thị bộ lọc đang active -->
        <div id="activeFilters" class="mt-3">
            <div class="alert alert-info" style="display: none;">
                <i class="fas fa-filter"></i> <span id="filterText"></span>
            </div>
        </div>
    </div>
    <!-- Ô tìm kiếm -->
    <div class="box-section">
        <input type="text" id="globalSearch" class="form-control" placeholder="Tìm kiếm nhanh...">
    </div>
    <!-- Bảng dữ liệu -->
    <div class="table-responsive">
        <?php if ($sql_table): ?>
        <table id="nckhTable" class="table table-striped table-hover align-middle">
            <thead class="table-primary">
                <tr>
                    <?php foreach ($labels as $index => $label): ?>
                        <th data-column="<?php echo $index; ?>"><?php echo $label; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                // Lấy dữ liệu từ bảng được chọn
                $sql = "SELECT h.*, e.teacherID, e.fullName 
                        FROM $sql_table h 
                        LEFT JOIN employee e ON h.employeeID = e.employeeID 
                        ORDER BY h.ten_san_pham, h.thanh_vien_chu_nhom, h.result_year DESC, h.id DESC";
                
                try {
                    $stmt = $conn->prepare($sql);
                    $stmt->execute();
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if ($results) {
                        // Nhóm dữ liệu theo ten_san_pham và thanh_vien_chu_nhom (chỉ để gộp cột STT)
                        $grouped_data = [];
                        foreach ($results as $row) {
                            $key = $row['ten_san_pham'] . '|' . $row['thanh_vien_chu_nhom'];
                            if (!isset($grouped_data[$key])) {
                                $grouped_data[$key] = [];
                            }
                            $grouped_data[$key][] = $row;
                        }

                        $stt = 1;
                        foreach ($grouped_data as $group) {
                            $rowspan = count($group);
                            foreach ($group as $index => $row) {
                                echo "<tr>";
                                // Cột 1: STT (gộp bằng rowspan)
                                if ($index == 0) {
                                    echo "<td rowspan='$rowspan' data-column='0'>" . $stt++ . "</td>";
                                }
                                // Các cột còn lại hiển thị riêng cho từng hàng
                                echo "<td data-column='1'>" . htmlspecialchars($row['teacherID']) . " - " . htmlspecialchars($row['fullName']) . "</td>";
                                echo "<td data-column='2'>" . htmlspecialchars($row['result_year']) . "</td>";
                                echo "<td data-column='3'>" . htmlspecialchars($row['nckh_id']) . "</td>";
                                echo "<td data-column='4'>" . htmlspecialchars($row['noi_dung']) . "</td>";
                                echo "<td data-column='5'>" . htmlspecialchars($row['ten_san_pham']) . "</td>";
                                echo "<td data-column='6'>" . htmlspecialchars($row['so_luong']) . "</td>";
                                echo "<td data-column='7'>" . htmlspecialchars($row['vai_tro']) . "</td>";
                                echo "<td data-column='8'>" . htmlspecialchars($row['so_tac_gia']) . "</td>";
                                echo "<td data-column='9'>" . htmlspecialchars($row['phan_tram_dong_gop']) . "</td>";
                                echo "<td data-column='10'>" . htmlspecialchars($row['gio_quy_doi']) . "</td>";
                                echo "<td data-column='11'>" . htmlspecialchars($row['ngay_cap_nhat']) . "</td>";
                                echo "<td data-column='12'>" . htmlspecialchars($row['note']) . "</td>";
                                echo "<td data-column='13'>" . htmlspecialchars($row['diem_tap_chi']) . "</td>";
                                echo "<td data-column='14'>" . htmlspecialchars($row['ma_so_xuat_ban']) . "</td>";
                                echo "<td data-column='15'>" . htmlspecialchars($row['ten_don_vi_xuat_ban']) . "</td>";
                                echo "<td data-column='16'>" . htmlspecialchars($row['ten_hoi_thao']) . "</td>";
                                echo "<td data-column='17'>" . htmlspecialchars($row['thanh_vien_chu_nhom']) . "</td>";
                                echo "<td data-column='18'>" . htmlspecialchars($row['nation_point']) . "</td>";
                                echo "<td data-column='19'>" . htmlspecialchars($row['student_infor']) . "</td>";
                                echo "</tr>";
                            }
                        }
                    }
                } catch(PDOException $e) {
                    echo "<tr><td colspan='20' class='text-center text-danger'>Lỗi: " . $e->getMessage() . "</td></tr>";
                }
                ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="alert alert-info text-center">
            <i class="fas fa-info-circle"></i> Vui lòng chọn loại nghiên cứu khoa học để xem dữ liệu
        </div>
        <?php endif; ?>
    </div>
    <!-- Nút xuất báo cáo và quay lại -->
    <div class="d-flex justify-content-between mt-4">
        <button class="btn btn-success"><i class="fas fa-file-export"></i> Xuất báo cáo</button>
        <a href="../../trangchu.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Quay lại</a>
    </div>
</div>
<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap4-toggle@3.6.1/js/bootstrap4-toggle.min.js"></script>
<script>
$(document).ready(function() {
    // Map for column names to display names
    var columnDisplayNames = {
        'result_year': 'Năm kết quả',
        'diem_tap_chi': 'Điểm tạp chí',
        'ma_so_xuat_ban': 'Mã số xuất bản',
        'ten_don_vi_xuat_ban': 'Tên đơn vị xuất bản',
        'ten_hoi_thao': 'Tên hội thảo',
        'nation_point': 'Điểm quốc tế'
    };

    // Map for column names to column indices
    var columnMap = {
        'result_year': 2,
        'diem_tap_chi': 13,
        'ma_so_xuat_ban': 14,
        'ten_don_vi_xuat_ban': 15,
        'ten_hoi_thao': 16,
        'nation_point': 18
    };

    // Automatically hide "Ghi chú" (column 12) and "NCKH ID" (column 3) columns
    $('.toggle-col[data-column="12"], .toggle-col[data-column="3"]').prop('checked', false).trigger('change');

    // Handle column visibility toggle
    $('.toggle-col').on('change', function() {
        var columnIndex = $(this).data('column');
        var isVisible = $(this).prop('checked');
        $('#nckhTable th[data-column="' + columnIndex + '"]').toggleClass('hidden-column', !isVisible);
        $('#nckhTable td[data-column="' + columnIndex + '"]').toggleClass('hidden-column', !isVisible);
    });

    // Initialize column visibility based on checkboxes
    $('.toggle-col').each(function() {
        var columnIndex = $(this).data('column');
        var isVisible = $(this).prop('checked');
        if (!isVisible) {
            $('#nckhTable th[data-column="' + columnIndex + '"]').addClass('hidden-column');
            $('#nckhTable td[data-column="' + columnIndex + '"]').addClass('hidden-column');
        }
    });

    // Handle filter changes
    $('.filter-select').on('change', function() {
        applyFilters();
    });

    // Handle global search
    $('#globalSearch').on('keyup', function() {
        applyFilters();
    });

    function applyFilters() {
        var filters = {};
        $('.filter-select').each(function() {
            var column = $(this).data('column');
            var value = $(this).val();
            if (value) {
                filters[column] = value.trim(); // Trim to remove extra spaces
            }
        });
        var searchText = $('#globalSearch').val().toLowerCase().trim();

        // Update active filters display
        var activeFilters = [];
        for (var column in filters) {
            if (filters[column]) {
                activeFilters.push(columnDisplayNames[column] + ': ' + filters[column]);
            }
        }
        var $filterAlert = $('#activeFilters .alert');
        var $filterText = $('#filterText');
        if (activeFilters.length > 0) {
            $filterText.html('Bạn đã chọn tìm kiếm theo ' + activeFilters.join(' và '));
            $filterAlert.show();
        } else {
            $filterAlert.hide();
        }

        // Apply filters and search
        $('#nckhTable tbody tr').each(function() {
            var row = $(this);
            var showRow = true;

            // Check filters with exact match
            for (var column in filters) {
                var columnIndex = columnMap[column];
                var cell = row.find('td[data-column="' + columnIndex + '"]');
                if (cell.length) {
                    var cellText = cell.text().trim();
                    if (cellText === '' || cellText !== filters[column]) {
                        showRow = false;
                        break;
                    }
                } else {
                    showRow = false; // If no cell found for this column, hide the row
                    break;
                }
            }

            // Check global search
            if (showRow && searchText) {
                var rowText = row.text().toLowerCase();
                if (!rowText.includes(searchText)) {
                    showRow = false;
                }
            }

            // Show/hide row
            row.toggleClass('hidden-row', !showRow);
        });
    }

    // Map tên bảng sang tên hiển thị
    var tableDisplayNames = {
        'nckhcc_history': 'Nghiên cứu khoa học các cấp',
        'huongdansv_history': 'Hướng dẫn sinh viên làm nghiên cứu khoa học',
        'bai_bao_history': 'Viết bài báo',
        'vietsach_history': 'Viết sách'
    };

    // Hàm cập nhật thông báo
    function updateCurrentViewAlert(tableName) {
        var displayName = tableDisplayNames[tableName];
        if (displayName) {
            $('#currentViewText').text('Bạn đang xem dữ liệu: ' + displayName);
            $('#currentViewAlert').fadeIn();
        }
    }

    // Xử lý sự kiện click vào các nút
    $('.action-btns button').on('click', function() {
        var tableName = $(this).data('table');
        
        // Gửi request AJAX để cập nhật bảng
        $.ajax({
            url: 'update_table.php',
            method: 'POST',
            data: {
                table_name: tableName
            },
            success: function(response) {
                // Cập nhật thông báo trước khi reload
                updateCurrentViewAlert(tableName);
                // Reload trang để hiển thị dữ liệu mới
                location.reload();
            },
            error: function(xhr, status, error) {
                console.error('Lỗi:', error);
                alert('Có lỗi xảy ra khi chuyển đổi bảng dữ liệu');
            }
        });
    });

    // Hiển thị thông báo ban đầu nếu có session
    <?php if (isset($_SESSION['current_table'])): ?>
    updateCurrentViewAlert('<?php echo $_SESSION['current_table']; ?>');
    <?php endif; ?>
});
</script>
</body>
</html>