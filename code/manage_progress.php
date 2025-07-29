<?php
require_once __DIR__ . '/connection/connection.php';

// Lấy danh sách nhân viên đầy đủ
try {
    $stmt = $conn->query("SELECT * FROM employee");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}

// Lọc danh sách chỉ lấy giảng viên, loại trừ "Giảng viên nước ngoài"
$filteredEmployees = array_filter($employees, function($emp) {
    return $emp['role'] === 'Giảng viên' && trim($emp['fullName']) !== 'Giảng viên nước ngoài';
});

// Hàm lấy tiến độ từng nhân viên
function getEmployeeProgress($conn, $employee, $year) {
    // Định mức mặc định - key phải đúng với dữ liệu thực tế trong bảng employee!
    $defaultNorms = [
        'Giảng viên' => ['teach' => 330, 'nckh' => 590, 'other' => 180],
        'Giảng viên (tập sự)' => ['teach' => 165, 'nckh' => 0, 'other' => 1265],
        'Trợ giảng' => ['teach' => 165, 'nckh' => 590, 'other' => 675],
        'Trợ giảng (tập sự)' => ['teach' => 140, 'nckh' => 0, 'other' => 1340],
    ];
    $norms = $defaultNorms[trim($employee['academicTitle'])] ?? ['teach' => 0, 'nckh' => 0, 'other' => 180];

    // 1. Giảng dạy
    $stmt1 = $conn->prepare("SELECT tong_gio_giang_day, dinh_muc_toi_thieu FROM tong_hop_giang_day WHERE employeeID = :employeeID AND result_year = :year");
    $stmt1->execute(['employeeID' => $employee['employeeID'], 'year' => $year]);
    $teach = $stmt1->fetch(PDO::FETCH_ASSOC) ?: [];
    $teach_gio = isset($teach['tong_gio_giang_day']) && $teach['tong_gio_giang_day'] !== null ? $teach['tong_gio_giang_day'] : 0;
    $teach_dinhmuc = (isset($teach['dinh_muc_toi_thieu']) && $teach['dinh_muc_toi_thieu'] !== null && $teach['dinh_muc_toi_thieu'] !== '') ? $teach['dinh_muc_toi_thieu'] : $norms['teach'];

    // 2. NCKH
    $stmt2 = $conn->prepare("SELECT tong_gio_nckh, dinh_muc_toi_thieu, dieu_kien FROM tong_hop_nckh WHERE employeeID = :employeeID AND result_year = :year");
    $stmt2->execute(['employeeID' => $employee['employeeID'], 'year' => $year]);
    $research = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];
    $nckh_gio = isset($research['tong_gio_nckh']) && $research['tong_gio_nckh'] !== null ? $research['tong_gio_nckh'] : 0;
    $nckh_dinhmuc = (isset($research['dinh_muc_toi_thieu']) && $research['dinh_muc_toi_thieu'] !== null && $research['dinh_muc_toi_thieu'] !== '') ? $research['dinh_muc_toi_thieu'] : $norms['nckh'];
    $nckh_ghichu = !empty($research['dieu_kien']) ? $research['dieu_kien'] : 'Không có ghi chú';

    // 3. Nhiệm vụ khác
    $stmt3 = $conn->prepare("SELECT total_completed_hours, dinh_muc_toi_thieu FROM total_hours WHERE employee_id = :employeeID AND year = :year");
    $stmt3->execute(['employeeID' => $employee['employeeID'], 'year' => $year]);
    $other = $stmt3->fetch(PDO::FETCH_ASSOC) ?: [];
    $other_gio = isset($other['total_completed_hours']) && $other['total_completed_hours'] !== null ? $other['total_completed_hours'] : 0;
    $other_dinhmuc = (isset($other['dinh_muc_toi_thieu']) && $other['dinh_muc_toi_thieu'] !== null && $other['dinh_muc_toi_thieu'] !== '') 
        ? $other['dinh_muc_toi_thieu'] 
        : $norms['other']; // Không cần fallback 180 nữa vì đã đúng

    return [
        'teaching' => ['completed' => $teach_gio, 'target' => $teach_dinhmuc],
        'research' => [
            'completed' => $nckh_gio,
            'target' => $nckh_dinhmuc,
            'ghichu' => $nckh_ghichu
        ],
        'other' => ['completed' => $other_gio, 'target' => $other_dinhmuc]
    ];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Tiến Độ</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
            backdrop-filter: blur(4px);
            padding: 2rem;
            margin: 2rem auto;
            max-width: 1400px;
            transition: transform 0.3s ease;
        }
        .main-card:hover {
            transform: translateY(-5px);
        }
        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .table thead th {
            background: #4a90e2;
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
        }
        .table tbody td {
            vertical-align: middle;
            padding: 1rem;
        }
        .progress {
            height: 10px;
            border-radius: 5px;
            background-color: #e9ecef;
            margin-top: 5px;
        }
        .progress-bar {
            border-radius: 5px;
            transition: width 0.6s ease;
        }
        .btn-custom {
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .dataTables_filter input {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }
        .dataTables_filter input:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .status-completed {
            background-color: #28a745;
            color: white;
        }
        .status-incomplete {
            background-color: #dc3545;
            color: white;
        }
        .status-warning {
            background-color: #ffc107;
            color: black;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="trangchu.php" class="btn btn-danger btn-custom">
                    <i class="fas fa-arrow-left"></i> Quay lại
                </a>
                <h1 class="fw-bold mb-0 text-center flex-grow-1">Quản lý tiến độ</h1>
                <button id="sendMailBtn" class="btn btn-warning btn-custom" style="position: absolute; right: 40px;">
                    <i class="fas fa-envelope"></i> Gửi mail cảnh báo tiến độ
                </button>
            </div>
            
            <div class="table-responsive">
                <table id="progressTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Mã GV</th>
                            <th>Họ và tên</th>
                            <th>Mã giảng viên</th>
                            <th>Chức vụ</th>
                            <th>Chức vụ giảng viên</th>
                            <th>Giảng dạy</th>
                            <th>Nghiên cứu khoa học</th>
                            <th>Nhiệm vụ khác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $stt = 1; foreach ($filteredEmployees as $employee): 
                            $progress = getEmployeeProgress($conn, $employee, date('Y'));
                        ?>
                            <tr>
                                <td><?= $stt++ ?></td>
                                <td><?= htmlspecialchars($employee['employeeID']) ?></td>
                                <td><?= htmlspecialchars($employee['fullName']) ?></td>
                                <td><?= htmlspecialchars($employee['teacherID']) ?></td>
                                <td><?= htmlspecialchars($employee['role']) ?></td>
                                <td><?= htmlspecialchars($employee['academicTitle']) ?></td>
                                <td>
                                    <span><?= number_format($progress['teaching']['completed'], 1) ?>/<?= number_format($progress['teaching']['target'], 1) ?> giờ</span><br>
                                    <span class="<?= $progress['teaching']['completed'] >= $progress['teaching']['target'] ? 'text-success' : 'text-danger' ?>">
                                        <?= $progress['teaching']['completed'] >= $progress['teaching']['target'] ? 'Hoàn thành' : 'Chưa hoàn thành' ?>
                                    </span>
                                </td>
                                <td>
                                    <span><?= number_format($progress['research']['completed'], 1) ?>/<?= number_format($progress['research']['target'], 1) ?> giờ</span><br>
                                    <span class="<?= $progress['research']['completed'] >= $progress['research']['target'] ? 'text-success' : 'text-danger' ?>">
                                        <?= $progress['research']['completed'] >= $progress['research']['target'] ? 'Hoàn thành' : 'Chưa hoàn thành' ?>
                                    </span>
                                    <br>
                                    <span class="text-secondary small"><b>Ghi chú:</b> <?= htmlspecialchars($progress['research']['ghichu']) ?></span>
                                </td>
                                <td>
                                    <span><?= number_format($progress['other']['completed'], 1) ?>/<?= number_format($progress['other']['target'], 1) ?> giờ</span><br>
                                    <span class="<?= $progress['other']['completed'] >= $progress['other']['target'] ? 'text-success' : 'text-danger' ?>">
                                        <?= $progress['other']['completed'] >= $progress['other']['target'] ? 'Hoàn thành' : 'Chưa hoàn thành' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#progressTable').DataTable({
                pageLength: 10,
                responsive: true,
                language: {
                    search: "Tìm kiếm:",
                    lengthMenu: "Hiển thị _MENU_ bản ghi mỗi trang",
                    info: "Hiển thị _START_ đến _END_ của _TOTAL_ bản ghi",
                    paginate: {
                        first: "Đầu",
                        previous: "Trước",
                        next: "Tiếp",
                        last: "Cuối"
                    }
                },
                order: [[1, 'asc']],
                columnDefs: [
                    {
                        targets: [5, 6, 7],
                        orderable: false
                    }
                ]
            });

            // Xử lý gửi mail cảnh báo tiến độ
            $('#sendMailBtn').on('click', function() {
                if (confirm('Bạn có chắc chắn muốn gửi mail cảnh báo tiến độ cho tất cả giảng viên?')) {
                    $.ajax({
                        url: 'manage_account.php',
                        type: 'POST',
                        data: { action: 'send_warning_mail' },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                alert('Đã gửi mail cảnh báo!\n' + (response.message ? response.message.join('\n') : ''));
                            } else {
                                alert('Lỗi khi gửi mail: ' + (response.error || 'Không xác định'));
                            }
                        },
                        error: function(xhr, status, error) {
                            alert('Đã xảy ra lỗi khi gửi mail: ' + error);
                        }
                    });
                }
            });
        });
    </script>
    <?php if (isset($_GET['sendmail'])): ?>
    <script>
        // Đợi khi gửi mail xong (có thể dựa vào response AJAX hoặc reload lại)
        window.onload = function() {
            alert('Đã gửi mail cảnh báo tiến độ cho các giảng viên!');
            window.location.href = 'manage_progress.php';
        }
    </script>
    <?php endif; ?>
</body>
</html>
