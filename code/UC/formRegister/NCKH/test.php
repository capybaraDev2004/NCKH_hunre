<?php
// Khởi động session
session_start();

// Kiểm tra session
if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../../login/login.php");
    exit();
}

// Kiểm tra và yêu cầu tệp kết nối
$connectionFile = __DIR__ . '/../../../connection/connection.php';
if (!file_exists($connectionFile)) {
    die("Lỗi: Không tìm thấy tệp connection.php tại " . $connectionFile);
}
require_once $connectionFile;

$employeeID = $_SESSION['employeeID'];
$selectedYear = isset($_POST['selected_year']) ? $_POST['selected_year'] : (date('Y') - 1);

// Tính tổng giờ NCKH từ 4 bảng lịch sử
$totalHours = 0;
try {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(gio_quy_doi), 0) as total FROM nckhcc_history WHERE employeeID = :employeeID AND result_year = :result_year");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    $totalHours += $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(gio_quy_doi), 0) as total FROM bai_bao_history WHERE employeeID = :employeeID AND result_year = :result_year");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    $totalHours += $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(gio_quy_doi), 0) as total FROM huongdansv_history WHERE employeeID = :employeeID AND result_year = :result_year");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    $totalHours += $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(gio_quy_doi), 0) as total FROM vietsach_history WHERE employeeID = :employeeID AND result_year = :result_year");
    $stmt->execute([':employeeID' => $employeeID, ':result_year' => $selectedYear]);
    $totalHours += $stmt->fetchColumn();
} catch (PDOException $e) {
    die("Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tìm kiếm trong Select</title>
    <style>
        .select-container {
            width: 300px;
            margin: 20px auto;
            font-family: Arial, sans-serif;
            position: relative;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        select {
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
        select option {
            padding: 5px;
        }
        select:focus {
            outline: none;
        }
        .total-hours-display {
            text-align: center;
            margin: 20px auto;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #e6f3ff;
            max-width: 600px;
            font-family: Arial, sans-serif;
        }
        .total-hours-display p {
            margin: 10px 0;
            font-size: 18px;
            color: #223771;
        }
        .total-hours-display p strong {
            color: #f8843d;
        }
    </style>
</head>
<body>
    <div class="select-container">
        <input type="text" id="search" placeholder="Nhập từ khóa để tìm..." onkeyup="filterOptions()" onclick="showOptions()">
        <select id="options" size="5" onchange="selectOption()">
            <option value="Muc1">Mục 1: Apple</option>
            <option value="Muc2">Mục 2: Banana</option>
            <option value="Muc3">Mục 3: Orange</option>
            <option value="Muc4">Mục 4: Mango</option>
            <option value="Muc5">Mục 5: Pineapple</option>
        </select>
    </div>

    <!-- Hiển thị tổng giờ NCKH -->
    <div class="total-hours-display">
        <p><strong>Tổng giờ NCKH (năm <?php echo htmlspecialchars($selectedYear); ?>):</strong> <?php echo number_format($totalHours, 2); ?> giờ</p>
    </div>

    <script>
        function filterOptions() {
            let input = document.getElementById('search').value.toLowerCase();
            let select = document.getElementById('options');
            let options = select.options;

            // Hiển thị select khi có nhập liệu
            select.style.display = input.length > 0 ? 'block' : 'none';

            // Lọc các tùy chọn
            for (let i = 0; i < options.length; i++) {
                let text = options[i].text.toLowerCase();
                options[i].style.display = text.includes(input) ? '' : 'none';
            }
        }

        function showOptions() {
            let select = document.getElementById('options');
            let input = document.getElementById('search').value;
            // Hiển thị tất cả tùy chọn khi nhấp vào input nếu chưa nhập gì
            select.style.display = 'block';
            if (!input) {
                for (let i = 0; i < select.options.length; i++) {
                    select.options[i].style.display = '';
                }
            }
        }

        function selectOption() {
            let select = document.getElementById('options');
            let input = document.getElementById('search');
            // Cập nhật giá trị input khi chọn một mục
            if (select.selectedIndex !== -1) {
                input.value = select.options[select.selectedIndex].text;
            }
            // Ẩn select sau khi chọn
            select.style.display = 'none';
        }

        // Ẩn select khi nhấp ra ngoài
        document.addEventListener('click', function(event) {
            let container = document.querySelector('.select-container');
            if (!container.contains(event.target)) {
                document.getElementById('options').style.display = 'none';
            }
        });
    </script>
</body>
</html>