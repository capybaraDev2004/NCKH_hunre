<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <link rel="stylesheet" href="../assets/css/style.css"> -->
    <title>✨TKB giảng viên khoa công nghệ thông tin đại học tài nguyên và môi trường hà nội</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-giJF6kkoqNQ00vy+HMDP7azOuL0xtbfIcaT9wjKHr8RbDVddVHyTfAAsrekwKmP1" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ygbV9kiqUc6oa4msXn9868pTtWMgiQaeYH7/t7LECLbyPA2x65Kgf80OJFdroafW"
        crossorigin="anonymous"></script>
    <link rel="stylesheet" href="../../assets/css/index.css">
    <link rel="stylesheet" href="../../assets/css/style.css">

    <!-- Load the plugin bundle. -->
    <script src="plugins/filter-table/excel-bootstrap-table-filter-bundle.js"></script>
    <link rel="stylesheet" href="plugins/filter-table/excel-bootstrap-table-filter-style.css" />
</head>

<body>
    <?php
    require '../../connection/connection.php';

    // Khởi động session nếu chưa được khởi động
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Kiểm tra đăng nhập
    if (!isset($_SESSION['employeeID'])) {
        header("Location: ../../login/login.php");
        exit();
    }

    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Giảng viên', 'Chuyển viên'])) {
        // Nếu vai trò không phải là "Giảng viên", hiển thị thông báo và chuyển hướng về trang chủ
        echo "<script>
                alert('Bạn không có quyền truy cập trang này! Chỉ có vai trò \"Giảng viên\" hoặc \"Chuyên viên\" mới được phép truy cập.');
                window.location.href = '../../trangchu.php';
              </script>";
        exit();
    }

    // Lấy thông tin fullName từ session
    $fullName = $_SESSION['fullName'];

    // Lấy đường dẫn ảnh đại diện giống như trangchu.php
    try {
        $employeeID = $_SESSION['employeeID'];
        $stmt = $conn->prepare("SELECT image FROM employee WHERE employeeID = :employeeID");
        $stmt->bindParam(':employeeID', $employeeID, PDO::PARAM_INT);
        $stmt->execute();
        $employeeData = $stmt->fetch(PDO::FETCH_ASSOC);

        $uploadDir = '../../UC/assets/uploads/';
        $defaultImage = '../../assets/images/avatar-default.png';

        $imageName = $employeeID . '.jpg';
        $imagePath = $uploadDir . $imageName;

        if (file_exists($imagePath)) {
            $avatarSrc = $imagePath;
        } else {
            $avatarSrc = $defaultImage;
        }
    } catch (Exception $e) {
        $avatarSrc = '../../assets/images/avatar-default.png';
    }
    ?>
    <header class="header">
        <nav class="nav">
            <ul class="nav-list">
                <li class="nav-item"><a href="../../trangchu.php" class="nav-link">Trang chủ</a></li>
                <li class="nav-item"><a href="../formRegister/formRegister.php" class="nav-link">Thống kê giảng dạy</a></li>
                <li class="nav-item"><a href="#" class="nav-link">Thời khóa biểu</a></li>
                <li class="nav-item"><a href="#" class="nav-link">Báo cáo</a></li>
            </ul>
            <div class="account-settings">
                <span class="account-name"><?php echo htmlspecialchars($fullName); ?></span>
                <div class="user-avatar">
                    <img id="avatar-preview" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Avatar" style="width:30px; height:30px; border-radius:50%;">
                </div>
                <div class="account-menu">
                    <a href="../../login/logout.php" class="account-menu-item">Đăng xuất</a>
                </div>
            </div>
        </nav>
    </header>



    <!----END-INFO-END----->

    <!----navbar-fixed-top-------->
    <div class="fixed-top collapse mb-5" id="navbarToggleExternalContent">
        <div class="p-4">
            <!-- Dạng danh sách -->
            <div class="container mt-5">
                <a class="btn btn-warning btn-sm mb-2" data-bs-toggle="collapse" href="#show-list-danhsach" role="button"
                    aria-expanded="true" aria-controls="ListLoc">
                    📜Ẩn/Hiện
                </a>
                <b>👉Danh sách lớp học đã chọn</b></br>
                <div class="collapse show" id="show-list-danhsach">
                    <div class="row">
                        <div class="col-6" style="border-top: solid; border-left: solid;">
                            <!--List  tên môn học-->
                            <b>Tên môn học:</b>
                            <div class="list-group" id="danhsach-ten-selected" role="tablist">
                                <!-- <a class="list-group-item list-group-item-action" data-bs-toggle="list" href="#list-home" role="tab">Demo1</a>
									<a class="list-group-item list-group-item-action" data-bs-toggle="list" href="#list-profile" role="tab">Demo2</a>
									<a class="list-group-item list-group-item-action" data-bs-toggle="list" href="#list-messages" role="tab">Demo3</a>
									<a class="list-group-item list-group-item-action" data-bs-toggle="list" href="#list-settings" role="tab">Demo4</a> -->

                            </div>
                        </div>

                        <div class="col-6" style="border-right: solid; border-left: solid; border-top: solid;">
                            <b>Thông tin môn học:</b>
                            <div class="tab-content" id="danhsach-info-selected">
                                <!-- <p id="status-text-info"></p> -->
                                <!-- <div class="tab-pane fade" id="list-home" role="tabpanel">Demo1</br>Demo1</br>Demo1</br>Demo1</div>
									<div class="tab-pane fade" id="list-profile" role="tabpanel" >Demo2</br>Demo2</br>Demo2</br>Demo2</div>
									<div class="tab-pane fade" id="list-messages" role="tabpanel">Demo3</br>Demo3</br>Demo3</br>Demo3</div>
									<div class="tab-pane fade" id="list-settings" role="tabpanel">Demo4</br>Demo3</br>Demo3</br>Demo3</div> -->

                            </div>
                        </div>
                        <div class="col-6 ml-1" style="border-bottom: solid; border-left: solid; border-top: solid;">
                            <b>Danh sách mã lớp hiện tại:</b>
                            <button id="btnCopy" type="button" class="btn btn-primary btn-sm">copy</button>
                            <div id="danhsach-malop-selected">

                            </div>
                        </div>
                        <div class="col-6 ml-1" style="border-style: solid">
                            <b>Tổng số tín chỉ :</b>
                            <div id="tongTC-selected">

                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-------------------->

            <!-- Dạng bảng -->
            <div class="container mt-2">
                <a class="btn btn-warning btn-sm mb-2 " data-bs-toggle="collapse" href="#box-table-tkbhp" role="button"
                    aria-expanded="true" aria-controls="ListLoc">
                    🎇Ẩn/Hiện
                </a>
                <b>👉Xem dưới dạng bảng thời khóa biểu</b></br>
            </div>
            <div class="collapse container" id="box-table-tkbhp">
                <table id="table-tkbhp" class="sticky-enabled table-tkbhp table-bordered table-striped"
                    style="font-size:0.6em;">
                    <thead class="head-table-tkbhp">
                        <tr>
                            <th name="cell-thutiet">Thứ / Tiết</th>
                            <th name="cell-thu">Thứ 2</th>
                            <th name="cell-thu">Thứ 3</th>
                            <th name="cell-thu">Thứ 4</th>
                            <th name="cell-thu">Thứ 5</th>
                            <th name="cell-thu">Thứ 6</th>
                            <th name="cell-thu">Thứ 7</th>
                        </tr>
                    </thead>
                    <tbody class="body-table-tkbhp">

                    </tbody>
                </table>
            </div>
            <!-------------------->
        </div>
    </div>
    </div>
    <!--------END-NAVBAR-END-------->

    <!-----BODY----->
    <div name="container-body">
        <div>
            <div>
                <h1 style="margin-top: 20px; text-align: center; align-items: center;">HỌC PHẦN GIẢNG DẠY</h1>
                <p>
                    <a class="btn btn-primary btn-sm mt-2" data-bs-toggle="collapse" href="#ListLoc" role="button"
                        aria-expanded="true" aria-controls="ListLoc" style="margin-left :2 0px;">
                        👉 Ẩn/hiện lọc:
                    </a>
                </p>
                <div class="collapse show" id="ListLoc">
                    <div class="card card-body">
                        <!--list lọc-->
                        <div class="btn-group-sm" role="group" aria-label="Basic checkbox toggle button group">
                            <label class="form-check-label" for="STT">
                                <input type="checkbox" class="form-check-input column-toggle" id="STT" data-column="cell-STT" checked> STT
                            </label>

                            <label class="form-check-label" for="TenLHP">
                                <input type="checkbox" class="form-check-input column-toggle" id="TenLHP" data-column="cell-TenMH" checked> Tên lớp học phần
                            </label>

                            <label class="form-check-label" for="Lop">
                                <input type="checkbox" class="form-check-input column-toggle" id="Lop" data-column="cell-Lop" checked> Lớp
                            </label>

                            <label class="form-check-label" for="SoTc">
                                <input type="checkbox" class="form-check-input column-toggle" id="SoTc" data-column="cell-SoTc" checked> Số tín chỉ
                            </label>

                            <label class="form-check-label" for="HTH">
                                <input type="checkbox" class="form-check-input column-toggle" id="HTH" data-column="cell-HTH" checked> Hình thức học
                            </label>

                            <label class="form-check-label" for="LT">
                                <input type="checkbox" class="form-check-input column-toggle" id="LT" data-column="cell-LT" checked> LT
                            </label>

                            <label class="form-check-label" for="TH">
                                <input type="checkbox" class="form-check-input column-toggle" id="TH" data-column="cell-TH" checked> TH
                            </label>

                            <label class="form-check-label" for="soTuan">
                                <input type="checkbox" class="form-check-input column-toggle" id="soTuan" data-column="cell-sotuan" checked> Số tuần
                            </label>

                            <label class="form-check-label" for="TG">
                                <input type="checkbox" class="form-check-input column-toggle" id="TG" data-column="cell-TG" checked> Thời gian
                            </label>

                            <label class="form-check-label" for="TenGV">
                                <input type="checkbox" class="form-check-input column-toggle" id="TenGV" data-column="cell-TenGV" checked> Giảng viên giảng dạy
                            </label>

                            <label class="form-check-label" for="GhiChu">
                                <input type="checkbox" class="form-check-input column-toggle" id="GhiChu" data-column="cell-GhiChu"> Ghi chú
                            </label>
                        </div>
                        <!----------->
                    </div>
                </div>
            </div>
            <!-- END-Phần lọc-END -->
        </div>
        <!----------------------->
        <hr>
        <!--Phần table select --->
        <div>
            <table id="main-table" id="table" class="table table-bordered table-hover mt-3 container">
                <thead id="main-table-head">
                    <!-- HEAD name COL -->
                    <tr id="cur-name-header-table" class="header_table" name="cell-Chon">
                        <th name="cell-STT">STT</th>
                        <th name="cell-TenMH" class="apply-filter">Tên lớp học phần</th>
                        <th name="cell-Lop">Lớp</th>
                        <th name="cell-SoTc" class="apply-filter">Số tín chỉ</th>
                        <th name="cell-HTH" class="apply-filter">Hình thức học</th>
                        <th name="cell-LT" class="apply-filter">LT</th>
                        <th name="cell-TH" class="apply-filter">TH</th>
                        <th name="cell-sotuan" class="apply-filter">Số tuần</th>
                        <th name="cell-TG">Thời gian</th>
                        <th name="cell-TenGV" class="apply-filter">Giảng viên giảng dạy</th>
                        <!-- <th colspan="5">Hệ số</th>
                            <th rowspan="2">Quy đổi giờ chuẩn</th>
                            <th rowspan="2">Tổng</th> -->
                        <th name="cell-GhiChu" class="apply-filter">Ghi chú</th>
                    </tr>
                    <!------------->
                </thead>

                <tbody id="main-table-body">
                    <?php
                    try {
                        // Tính tổng của tất cả LT và TH
                        $stmt = $conn->prepare("SELECT 
                                                   SUM(LT) as total_LT,
                                                   SUM(TH) as total_TH
                                               FROM schedule 
                                               WHERE teacher_name LIKE :teacher_name");
                        $stmt->execute(['teacher_name' => "%$fullName%"]);
                        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Tính tổng period (tổng của LT + tổng của TH)
                        $totalPeriod = $totals['total_LT'] + $totals['total_TH'];
                        
                        // Cập nhật total_period cho tất cả các bản ghi của giảng viên này
                        $updateStmt = $conn->prepare("UPDATE schedule 
                                                     SET total_period = :total_period 
                                                     WHERE teacher_name LIKE :teacher_name");
                        $updateStmt->execute([
                            'total_period' => $totalPeriod,
                            'teacher_name' => "%$fullName%"
                        ]);

                        // Tiếp tục với phần hiển thị dữ liệu như bình thường
                        $stmt = $conn->prepare("SELECT * FROM schedule WHERE teacher_name LIKE :teacher_name");
                        $stmt->execute(['teacher_name' => "%$fullName%"]);
                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Kiểm tra nếu có dữ liệu
                        if (count($result) > 0) {
                            $stt = 1;
                            foreach ($result as $row) {
                                echo "<tr>
                                    <td name='cell-STT'>{$stt}</td>
                                    <td name='cell-TenMH'>{$row['subject_name']}</td>
                                    <td name='cell-Lop'>{$row['class']}</td>
                                    <td name='cell-SoTc'>{$row['credits']}</td>
                                    <td name='cell-HTH'>{$row['study_type']}</td>
                                    <td name='cell-LT'>{$row['LT']}</td>
                                    <td name='cell-TH'>{$row['TH']}</td>
                                    <td name='cell-sotuan'>{$row['Number_of_weeks']}</td>
                                    <td name='cell-TG'>{$row['time']}</td>
                                    <td name='cell-TenGV'>{$row['teacher_name']}</td>
                                    <td name='cell-GhiChu'>{$row['note']}</td>
                                </tr>";
                                $stt++;
                            }
                        } else {
                            echo "<tr><td colspan='12' style='text-align:center;'>Không có dữ liệu</td></tr>";
                        }
                    } catch (PDOException $e) {
                        echo "Lỗi truy vấn: " . $e->getMessage();
                    }
                    ?>

                </tbody>
            </table>
        </div>
        <!----------------------->
    </div>
    <!-- END-BODY-END -->
    <div class="end-body-end">
        <!-- Form upload Excel -->
        <div class="uploadExcel">
            <form action="upload_schedule.php" method="POST" enctype="multipart/form-data">
                <label for="file">Chọn file Excel:</label>
                <input type="file" name="file" id="file" accept=".xlsx" required>
                <button type="submit">Tải lên</button>
            </form>
        </div>

        <!-- Container cho các nút utility -->
        <div class="utility-buttons">
            <a href="https://www.adobe.com/vn_vi/acrobat/online/pdf-to-excel.html" class="pdf-to-excel-btn" style="color: #fff;">
                📄 Chuyển PDF sang Excel
            </a>
            <form method="POST" id="deleteForm" action="delete_all.php" method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" onclick="return confirm('Bạn có chắc muốn xóa tất cả dữ liệu?');" class="btn btn-danger">
                    🗑 Xóa toàn bộ dữ liệu
                </button>
            </form>
        </div>
    </div>


    <script src="../../assets/js/index.js"></script>

    </main>
</body>

</html>