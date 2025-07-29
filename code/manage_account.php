<?php
require_once __DIR__ . '/connection/connection.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Hàm ghi log
function debugLog($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, __DIR__ . '/debug.log');
}

// Xử lý yêu cầu xóa nếu có
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $employeeID = $_POST['employeeID'] ?? '';
    if ($employeeID) {
        try {
            $stmt = $conn->prepare("DELETE FROM employee WHERE employeeID = :employeeID");
            $stmt->bindParam(':employeeID', $employeeID);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            debugLog("Lỗi xóa giảng viên ID {$employeeID}: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    } else {
        debugLog("Lỗi: ID giảng viên không hợp lệ");
        echo json_encode(['success' => false, 'error' => 'Invalid employee ID']);
        exit;
    }
}

// Xử lý gửi mail cảnh báo tiến độ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_warning_mail') {
    $result = [];
    debugLog("Bắt đầu gửi mail cảnh báo");
    
    try {
        // Lấy danh sách giảng viên
        $stmt = $conn->prepare("SELECT employeeID, fullName, email FROM employee WHERE role = 'Giảng viên'");
        $stmt->execute();
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        debugLog("Số giảng viên tìm thấy: " . count($teachers));

        $now = date('d/m/Y H:i:s');
        $currentYear = date('Y');

        foreach ($teachers as $teacher) {
            $employeeID = $teacher['employeeID'];
            $fullName = $teacher['fullName'];
            $email = $teacher['email'];

            // Lấy academicTitle và teacherID
            $stmtTitle = $conn->prepare("SELECT academicTitle, teacherID FROM employee WHERE employeeID = :employeeID");
            $stmtTitle->execute(['employeeID' => $employeeID]);
            $rowTitle = $stmtTitle->fetch(PDO::FETCH_ASSOC);
            $academicTitle = $rowTitle['academicTitle'] ?? '';
            $teacherID = $rowTitle['teacherID'] ?? '';

            // Thiết lập định mức mặc định
            $defaultNorms = [
                'Giảng viên' => ['teach' => 330, 'nckh' => 590, 'other' => 180],
                'Giảng viên tập sự' => ['teach' => 165, 'nckh' => 0, 'other' => 1265],
                'Trợ giảng' => ['teach' => 165, 'nckh' => 590, 'other' => 675],
                'Trợ giảng tập sự' => ['teach' => 140, 'nckh' => 0, 'other' => 1340],
            ];
            $norms = $defaultNorms[$academicTitle] ?? ['teach' => 'N/A', 'nckh' => 'N/A', 'other' => 'N/A'];

            // Kiểm tra email hợp lệ
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                debugLog("Email không hợp lệ cho {$fullName}: {$email}");
                $result[] = "Email không hợp lệ cho {$fullName} ({$email})";
                continue;
            }

            debugLog("Chuẩn bị gửi mail cho {$fullName} ({$email})");

            // Lấy dữ liệu từng bảng
            try {
                // 1. Giảng dạy trực tiếp
                $stmt1 = $conn->prepare("SELECT tong_gio_giang_day, dinh_muc_toi_thieu FROM tong_hop_giang_day WHERE employeeID = :employeeID AND result_year = :year");
                $stmt1->execute(['employeeID' => $employeeID, 'year' => $currentYear]);
                $teach = $stmt1->fetch(PDO::FETCH_ASSOC) ?: [];
                $teach_gio = isset($teach['tong_gio_giang_day']) && $teach['tong_gio_giang_day'] !== null ? $teach['tong_gio_giang_day'] : 0;
                $teach_dinhmuc = isset($teach['dinh_muc_toi_thieu']) && $teach['dinh_muc_toi_thieu'] !== null && $teach['dinh_muc_toi_thieu'] !== '' ? $teach['dinh_muc_toi_thieu'] : $norms['teach'];
                $teach_trangthai = ($teach_gio >= $teach_dinhmuc) ? 'Hoàn thành' : 'Chưa hoàn thành';

                // 2. Nghiên cứu khoa học
                $stmt2 = $conn->prepare("SELECT tong_gio_nckh, dinh_muc_toi_thieu, trang_thai_hoan_thanh, dieu_kien FROM tong_hop_nckh WHERE employeeID = :employeeID AND result_year = :year");
                $stmt2->execute(['employeeID' => $employeeID, 'year' => $currentYear]);
                $research = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];
                $nckh_gio = isset($research['tong_gio_nckh']) && $research['tong_gio_nckh'] !== null ? $research['tong_gio_nckh'] : 0;
                $nckh_dinhmuc = isset($research['dinh_muc_toi_thieu']) && $research['dinh_muc_toi_thieu'] !== null && $research['dinh_muc_toi_thieu'] !== '' ? $research['dinh_muc_toi_thieu'] : $norms['nckh'];
                $nckh_trangthai = $research['trang_thai_hoan_thanh'] ?? 'N/A';
                $nckh_ghichu = !empty($research['dieu_kien']) ? $research['dieu_kien'] : 'Không có ghi chú';

                // 3. Nhiệm vụ khác
                $stmt3 = $conn->prepare("SELECT total_completed_hours, dinh_muc_toi_thieu FROM total_hours WHERE employee_id = :employeeID AND year = :year");
                $stmt3->execute(['employeeID' => $employeeID, 'year' => $currentYear]);
                $other = $stmt3->fetch(PDO::FETCH_ASSOC) ?: [];
                $other_gio = isset($other['total_completed_hours']) && $other['total_completed_hours'] !== null ? $other['total_completed_hours'] : 0;
                $other_dinhmuc = isset($other['dinh_muc_toi_thieu']) && $other['dinh_muc_toi_thieu'] !== null && $other['dinh_muc_toi_thieu'] !== '' ? $other['dinh_muc_toi_thieu'] : $norms['other'];
                $other_trangthai = ($other_gio >= $other_dinhmuc) ? 'Hoàn thành' : 'Chưa hoàn thành';

                // Soạn nội dung mail
                $mailContent = "Hệ thống cảnh báo chỉ tiêu của giảng viên: <b>$fullName - $teacherID</b><br>";
                $mailContent .= "Tính đến thời điểm <b>$now</b>, bạn đã đạt được định mức như sau:<br><br>";
                $mailContent .= "1. <b>Giảng dạy trực tiếp</b>:<br>";
                $mailContent .= "- Định mức: {$teach_dinhmuc}<br>";
                $mailContent .= "- Tổng giờ đạt được: {$teach_gio}<br>";
                $mailContent .= "- Trạng thái hoàn thành: {$teach_trangthai}<br><br>";

                $mailContent .= "2. <b>Nghiên cứu khoa học</b>:<br>";
                $mailContent .= "- Định mức: {$nckh_dinhmuc}<br>";
                $mailContent .= "- Tổng giờ đạt được: {$nckh_gio}<br>";
                $mailContent .= "- Trạng thái hoàn thành: {$nckh_trangthai}<br>";
                $mailContent .= "- Ghi chú: {$nckh_ghichu}<br><br>";

                $mailContent .= "3. <b>Nhiệm vụ khác</b>:<br>";
                $mailContent .= "- Định mức: {$other_dinhmuc}<br>";
                $mailContent .= "- Tổng giờ đạt được: {$other_gio}<br>";
                $mailContent .= "- Trạng thái hoàn thành: {$other_trangthai}<br>";

                // Gửi mail
                $mail = new PHPMailer(true);
                try {
                    // Cấu hình SMTP
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'nguyentientoan28022004@gmail.com';
                    $mail->Password = 'momj ghwf mzpg ipuy';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    $mail->SMTPDebug = 2; // Bật debug chi tiết
                    $mail->Debugoutput = function($str, $level) {
                        debugLog("PHPMailer Debug [Level $level]: $str");
                    };

                    $mail->setFrom('nguyentientoan28022004@gmail.com', 'Hệ thống quản lý nghiệp vụ giảng viên');
                    $mail->addAddress($email, $fullName);
                    $mail->CharSet = 'UTF-8';
                    $mail->isHTML(true);
                    $mail->Subject = 'Cảnh báo tiến độ công việc';
                    $mail->Body = $mailContent;

                    $mail->send();
                    debugLog("Gửi mail thành công cho {$fullName} ({$email})");
                    $result[] = "Đã gửi mail cho {$fullName} ({$email})";
                } catch (Exception $e) {
                    debugLog("Lỗi gửi mail cho {$fullName} ({$email}): {$mail->ErrorInfo}");
                    $result[] = "Không gửi được mail cho {$fullName} ({$email}): {$mail->ErrorInfo}";
                }
            } catch (PDOException $e) {
                debugLog("Lỗi truy vấn dữ liệu cho {$fullName}: {$e->getMessage()}");
                $result[] = "Lỗi truy vấn dữ liệu cho {$fullName}: {$e->getMessage()}";
            }
        }
        echo json_encode(['success' => !empty($result), 'message' => $result]);
        exit;
    } catch (Exception $e) {
        debugLog("Lỗi tổng quát khi gửi mail: {$e->getMessage()}");
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Lấy danh sách giảng viên
try {
    $stmt = $conn->query("SELECT * FROM employee WHERE fullName != 'giảng viên nước ngoài'");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    debugLog("Lỗi truy vấn danh sách giảng viên: {$e->getMessage()}");
    die("Lỗi truy vấn: " . $e->getMessage());
}

// Hàm định dạng ngày thành dd/mm/yyyy
function formatDate($date) {
    if ($date && $date !== '0000-00-00') {
        return date('d/m/Y', strtotime($date));
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Người Dùng</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%);
            min-height: 100vh;
        }
        .main-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.10);
            padding: 32px 18px 24px 18px;
            margin: 40px auto;
            max-width: 1200px;
            animation: fadeInDown 0.8s;
        }
        .table-responsive {
            border-radius: 16px;
            overflow: auto;
            box-shadow: 0 2px 12px rgba(31,38,135,0.06);
            background: #f8f9fa;
            margin-top: 18px;
        }
        .table thead th {
            background: #f1f3f6;
            color: #222;
            font-weight: 700;
            border: none;
            white-space: nowrap;
        }
        .table td, .table th {
            vertical-align: middle;
            white-space: nowrap;
        }
        .btn-custom {
            border-radius: 20px;
            transition: all 0.2s;
        }
        .btn-custom:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 4px 16px rgba(142,197,252,0.2);
        }
        .dataTables_filter input {
            border-radius: 20px !important;
        }
        .column-filters {
            background: #f6f8fc;
            border-radius: 12px;
            padding: 12px 18px;
            margin-bottom: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px 24px;
            justify-content: flex-start;
        }
        .column-filters .form-check {
            min-width: 150px;
        }
        @media (max-width: 768px) {
            .main-card {
                padding: 12px 2px;
            }
            .column-filters .form-check {
                min-width: 120px;
            }
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px);}
            to { opacity: 1; transform: translateY(0);}
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card animate__animated animate__fadeInDown">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="trangchu.php" class="btn btn-danger btn-custom">
                    <i class="fas fa-arrow-left"></i> Quay lại
                </a>
                <h1 class="fw-bold mb-0 text-center flex-grow-1">Quản lý người dùng</h1>
                <button id="sendWarningMail" class="btn btn-warning btn-custom">
                    <i class="fas fa-envelope"></i> Gửi Mail cảnh báo tiến độ
                </button>
            </div>
            <div class="column-filters mb-2">
                <?php
                $columns = [
                    "Employee ID", "Full Name", "Birth", "Gender", "Phone", "Email", "Address", "Major", "Hire Date", "Role",
                    "Username", "Password", "Academic Title", "Leadership Position", "Faculty", "Image", "Note", "Created At", "Teacher ID", "Rank Teacher", "Hành động"
                ];
                foreach ($columns as $i => $col) {
                    echo '<div class="form-check form-switch">
                            <input class="form-check-input column-toggle" type="checkbox" data-column="'.$i.'" id="col'.$i.'" checked>
                            <label class="form-check-label small" for="col'.$i.'">'.$col.'</label>
                          </div>';
                }
                ?>
            </div>
            <div class="table-responsive animate__animated animate__fadeInUp">
                <table id="employeeTable" class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <th><?= $col ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td><?= htmlspecialchars($employee['employeeID'] ?? '') ?></td>
                                <td><?= htmlspecialchars($employee['fullName'] ?? '') ?></td>
                                <td><?= htmlspecialchars(formatDate($employee['birth'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($employee['gender'] ?? '') ?></td>
                                <td><?= htmlspecialchars($employee['phone'] ?? '') ?></td>
                                <td><?= htmlspecialchars($employee['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($employee['address'] ?? '') ?></td>
                                <td><?= htmlspecialchars($employee['major'] ?? '') ?></td>
                                <td><?= htmlspecialchars(formatDate($employee['hireDate'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($employee['role'] ?? '') ?></td>
                                <td><?= htmlspecialchars($employee['userName'] ?? '') ?></td>
                                <td><?= htmlspecialchars($employee['password'] ?? '') ?></td>
                                <td><?= htmlspecialchars($employee['academicTitle'] ?? '') ?></td>
                                <td><?= htmlspecialchars($employee['leadershipPosition'] ?? '') ?></td>
                                <td><?= htmlspecialchars($employee['faculty'] ?? '') ?></td>
                                <td><?= htmlspecialchars($employee['image'] ?? '') ?></td>
                                <td><?= htmlspecialchars($employee['note'] ?? '') ?></td>
                                <td><?= htmlspecialchars(formatDate($employee['created_at'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($employee['teacherID'] ?? '') ?></td>
                                <td><?= htmlspecialchars($employee['rankTeacher'] ?? '') ?></td>
                                <td>
                                    <button class="btn btn-danger btn-sm btn-custom delete-btn" data-id="<?= htmlspecialchars($employee['employeeID']) ?>">
                                        <i class="fas fa-trash"></i> Xóa
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#employeeTable').DataTable({
                pageLength: 10,
                responsive: true,
                scrollX: true,
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
                }
            });

            $('.column-toggle').on('change', function() {
                var column = table.column($(this).data('column'));
                column.visible($(this).is(':checked'));
            });

            $(document).on('click', '.delete-btn', function() {
                var employeeID = $(this).data('id');
                var row = $(this).closest('tr');
                if (confirm('Bạn có chắc chắn muốn xóa giảng viên này?')) {
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            action: 'delete',
                            employeeID: employeeID
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                row.addClass('animate__animated animate__fadeOutLeft');
                                setTimeout(function() {
                                    table.row(row).remove().draw();
                                }, 600);
                                setTimeout(function() {
                                    alert('Xóa giảng viên thành công!');
                                }, 650);
                            } else {
                                alert('Lỗi khi xóa: ' + response.error);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Lỗi AJAX xóa:', error);
                            alert('Đã xảy ra lỗi khi xóa giảng viên.');
                        }
                    });
                }
            });

            $('#sendWarningMail').on('click', function() {
                if (confirm('Bạn có chắc chắn muốn gửi mail cảnh báo tiến độ cho tất cả giảng viên?')) {
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            action: 'send_warning_mail'
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                alert('Đã gửi mail cảnh báo!\n' + response.message.join('\n'));
                            } else {
                                alert('Lỗi khi gửi mail: ' + response.error);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Lỗi AJAX gửi mail:', error);
                            alert('Đã xảy ra lỗi khi gửi mail.');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>