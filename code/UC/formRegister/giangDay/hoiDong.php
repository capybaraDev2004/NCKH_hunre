<?php
// Kiểm tra nếu session chưa được khởi tạo thì mới gọi session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sửa đường dẫn tới file connection.php
require_once __DIR__ . '../../../../connection/connection.php';

if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login/login.php");
    exit();
}

$employeeID = $_SESSION['employeeID'];
$selectedYear = isset($_POST['selected_year']) ? $_POST['selected_year'] : date('Y');

// Thêm vào đầu file sau phần require connection
$stmt = $conn->prepare("SELECT leadershipPosition FROM employee WHERE employeeID = :employeeID");
$stmt->execute([':employeeID' => $employeeID]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
$isQuanNhanDuBi = ($employee['leadershipPosition'] === 'Giảng viên đang là quân nhân dự bị, tự vệ được triệu tập huấn luyện, diễn tập');

// Load dữ liệu từ database dựa trên năm đã chọn
$stmt = $conn->prepare("SELECT * FROM hoi_dong WHERE employeeID = :employeeID AND result_year = :result_year");
$stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
$councilData = $stmt->fetch(PDO::FETCH_ASSOC);

// Nếu không có dữ liệu, khởi tạo mảng trống
if (!$councilData) {
    $councilData = [
        'so_sach' => '',
        'vai_tro' => '',
        'so_gio_ngoai_gio' => '',
        'so_gio_ngoai_truong' => '',
        'khoang_cach' => ''
    ];
}

// Load dữ liệu diễn tập nếu là quân nhân dự bị
if ($isQuanNhanDuBi) {
    $stmt = $conn->prepare("SELECT * FROM dien_tap WHERE employeeID = :employeeID AND result_year = :result_year");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    $trainingData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$trainingData) {
        $trainingData = ['so_ngay' => ''];
    }
}

// Xử lý kiểm tra dữ liệu tồn tại (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'check_data_exists') {
    $year = $_POST['year'] ?? date('Y');
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM hoi_dong WHERE employeeID = :employeeID AND result_year = :result_year");
    $checkStmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
    $exists = $checkStmt->fetchColumn();
    echo json_encode(['exists' => $exists > 0]);
    exit();
}

