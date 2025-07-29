<?php
if (!isset($_SESSION['employeeID'])) {
    ("Location: ../../login/login.php");
    exit();
}

$servername = "localhost:3306";
$username = "root";
$password = "";
$dbname = "qlgv";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$employee_id = $_SESSION['employeeID'];
$total_hours = [
    'b1' => 0,
    'b2_3' => 0,
    'b4_5_6' => 0,
    'total' => 0
];

// Lấy tổng số giờ từ từng section
$sections = ['b1', 'b2_3', 'b4_5_6'];
foreach ($sections as $section) {
    $sql = "SELECT SUM(total_hours) as total FROM task_registrations WHERE employee_id = ? AND section = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $employee_id, $section);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_hours[$section] = $result->fetch_assoc()['total'] ?? 0;
}

$total_hours['total'] = $total_hours['b1'] + $total_hours['b2_3'] + $total_hours['b4_5_6'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tổng hợp số giờ nhiệm vụ khác - Phần B</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
    .main-content {
        flex: 1;
        padding: 20px;
    }

    .research-form {
        padding: 20px;
    }

    .form-title {
        color: #223771;
        font-size: 24px;
        margin-bottom: 20px;
        text-align: center;
        width: 500px;
    }

    .summary-table {
        width: 100%;
        max-width: 600px;
        margin: 0 auto;
        border-collapse: collapse;
        background-color: #fff;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .summary-table th,
    .summary-table td {
        padding: 12px;
        text-align: left;
        border: 1px solid #d2ddfd;
    }

    .summary-table th {
        background-color: #223771;
        color: white;
        font-weight: 500;
    }

    .summary-table td {
        font-size: 14px;
    }

    .total-row {
        font-weight: bold;
        background-color: #f0f4ff;
    }
    </style>
</head>

<body>
    <div class="main-content">
        <div class="research-form">
            <h2 class="form-title">Tổng hợp số giờ nhiệm vụ khác - Phần B</h2>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Mục</th>
                        <th>Tổng số giờ hoàn thành</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Mục 1: Hoạt động phục vụ đào tạo, khoa học, người học, cộng đồng</td>
                        <td><?php echo number_format($total_hours['b1'], 2); ?> giờ</td>
                    </tr>
                    <tr>
                        <td>Mục 2 & 3: Tuyển sinh, truyền thông, học tập, hội họp</td>
                        <td><?php echo number_format($total_hours['b2_3'], 2); ?> giờ</td>
                    </tr>
                    <tr>
                        <td>Mục 4, 5 & 6: Văn thể mỹ, hướng dẫn tập sự, công việc khác</td>
                        <td><?php echo number_format($total_hours['b4_5_6'], 2); ?> giờ</td>
                    </tr>
                    <tr class="total-row">
                        <td>Tổng cộng</td>
                        <td><?php echo number_format($total_hours['total'], 2); ?> giờ</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>