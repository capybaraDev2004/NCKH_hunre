<?php
session_start();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo Nghiên cứu khoa học</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center mb-4">BÁO CÁO NGHIÊN CỨU KHOA HỌC</h2>
        <form action="export_nckh.php" method="POST" class="border p-4 rounded shadow">
            <input type="hidden" name="taskType" value="nckh">
            
            <div class="mb-3">
                <label for="year" class="form-label">Chọn năm:</label>
                <select class="form-select" id="year" name="year" required>
                    <?php
                    $currentYear = date("Y");
                    for ($i = $currentYear; $i >= $currentYear - 10; $i--) {
                        echo "<option value='$i'>$i</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="filename" class="form-label">Tên file báo cáo:</label>
                <input type="text" class="form-control" id="filename" name="filename" placeholder="Nhập tên file (ví dụ: BaoCaoNCKH)" required>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-primary">Xuất báo cáo</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>