// AJAX Handling - Must be at the top before any HTML output
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        switch ($_POST['action']) {
            case 'get_council_data':
                $year = $_POST['year'] ?? (date('Y') - 1);
                $stmt = $conn->prepare("SELECT * FROM hoi_dong WHERE employeeID = :employeeID AND result_year = :result_year");
                $stmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$data) {
                    $data = [
                        'so_sach' => '',
                        'vai_tro' => '',
                        'so_gio_ngoai_gio' => '',
                        'so_gio_ngoai_truong' => '',
                        'khoang_cach' => ''
                    ];
                }
                echo json_encode($data);
                exit;

            case 'get_training_data':
                $year = $_POST['year'] ?? (date('Y') - 1);
                $stmt = $conn->prepare("SELECT * FROM dien_tap WHERE employeeID = :employeeID AND result_year = :result_year");
                $stmt->execute([':employeeID' => $employeeID, ':result_year' => $year]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$data) {
                    $data = ['so_ngay' => ''];
                }
                echo json_encode($data);
                exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Xử lý dữ liệu từ form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action']) && isset($_POST['so_sach'])) {
    $tongGioChuan = 0;

    // Lấy dữ liệu từ form
    $so_sach = floatval($_POST['so_sach'] ?? 0);
    $vai_tro = $_POST['vai_tro'] ?? '';
    $so_gio_ngoai_gio = floatval($_POST['so_gio_ngoai_gio'] ?? 0);
    $so_gio_ngoai_truong = floatval($_POST['so_gio_ngoai_truong'] ?? 0);
    $khoang_cach = $_POST['khoang_cach'] ?? '';

    // Tính giờ quy đổi hội đồng
    $tong_gio_quy_doi_hoi_dong = 0;
    switch ($vai_tro) {
        case 'Chủ tịch hội đồng':
            $tong_gio_quy_doi_hoi_dong = $so_sach * 6.0;
            break;
        case 'Phản biện':
            $tong_gio_quy_doi_hoi_dong = $so_sach * 5.5;
            break;
        case 'Thư ký':
            $tong_gio_quy_doi_hoi_dong = $so_sach * 5.0;
            break;
        case 'Ủy viên':
            $tong_gio_quy_doi_hoi_dong = $so_sach * 3.0;
            break;
        default:
            $tong_gio_quy_doi_hoi_dong = 0;
    }

    // Tính giờ quy đổi ngoài giờ (hệ số 1.3)
    $tong_gio_quy_doi_ngoai_gio = $so_gio_ngoai_gio * 1.3;

    // Tính giờ quy đổi ngoài trường
    $tong_gio_quy_doi_ngoai_truong = 0;
    if ($khoang_cach === 'duoi_200km') {
        $tong_gio_quy_doi_ngoai_truong = $so_gio_ngoai_truong * 1.3;
    } elseif ($khoang_cach === 'tu_200km') {
        $tong_gio_quy_doi_ngoai_truong = $so_gio_ngoai_truong * 1.4;
    }

    $tongGioChuan = $tong_gio_quy_doi_hoi_dong + $tong_gio_quy_doi_ngoai_gio + $tong_gio_quy_doi_ngoai_truong;

    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM hoi_dong WHERE employeeID = :employeeID AND result_year = :result_year");
    $checkStmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    $exists = $checkStmt->fetchColumn();

    if ($selectedYear != date('Y') && $selectedYear != date('Y') - 1) {
        echo "<script>showPopup('Không thể cập nhật dữ liệu cho năm $selectedYear vì chỉ được phép chỉnh sửa năm hiện tại hoặc năm hiện tại -1', 'error');</script>";
    } else {
        if ($exists) {
            $stmt = $conn->prepare("
                UPDATE hoi_dong SET
                    so_sach = :so_sach, 
                    tong_gio_quy_doi = :tong_gio_quy_doi, 
                    vai_tro = :vai_tro,
                    so_gio_ngoai_gio = :so_gio_ngoai_gio,
                    so_gio_ngoai_truong = :so_gio_ngoai_truong,
                    khoang_cach = :khoang_cach,
                    ngay_cap_nhat = CURRENT_TIMESTAMP
                WHERE employeeID = :employeeID AND result_year = :result_year
            ");
            $stmt->execute([
                ':employeeID' => $employeeID,
                ':result_year' => $selectedYear,
                ':so_sach' => $so_sach,
                ':tong_gio_quy_doi' => $tongGioChuan,
                ':vai_tro' => $vai_tro,
                ':so_gio_ngoai_gio' => $so_gio_ngoai_gio,
                ':so_gio_ngoai_truong' => $so_gio_ngoai_truong,
                ':khoang_cach' => $khoang_cach
            ]);
            echo "<script>showPopup('Cập nhật dữ liệu thành công cho năm $selectedYear', 'success');</script>";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO hoi_dong (employeeID, result_year, so_sach, tong_gio_quy_doi, vai_tro, so_gio_ngoai_gio, so_gio_ngoai_truong, khoang_cach)
                VALUES (:employeeID, :result_year, :so_sach, :tong_gio_quy_doi, :vai_tro, :so_gio_ngoai_gio, :so_gio_ngoai_truong, :khoang_cach)
            ");
            $stmt->execute([
                ':employeeID' => $employeeID,
                ':result_year' => $selectedYear,
                ':so_sach' => $so_sach,
                ':tong_gio_quy_doi' => $tongGioChuan,
                ':vai_tro' => $vai_tro,
                ':so_gio_ngoai_gio' => $so_gio_ngoai_gio,
                ':so_gio_ngoai_truong' => $so_gio_ngoai_truong,
                ':khoang_cach' => $khoang_cach
            ]);
            echo "<script>showPopup('Lưu dữ liệu thành công cho năm $selectedYear', 'success');</script>";
        }
    }

    // Xử lý dữ liệu diễn tập nếu là quân nhân dự bị
    if ($isQuanNhanDuBi) {
        $so_ngay = floatval($_POST['so_ngay'] ?? 0);
        
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM dien_tap WHERE employeeID = :employeeID AND result_year = :result_year");
        $checkStmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
        $exists = $checkStmt->fetchColumn();

        if ($exists) {
            $stmt = $conn->prepare("
                UPDATE dien_tap SET
                    so_ngay = :so_ngay,
                    ngay_cap_nhat = CURRENT_TIMESTAMP
                WHERE employeeID = :employeeID AND result_year = :result_year
            ");
        } else {
            $stmt = $conn->prepare("
                INSERT INTO dien_tap (employeeID, result_year, so_ngay)
                VALUES (:employeeID, :result_year, :so_ngay)
            ");
        }
        $stmt->execute([
            ':employeeID' => $employeeID,
            ':result_year' => $selectedYear,
            ':so_ngay' => $so_ngay
        ]);
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hội đồng</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; }
        .teaching-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        .teaching-table th, .teaching-table td { border: 1px solid #d2ddfd; padding: 10px; text-align: left; vertical-align: top; }
        .teaching-table th { background-color: #223771; color: white; text-align: center; }
        .teaching-table td { background-color: #fff; }
        .teaching-table td:first-child { width: 5%; text-align: center; }
        .teaching-table td:nth-child(2) { width: 20%; }
        .teaching-table td:nth-child(3) { width: 15%; text-align: center; }
        .teaching-table td:nth-child(4) { width: 15%; text-align: center; }
        .teaching-table td:nth-child(5) { width: 25%; text-align: center; }
        .teaching-table td:nth-child(6) { width: 10%; text-align: center; }
        .teaching-table td:nth-child(7) { width: 10%; text-align: center; }
        .form-title { color: #223771; font-size: 24px; margin-bottom: 20px; text-align: center; }
        .total-input { width: 100px; padding: 5px; text-align: center; margin-bottom: 5px; }
        .year-select { padding: 8px; font-size: 14px; border-radius: 4px; margin-bottom: 10px; }
        button {
            background-color: #223771;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #1a2a5b;
        }
        button:active {
            background-color: #142146;
        }
        .role-checkbox { margin-right: 5px; }
        .hoidong a, .NV1{
            color: #f8843d !important;
        }
        .training-days {
            width: 100px;
            padding: 5px;
            text-align: center;
        }
        .converted-hours-training {
            text-align: center;
        }
    </style>
</head>
<body>
    <h2 class="form-title">Hội đồng</h2>
    <div style="text-align: center; margin-bottom: 15px;">
        <form method="POST" action="" id="yearForm">
            <label for="selected_year">Năm lưu dữ liệu: </label>
            <select name="selected_year" id="selectedYear" class="year-select" onchange="this.form.submit()">
                <?php
                $currentYear = date('Y');
                for ($i = $currentYear - 5; $i <= $currentYear; $i++) {
                    echo "<option value='$i'" . ($i == $selectedYear ? " selected" : "") . ">$i</option>";
                }
                ?>
            </select>
        </form>
    </div>

    <form id="councilForm" method="POST" action="" onsubmit="return handleSubmit(event)">
        <input type="hidden" name="selected_year" value="<?php echo $selectedYear; ?>">
        <table class="teaching-table">
            <thead>
                <tr>
                    <th>TT</th>
                    <th>Công việc</th>
                    <th>Định mức lao động</th>
                    <th>Giờ chuẩn</th>
                    <th>Chức vụ</th>
                    <th>Tổng số sách/giờ</th>
                    <th>Tổng giờ quy đổi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>Hội đồng</td>
                    <td>Cho 1 sách</td>
                    <td class="standard-hour" data-hour-chu-tich="6.0" data-hour-phan-bien="5.5" data-hour-thu-ky="5.0" data-hour-uy-vien="3.0">
                        <span id="standard-hour-display">0.0 gc</span>
                    </td>
                    <td>
                        <label><input type="radio" name="vai_tro" value="Chủ tịch hội đồng" class="role-radio" <?php echo $councilData['vai_tro'] === 'Chủ tịch hội đồng' ? 'checked' : ''; ?>> Chủ tịch hội đồng</label><br>
                        <label><input type="radio" name="vai_tro" value="Phản biện" class="role-radio" <?php echo $councilData['vai_tro'] === 'Phản biện' ? 'checked' : ''; ?>> Phản biện</label><br>
                        <label><input type="radio" name="vai_tro" value="Thư ký" class="role-radio" <?php echo $councilData['vai_tro'] === 'Thư ký' ? 'checked' : ''; ?>> Thư ký</label><br>
                        <label><input type="radio" name="vai_tro" value="Ủy viên" class="role-radio" <?php echo $councilData['vai_tro'] === 'Ủy viên' ? 'checked' : ''; ?>> Ủy viên</label>
                    </td>
                    <td><input type="number" name="so_sach" class="total-input council-input" min="0" step="1" placeholder="Tổng sách" value="<?php echo htmlspecialchars($councilData['so_sach']); ?>"></td>
                    <td class="converted-hours" data-type="hoi-dong">0 gc</td>
                </tr>
                <tr>
                    <td colspan="7" style="font-size: 20px; font-weight: bold;">Hệ số giảng dạy ngoài giờ</td>
                </tr
                <tr>
                    <td>2</td>
                    <td>Dạy, coi, chấm thi</td>
                    <td>Cho 1 giờ</td>
                    <td class="standard-hour-ngoai-gio">1.3</td>
                    <td></td>
                    <td><input type="number" name="so_gio_ngoai_gio" class="total-input ngoai-gio-input" min="0" step="1" placeholder="Tổng giờ" value="<?php echo htmlspecialchars($councilData['so_gio_ngoai_gio']); ?>"></td>
                    <td class="converted-hours" data-type="ngoai-gio">0 gc</td>
                </tr>
                <tr>
                    <td>3</td>
                    <td>Các lớp tổ chức ngoài trường</td>
                    <td>Cho 1 giờ</td>
                    <td class="standard-hour-ngoai-truong" data-hour-duoi-200="1.3" data-hour-tu-200="1.4">
                        <span id="standard-hour-ngoai-truong-display">0.0 gc</span>
                    </td>
                    <td>
                        <label><input type="radio" name="khoang_cach" value="duoi_200km" class="distance-radio" <?php echo $councilData['khoang_cach'] === 'duoi_200km' ? 'checked' : ''; ?>> Nếu dưới 200 km</label><br>
                        <label><input type="radio" name="khoang_cach" value="tu_200km" class="distance-radio" <?php echo $councilData['khoang_cach'] === 'tu_200km' ? 'checked' : ''; ?>> Từ 200 km trở lên</label>
                    </td>
                    <td><input type="number" name="so_gio_ngoai_truong" class="total-input ngoai-truong-input" min="0" step="1" placeholder="Tổng giờ" value="<?php echo htmlspecialchars($councilData['so_gio_ngoai_truong']); ?>"></td>
                    <td class="converted-hours" data-type="ngoai-truong">0 gc</td>
                </tr>
            </tbody>
        </table>
        <div style="text-align: center; margin-top: 20px;">
            <button type="submit">Cập nhật</button>
            <p>Tổng số giờ chuẩn hiện tại: <strong id="tongGioChuan">0 gc</strong></p>
        </div>
    </form>

    <?php if ($isQuanNhanDuBi): ?>
    <div style="margin-top: 30px;">
        <table class="teaching-table">
            <thead>
                <tr>
                    <th colspan="4" style="text-align: center; font-size: 18px;">Số ngày diễn tập</th>
                </tr>
                <tr>
                    <th>TT</th>
                    <th>Công việc</th>
                    <th>Số ngày</th>
                    <th>Giờ quy đổi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>Số ngày tham gia huấn luyện, diễn tập (1 ngày = 2.5 giờ chuẩn)</td>
                    <td><input type="number" name="so_ngay" class="total-input training-days" min="0" step="0.5" placeholder="Số ngày" value="<?php echo htmlspecialchars($trainingData['so_ngay']); ?>"></td>
                    <td class="converted-hours-training">0 gc</td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <script>
        // Khai báo các biến DOM một cách an toàn
        const councilInput = document.querySelector('.council-input');
        const roleRadios = document.querySelectorAll('.role-radio');
        const convertedHoursCells = document.querySelectorAll('.converted-hours');
        const standardHourCell = document.querySelector('.standard-hour');
        const standardHourDisplay = document.getElementById('standard-hour-display');
        const ngoaiGioInput = document.querySelector('.ngoai-gio-input');
        const ngoaiTruongInput = document.querySelector('.ngoai-truong-input');
        const distanceRadios = document.querySelectorAll('.distance-radio');
        const standardHourNgoaiTruongCell = document.querySelector('.standard-hour-ngoai-truong');
        const standardHourNgoaiTruongDisplay = document.getElementById('standard-hour-ngoai-truong-display');
        const trainingDaysInput = document.querySelector('.training-days');
        const convertedHoursTraining = document.querySelector('.converted-hours-training');
        const tongGioChuanElement = document.getElementById('tongGioChuan');

        function calculateConvertedHours() {
            // Tính giờ quy đổi hội đồng
            const tasks = parseFloat(councilInput?.value) || 0;
            let standardHour = 0;
            let selectedRole = '';

            roleRadios.forEach(radio => {
                if (radio.checked) {
                    selectedRole = radio.value;
                }
            });

            switch (selectedRole) {
                case 'Chủ tịch hội đồng':
                    standardHour = 6.0;
                    break;
                case 'Phản biện':
                    standardHour = 5.5;
                    break;
                case 'Thư ký':
                    standardHour = 5.0;
                    break;
                case 'Ủy viên':
                    standardHour = 3.0;
                    break;
                default:
                    standardHour = 0;
            }

            if (standardHourDisplay) {
                standardHourDisplay.textContent = standardHour.toFixed(1) + ' gc';
            }

            const hoiDongResult = tasks * standardHour;
            if (convertedHoursCells[0]) {
                convertedHoursCells[0].textContent = hoiDongResult.toFixed(2) + ' gc';
            }

            // Tính giờ quy đổi ngoài giờ
            const ngoaiGio = parseFloat(ngoaiGioInput?.value) || 0;
            const ngoaiGioResult = ngoaiGio * 1.3;
            if (convertedHoursCells[1]) {
                convertedHoursCells[1].textContent = ngoaiGioResult.toFixed(2) + ' gc';
            }

            // Tính giờ quy đổi ngoài trường
            const ngoaiTruong = parseFloat(ngoaiTruongInput?.value) || 0;
            let standardHourNgoaiTruong = 0;
            let selectedDistance = '';

            distanceRadios.forEach(radio => {
                if (radio.checked) {
                    selectedDistance = radio.value;
                }
            });

            if (selectedDistance === 'duoi_200km') {
                standardHourNgoaiTruong = 1.3;
            } else if (selectedDistance === 'tu_200km') {
                standardHourNgoaiTruong = 1.4;
            }

            if (standardHourNgoaiTruongDisplay) {
                standardHourNgoaiTruongDisplay.textContent = standardHourNgoaiTruong.toFixed(1) + ' gc';
            }

            const ngoaiTruongResult = ngoaiTruong * standardHourNgoaiTruong;
            if (convertedHoursCells[2]) {
                convertedHoursCells[2].textContent = ngoaiTruongResult.toFixed(2) + ' gc';
            }

            updateTongGioChuan();
        }

        function calculateTrainingHours() {
            if (!trainingDaysInput || !convertedHoursTraining) return;
            
            const days = parseFloat(trainingDaysInput.value) || 0;
            const hours = days * 2.5;
            convertedHoursTraining.textContent = hours.toFixed(2) + ' gc';
            updateTongGioChuan();
        }

        function updateTongGioChuan() {
            let tongGio = 0;

            // Chỉ tính tổng giờ từ các ô converted-hours của hội đồng
            convertedHoursCells.forEach(cell => {
                const gio = parseFloat(cell?.textContent?.replace(' gc', '')) || 0;
                tongGio += gio;
            });

            // Cập nhật tổng giờ
            if (tongGioChuanElement) {
                tongGioChuanElement.textContent = tongGio.toFixed(2) + ' gc';
            }
        }

        // Thêm event listeners một cách an toàn
        document.addEventListener('DOMContentLoaded', function() {
            if (councilInput) {
                councilInput.addEventListener('input', calculateConvertedHours);
            }

            roleRadios.forEach(radio => {
                radio.addEventListener('change', calculateConvertedHours);
            });

            if (ngoaiGioInput) {
                ngoaiGioInput.addEventListener('input', calculateConvertedHours);
            }

            if (ngoaiTruongInput) {
                ngoaiTruongInput.addEventListener('input', calculateConvertedHours);
            }

            distanceRadios.forEach(radio => {
                radio.addEventListener('change', calculateConvertedHours);
            });

            if (trainingDaysInput) {
                trainingDaysInput.addEventListener('input', calculateTrainingHours);
            }

            // Tính toán ban đầu cho tất cả các giá trị
            calculateConvertedHours();
            calculateTrainingHours();
        });

        // Thêm hàm handleSubmit để xử lý form submit
        function handleSubmit(event) {
            event.preventDefault();
            const selectedYear = document.getElementById('selectedYear').value;
            const currentYear = new Date().getFullYear();
            const form = document.getElementById('councilForm');
            const formData = new FormData(form);

            // Thêm dữ liệu diễn tập vào formData nếu có
            const trainingDaysInput = document.querySelector('.training-days');
            if (trainingDaysInput) {
                formData.append('so_ngay', trainingDaysInput.value);
            }

            if (selectedYear != currentYear && selectedYear != currentYear - 1) {
                showPopup(`Không thể cập nhật dữ liệu cho năm ${selectedYear} vì chỉ được phép chỉnh sửa năm hiện tại hoặc năm hiện tại -1`, 'error');
            } else {
                submitFormData(formData, selectedYear);
            }
            return false;
        }

        // Thêm hàm submitFormData để xử lý AJAX
        function submitFormData(formData, selectedYear) {
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('Cập nhật dữ liệu thành công') || data.includes('Lưu dữ liệu thành công')) {
                    showPopup(`Dữ liệu đã được cập nhật thành công cho năm ${selectedYear}`, 'success');
                } else if (data.includes('Không thể cập nhật dữ liệu')) {
                    showPopup(`Không thể cập nhật dữ liệu cho năm ${selectedYear}`, 'error');
                } else {
                    showPopup('Có lỗi xảy ra khi cập nhật dữ liệu!', 'error');
                }
            })
            .catch(error => {
                showPopup('Lỗi kết nối server: ' + error, 'error');
            });
        }
    </script>
</body>
</html>