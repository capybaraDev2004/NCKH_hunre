<?php
session_start();

// Đảm bảo thư viện PhpSpreadsheet được tải
try {
    require_once '..\..\..\vendor\autoload.php';
} catch (Exception $e) {
    die('Lỗi: Không thể tải thư viện PhpSpreadsheet. Vui lòng kiểm tra đường dẫn hoặc cài đặt thư viện: ' . $e->getMessage());
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['taskType']) && $_POST['taskType'] === 'nhiemvukhac') {
    $year = $_POST['year'] ?? '2023';
    $filename = $_POST['filename'] ?? 'ThongKeNhiemVuChuyenMonKhac';
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '', $filename) . '.xlsx'; // Sanitize filename

    try {
        // Khởi tạo Spreadsheet
        $spreadsheet = new Spreadsheet();

        // Sheet 1: Bản kê của giảng viên
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Bản kê của giảng viên');

        // Tiêu đề chính cho Sheet 1
        $sheet1->mergeCells('A1:G1');
        $sheet1->setCellValue('A1', 'PHỤ LỤC 4: THỐNG KÊ NHIỆM VỤ CHUYÊN MÔN KHÁC NĂM ' . $year);
        $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet1->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Tiêu đề cột cho Sheet 1
        $headers = [
            'Định mức của chức danh',
            'Mã số (sheet 2)',
            'Tên nhiệm vụ',
            'Tỷ lệ tham gia',
            'Giờ quy đổi',
            'Giờ làm NV3',
            'Minh chứng'
        ];
        $sheet1->fromArray($headers, null, 'A3');
        $sheet1->getStyle('A3:G3')->getFont()->setBold(true);
        $sheet1->getStyle('A3:G3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle('A3:G3')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet1->getStyle('A3:G3')->getAlignment()->setWrapText(true);

        // Áp dụng viền đen cho tiêu đề cột
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'], // Màu đen
                ],
            ],
        ];
        $sheet1->getStyle('A3:G3')->applyFromArray($styleArray);

        // Định dạng độ rộng cột cho Sheet 1
        $sheet1->getColumnDimension('A')->setWidth(10);  // Định mức của chức danh
        $sheet1->getColumnDimension('B')->setWidth(10);  // Mã số
        $sheet1->getColumnDimension('C')->setWidth(40);  // Tên nhiệm vụ
        $sheet1->getColumnDimension('D')->setWidth(15);  // Tỷ lệ tham gia
        $sheet1->getColumnDimension('E')->setWidth(10);  // Giờ quy đổi
        $sheet1->getColumnDimension('F')->setWidth(10);  // Giờ làm NV3
        $sheet1->getColumnDimension('G')->setWidth(15);  // Minh chứng

        // Dữ liệu mẫu cho Sheet 1 (hard-coded)
        $dataSheet1 = [
            ['360', '1.5.10', 'Ủy viên', '20%', '0.15', '10.8', '- Minh chứng'],
            ['360', '2.1.6', 'Chủ trì và các thành viên', '20%', '250', '50', '...'],
            ['360', '2.1.8', 'Người biên soạn', '100%', '15', '15', '...'],
            ['360', '2.1.27', 'Tham gia góp ý các dự thảo văn bản của Bộ, Trường, Khoa (khi được yêu cầu).', '700%', '8', '56', '...'],
            ['360', '4.1.6', 'Thực hiện và tham gia seminar chuyên môn cấp khoa.', '19%', '16', '3.04', '...'],
            ['360', '4.1.8', 'Tham gia sinh hoạt chuyên môn bộ môn (cấp khoa)', '1000%', '4', '40', '...'],
            ['360', '4.1.9', 'Họp chi bộ', '1200%', '4', '48', '...'],
            ['360', '7.14.1', 'Rà soát đề cương chi tiết môn tiếng Anh chuyên ngành, đề cương chi tiết các môn học bằng tiếng Anh của các ngành đào tạo trong trường trong xây dựng các chương trình đào tạo', '100%', '2', '2', '...'],
            ['360', '5.2.1', '- Trưởng ban', '600%', '0.1', '216', '...'],
            ['360', '5.3.6', '- Tham gia nhưng không đạt giải', '100%', '0.05', '18', '...'],
            ['360', '7.26.1', 'Thành viên Hội đồng dự giờ', '500%', '2', '10', '...']
        ];

        // Thêm dữ liệu vào Sheet 1
        $rowIndex = 4;
        foreach ($dataSheet1 as $rowData) {
            $sheet1->fromArray($rowData, null, 'A' . $rowIndex);
            $sheet1->getStyle('A' . $rowIndex . ':G' . $rowIndex)->applyFromArray($styleArray);
            $rowIndex++;
        }

        // Tổng thời gian thực hiện nhiệm vụ 3 cho Sheet 1
        $totalRow = $rowIndex + 1;
        $sheet1->mergeCells('A' . $totalRow . ':F' . $totalRow);
        $sheet1->setCellValue('A' . $totalRow, 'Tổng thời gian thực hiện nhiệm vụ 3:');
        $sheet1->getStyle('A' . $totalRow)->getFont()->setBold(true);
        $sheet1->getStyle('A' . $totalRow . ':G' . $totalRow)->applyFromArray($styleArray);

        // Hard-code tổng giờ làm NV3
        $totalHours = 468.84;
        $sheet1->setCellValue('G' . $totalRow, $totalHours);
        $sheet1->getStyle('G' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Chữ ký cho Sheet 1
        $signatureRow = $totalRow + 2;
        $sheet1->mergeCells('A' . $signatureRow . ':C' . $signatureRow);
        $sheet1->mergeCells('E' . $signatureRow . ':G' . $signatureRow);
        $sheet1->setCellValue('A' . $signatureRow, 'NGƯỜI KÊ KHAI');
        $sheet1->setCellValue('E' . $signatureRow, 'LÃNH ĐẠO ĐƠN VỊ');
        $sheet1->getStyle('A' . $signatureRow . ':G' . $signatureRow)->getFont()->setBold(true);
        $sheet1->getStyle('A' . $signatureRow . ':G' . $signatureRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Sheet 2: Danh mục
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Danh mục');

        // Tiêu đề cột cho Sheet 2
        $headersSheet2 = [
            'Mã số',
            'Công việc',
            'Giờ làm việc',
            'Giờ làm việc',
            'Ghi chú'
        ];
        $sheet2->fromArray($headersSheet2, null, 'A1');
        $sheet2->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet2->getStyle('A1:E1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet2->getStyle('A1:E1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet2->getStyle('A1:E1')->getAlignment()->setWrapText(true);
        $sheet2->getStyle('A1:E1')->applyFromArray($styleArray);

        // Định dạng độ rộng cột cho Sheet 2
        $sheet2->getColumnDimension('A')->setWidth(10);  // Mã số
        $sheet2->getColumnDimension('B')->setWidth(50);  // Công việc
        $sheet2->getColumnDimension('C')->setWidth(20);  // Giờ làm việc
        $sheet2->getColumnDimension('D')->setWidth(10);  // Giờ làm việc (cột 2)
        $sheet2->getColumnDimension('E')->setWidth(30);  // Ghi chú

        // Dữ liệu mẫu cho Sheet 2 (hard-coded từ sheet "Danh mục")
        $dataSheet2 = [
            ['I', 'Tham gia các hội đồng, công tác đoàn thể chưa được giảm trừ khối lượng hoặc chưa được hưởng phụ cấp', '', '', ''],
            ['', 'Hội đồng Khoa học và Đào tạo (tính khoán cho cả năm)', '', '', ''],
            ['1.1.1', '- Chủ tịch, thư ký Hội đồng', 'Hoàn thành 15% khối lượng NV3', '0.15', 'Theo QĐ thành lập Hội đồng'],
            ['1.1.2', '- Ủy viên Hội đồng', 'Hoàn thành 8% khối lượng NV3', '0.08', ''],
            ['', 'Hội đồng Khoa', '', '', ''],
            ['1.2.1', '- Chủ tịch, thư ký Hội đồng', 'Hoàn thành 12% khối lượng NV3', '0.12', 'Theo QĐ thành lập Hội đồng'],
            ['1.2.2', '- Ủy viên Hội đồng', 'Hoàn thành 8% khối lượng NV3', '0.08', ''],
            ['', 'Hội đồng Đảm bảo chất lượng trường', '', '', ''],
            ['1.3.1', '- Chủ tịch, thư ký Hội đồng', 'Hoàn thành 10% khối lượng NV3', '0.1', 'Theo QĐ thành lập Hội đồng'],
            ['1.3.2', '- Ủy viên Hội đồng', 'Hoàn thành 5% khối lượng NV3', '0.05', ''],
            ['', 'Hội đồng Đánh giá chương trình đào tạo', '', '', ''],
            ['1.4.1', '- Chủ tịch, Phó Chủ tịch', 'Hoàn thành 30% khối lượng NV3', '0.3', 'Theo QĐ thành lập Hội đồng'],
            ['1.4.2', '- Thư ký Hội đồng', 'Hoàn thành 50% khối lượng NV3', '0.5', ''],
            ['1.4.3', '- Ủy viên Hội đồng', 'Hoàn thành 10% khối lượng NV3', '0.1', ''],
            ['', 'Tham gia công tác đảng, công đoàn, nữ công, hội cựu chiến binh, đoàn thanh niên', '', '', ''],
            ['1.5.1', 'Ủy ban kiểm tra đảng ủy Trường', '', '', ''],
            ['1.5.2', 'Chủ nhiệm', 'Hoàn thành 30% khối lượng NV3', '0.3', 'Theo QĐ chuẩn y của Đảng bộ Bộ TNMT'],
            ['1.5.3', 'Phó Chủ nhiệm', 'Hoàn thành 25% khối lượng NV3', '0.25', ''],
            ['1.5.4', 'Ủy viên', 'Hoàn thành 20% khối lượng NV3', '0.2', ''],
            ['', 'Chi ủy viên Chi bộ trực thuộc', '', '', ''],
            ['1.5.5', '< 20 Đảng viên', 'Hoàn thành 3% khối lượng NV3', '0.03', 'Theo QĐ chuẩn y của Đảng bộ Trường'],
            ['1.5.6', '≥ 20 Đảng viên', 'Hoàn thành 5% khối lượng NV3', '0.05', ''],
            ['1.5.7', '≥ 40 Đảng viên', 'Hoàn thành 10% khối lượng NV3', '0.1', ''],
            ['', 'Uỷ ban kiểm tra Công đoàn trường', '', '', ''],
            ['1.5.8', 'Chủ nhiệm', 'Hoàn thành 25% khối lượng NV3', '0.25', 'Theo QĐ chuẩn y của Công đoàn Bộ TNMT'],
            ['1.5.9', 'Phó Chủ nhiệm', 'Hoàn thành 20% khối lượng NV3', '0.2', ''],
            ['1.5.10', 'Ủy viên', 'Hoàn thành 15% khối lượng NV3', '0.15', ''],
            ['', 'Ban thanh tra nhân dân', '', '', ''],
            ['1.5.11', 'Phó chủ nhiệm', 'Hoàn thành 15% khối lượng NV3', '0.15', 'Theo QĐ chuẩn y của Công đoàn Trường'],
            ['1.5.12', 'Ủy viên', 'Hoàn thành 10% khối lượng NV3', '0.1', ''],
            ['', 'Ban nữ công Trường', '', '', ''],
            ['1.5.13', 'Phó chủ nhiệm', 'Hoàn thành 15% khối lượng NV3', '0.15', 'Theo QĐ chuẩn y của Công đoàn Trường'],
            ['1.5.14', 'Ủy viên', 'Hoàn thành 10% khối lượng NV3', '0.1', ''],
            ['', 'Đoàn thanh niên Trường', '', '', ''],
            ['1.5.15', 'Ủy viên BCH Đoàn trường', 'Hoàn thành 20% khối lượng NV3', '0.2', 'Theo QĐ chuẩn y của Đoàn TN Bộ TNMT'],
            ['1.5.16', 'Phó Bí thư liên chi', 'Hoàn thành 15% khối lượng NV3', '0.15', 'Theo QĐ chuẩn y của Đoàn trường'],
            ['1.5.17', 'Ủy viên BCH liên chi', 'Hoàn thành 10% khối lượng NV3', '0.1', ''],
            ['1.5.18', 'Tham gia Hội đồng tuyển dụng hợp đồng lao động', '02 giờ/1 ứng viên dự tuyển', '2', 'Theo QĐ của Hiệu trưởng'],
            ['1.5.19', 'Tham gia Ban phòng cháy chữa cháy Trường', '10 giờ/năm', '10', 'Theo QĐ của Hiệu trưởng'],
            ['1.5.20', 'Giảng viên tập sự / trợ giảng đi dự giờ', '01 giờ/hội đồng', '1', 'Xác nhận của Khoa'],
            ['II', 'Hoạt động phục vụ đào tạo, khoa học, người học, phục vụ cộng đồng…', '', '', ''],
            ['2.1.1', 'Xây dựng đề án mở mới ngành đào tạo', '150 giờ/1 đề án', '150', '- Trưởng khoa phân bổ số giờ cho các thành viên'],
            ['', 'Xây dựng chương trình đào tạo theo Quyết định của Trường, không được chi kinh phí, không tính xây dựng đề cương chi tiết (từ khi bắt đầu đến khi chương trình được phê duyệt)', '', '', ''],
            ['2.1.2', 'Chủ trì và các thành viên', '500 giờ /1 chương trình', '500', '- Trưởng khoa phân bổ số giờ cho các thành viên'],
            ['2.1.3', 'Thư ký', 'Hoàn thành 50% khối lượng NV3', '0.5', '- QĐ thành lập tổ soạn thảo, QĐ phê duyệt chương trình'],
            ['', 'Xây dựng mới đề cương chi tiết không được chi kinh phí', '', '', ''],
            ['2.1.4', 'Người biên soạn', '30 giờ/đề cương', '30', 'QĐ thành lập tổ soạn thảo, QĐ phê duyệt ĐCCT'],
            ['2.1.5', 'Người nhận xét', '10 giờ/đề cương', '10', ''],
            ['', 'Rà soát, cập nhật chương trình đào tạo theo Quyết định của Trường, không được chi kinh phí, không tính xây dựng đề cương chi tiết (từ khi bắt đầu đến khi chương trình được phê duyệt)', '', '', ''],
            ['2.1.6', 'Chủ trì và các thành viên', '250 giờ/1 chương trình', '250', '- Trưởng khoa phân bổ số giờ cho các thành viên.'],
            ['2.1.7', 'Thư ký', 'Hoàn thành 25% khối lượng NV3', '0.25', '- QĐ thành lập tổ soạn thảo, QĐ phê duyệt chương trình'],
            ['', 'Điều chỉnh đề cương chi tiết không được chi kinh phí', '', '', ''],
            ['2.1.8', 'Người biên soạn', '15 giờ/đề cương', '15', 'QĐ thành lập tổ soạn thảo, QĐ phê duyệt ĐCCT'],
            ['2.1.9', 'Người nhận xét', '5 giờ/đề cương', '5', ''],
            ['', 'Tổ chức buổi báo cáo chuyên đề, học thuật cho Trường.', '', '', ''],
            ['2.1.10', 'Chủ trì', '30 giờ/lần', '30', '- P. KHCN&HTQT xác nhận cho từng thành viên tham dự;'],
            ['2.1.11', 'Thành viên trong Ban tổ chức.', '15 giờ/lần', '15', '- QĐ (hoặc kế hoạch) của nhà trường.'],
            ['', 'Tổ chức buổi báo cáo chuyên đề, học thuật cho Khoa.', '', '', ''],
            ['2.1.12', 'Chủ trì', '10 giờ/lần', '10', '- Trưởng khoa xác nhận cho từng thành viên tham dự;'],
            ['2.1.13', 'Thành viên trong Ban tổ chức.', '05 giờ/lần', '5', '- Kế hoạch tổ chức có xác nhận của nhà trường'],
            ['2.1.14', 'Viết bài, hoặc báo cáo tại hội nghị, hội thảo, … về lĩnh vực giáo dục hoặc chuyên ngành (không xuất bản ấn phẩm), dưới danh nghĩa Trường.', '- 20 giờ /1 bài trong nước', '20', '- Toàn văn bài viết;'],
            ['2.1.15', '', '- 40 giờ / 1 bài quốc tế', '40', '- Chương trình HT, HN;\n- Giấy mời (nếu là báo cáo viên)'],
            ['2.1.16', 'Thành viên nhóm nghiên cứu mạnh, chuyên sâu, dự án quốc tế/quốc gia/tỉnh/thành không do Trường quyết định nhưng phải mang tên Trường (có hoạt động thực tế được công nhận).', 'Tối đa 50 giờ/năm/ đề tài, dự án,…', '50', 'Theo đề xuất của đơn vị đầu mối, được BGH xác nhận'],
            ['2.1.17', 'Thành viên Ban thư ký của Hội đồng tự đánh giá chương trình đào tạo (chỉ tính với các thành viên là giảng viên).', '4.500 giờ/chương trình', '4500', '- Tính trong thời gian làm, phân bổ từng năm theo đề nghị của trưởng khoa'],
            ['2.1.18', 'Hỗ trợ trường, khoa trong công tác đối ngoại (không thuộc chức năng nhiệm vụ của vị trí đương nhiệm), hỗ trợ/tư vấn cho các đơn vị khác trong trường,... (có xác nhận của trường).', 'Tối đa 50 giờ làm việc/năm', '50', 'Theo đề xuất của đơn vị đầu mối, được BGH xác nhận hoặc theo Quyết định phân công của trường'],
            ['2.1.19', 'Hỗ trợ Trường, khoa trong công tác sinh viên, cựu sinh viên và hướng nghiệp, giao lưu ra bên ngoài...', 'Tối đa 50 giờ làm việc/năm', '50', 'Theo đề xuất của đơn vị đầu mối, được BGH xác nhận hoặc theo Quyết định phân công của trường'],
            ['2.1.20', 'Xét duyệt đề xuất nghiên cứu khoa học của người học ở khoa/bộ môn.', '2 giờ/1 đề cương', '2', "- Danh sách phân công của Khoa/Bộ môn trực thuộc\n- Biên bản họp;\n- Bản đề xuất (photo)"],
            ['2.1.21', 'Xét duyệt đề xuất NCKH cấp cơ sở không sử dụng NSNN của GV ở khoa/bộ môn.', '3 giờ/1 đề cương', '3', "- Danh sách phân công của Khoa/Bộ môn trực thuộc\n- Biên bản họp;\n- bản đề xuất (photo)"],
            ['2.1.22', 'Thành viên Hội đồng xét duyệt thuyết minh, hội đồng nghiệm thu đề tài NCKH cấp cơ sở không sử dụng NSNN.', '4 giờ/đề tài', '4', 'QĐ thành lập hội đồng'],
            ['2.1.23', 'Thành viên Hội đồng xét duyệt thuyết minh; Hội đồng nghiệm thu đề tài NCKH của sinh viên.', '1,5 giờ/đề tài', '1.5', 'QĐ thành lập hội đồng'],
            ['2.1.24', 'Xét duyệt tiến độ luận văn thạc sỹ (trước khi xét điều kiện bảo vệ).', '01 giờ/1 luận văn/thành viên', '1', "- Danh sách phân công của Khoa/Bộ môn trực thuộc;\n- Biên bản họp;\n- Bản dự thảo luận văn của học viên"],
            ['2.1.25', 'Duyệt đề cương Khóa luận tốt nghiệp sinh viên.', '01 giờ/đề cương (tính cho cả nhóm duyệt)', '1', "- Danh sách phân công của Khoa/Bộ môn trực thuộc\n- Biên bản họp;\n- Bản dự thảo đề cương khóa luận"],
            ['2.1.26', 'Xét duyệt tiến độ, khối lượng khóa luận tốt nghiệp trước bảo vệ.', '01 giờ/khóa luận (tính cho cả nhóm duyệt)', '1', "- Danh sách phân công của Khoa/Bộ môn trực thuộc\n- Biên bản họp;\n- Bản dự thảo khóa luận của SV"],
            ['2.1.27', 'Tham gia góp ý các dự thảo văn bản của Bộ, Trường, Khoa (khi được yêu cầu).', '8 giờ với văn bản cấp trường', '8', "- Xác nhận của Trưởng khoa;\n- Bản nhận xét góp ý"],
            ['2.1.28', '', '16 giờ với văn bản cấp Bộ', '16', ''],
            ['2.1.29', 'Rà soát kế hoạch đào tạo', '10 giờ/kỳ', '10', 'Kế hoạch đào tạo được phê duyệt'],
            ['', 'Tổ chức các hội thảo cấp Trường.', 'Theo phê duyệt của nhà trường', '', 'QĐ/ Kế hoạch tổ chức hội thảo của nhà trường'],
            ['2.1.30', 'Liên hệ cho SV, HV đi tham quan nhận thức tại cơ sở', '4 giờ/đợt', '4', "- Kế hoạch đào tạo\n- Xác nhận của trưởng khoa.\n- Giấy đi đường có xác nhận của cơ sở liên hệ."],
            ['2.1.31', 'Liên hệ cho SV, HV đi thực tập tốt nghiệp tại cơ sở (đối với các sinh viên không tự xin được nơi thực tập và đăng ký để khoa/ bộ môn liên hệ)', '4 giờ/cơ sở', '4', "- Kế hoạch và phần công của khoa, kèm theo danh sách sinh viên/cơ sỏ.\n- Giấy đi đường có xác nhận của cơ sở liên hệ."],
            ['2.1.32', 'Lập báo cáo khảo sát các đối tượng cấp khoa/bộ môn (01 lần/học kỳ).', '40 giờ/học kỳ', '40', "- Thông báo/kế hoạch của đơn vị chức năng\n- Danh sách phân công của khoa có xác nhận của Trưởng khoa\n- Báo cáo khảo sát."],
            ['2.1.33', 'Lập kế hoạch duy trì và nâng cao hoạt động đảm bảo chất lượng của đơn vị (01 lần/học kỳ).', '12 giờ/học kỳ', '12', "- Thông báo/kế hoạch của đơn vị chức năng\n- Danh sách phân công của khoa có xác nhận của Trưởng khoa\n- Báo cáo."],
            ['2.1.34', 'Lập báo cáo tổng kết việc thực hiện kế hoạch nhằm duy trì và nâng cao hoạt động đảm bảo chất lượng của đơn vị (01 lần/học kỳ).', '16 giờ/học kỳ', '16', "- Thông báo/kế hoạch của đơn vị chức năng\n- Danh sách phân công của khoa có xác nhận của Trưởng khoa\n- Báo cáo."],
            ['III', 'Hoạt động phục vụ công tác tuyển sinh, truyền thông, quản lý các trang thông tin của trường, khoa', '', '', ''],
            ['3.1.1', 'Viết bài đăng trên các trang quảng bá, tuyển sinh của nhà trường quản lý (không thuộc chức năng nhiệm vụ được quy định).', '- Tối đa 05 giờ/tin, bài đăng trên fanpge của khoa', '5', "- Xác nhận của phòng/ban chức năng (nếu đăng trên fanpge của trường) kèm theo minh chứng"],
            ['3.1.2', '', '- Tối đa 10 giờ/tin, bài đăng trên fanpge của trường', '10', "- Xác nhận của Trưởng khoa (nếu đăng trên fanpge của khoa) kèm theo minh chứng\n- Các tin, bài đăng ở nhiều kênh thông tin khác nhau chỉ được tính 01 lần cao nhất"],
            ['3.1.3', 'Quản trị web, fanpage khoa (không thuộc chức năng nhiệm vụ được quy định).', '- Mức 1 (tốt): 30 giờ;', '30', "- Tối đa 30 giờ/năm/thành viên với web (tối đa 2 thành viên).\n- Tối đa 30 giờ/năm/thành viên với fanpage (tối đa 3 thành viên).\n- Xác nhận của phòng/ban chức năng về kết quả hoạt động."],
            ['3.1.4', '', '- Mức 2 (khá): 25 giờ;', '25', ''],
            ['3.1.5', '', '- Mức 3 (trung bình): 15 giờ', '15', ''],
            ['', 'Tham gia hoạt động truyền thông, quảng bá hình ảnh của Khoa, Trường.', 'Tính theo thực tế', '', "- Kế hoạch của khoa\n- Đề nghị của trưởng khoa\n- Xác nhận của phòng/ban chức năng"],
            ['', 'Tham gia công tác tuyển sinh, nhập học của trường.', 'Tính theo thực tế', '', "- Theo QĐ hoặc thông báo hoặc kế hoạch điều động của trường.\n- Xác nhận của phòng/ban chức năng."],
            ['', 'Hỗ trợ công tác quảng bá tuyển sinh của trường.', 'Tính theo thực tế', '', 'Kế hoạch của nhà trường'],
            ['IV', 'Học tập, nâng cao trình độ, hội họp (được tính tối đa 50% tổng số giờ định mức của NV3)', '', '', ''],
            ['4.1.1', 'Chứng chỉ/chứng nhận chuyên môn, nghiệp vụ phục vụ trực tiếp hoặc theo yêu cầu của vị trí công việc đương nhiệm, không được hỗ trợ kinh phí (Ví dụ: Phương pháp giảng dạy, phương pháp nghiên cứu khoa học, quản lý hành chính, đảm bảo chất lượng, tin học, ngoại ngữ....).', '100 giờ /1 chứng chỉ', '100', "- Bản công chứng CC/chứng nhận (nộp phòng TCHC).\n- Phòng TCHC xác nhận"],
            ['4.1.2', 'Chứng chỉ hỗ trợ nâng cao chất lượng và hiệu quả công việc theo định hướng của trường (ví dụ: Tiếng Anh quốc tế TOEFL 550+, TOEFL iBT 80+, TOEIC 780+, IELTS 6.5+, Tin học nâng cao,....), không được hỗ trợ kinh phí.', '100 giờ /1 chứng chỉ', '100', "- Bản công chứng CC/chứng nhận (nộp phòng TCHC).\n- Phòng TCHC xác nhận"],
            ['4.1.3', 'Tham dự các hội thảo chuyên ngành để tự bồi dưỡng chuyên môn (không có báo cáo).', '- 5 giờ /hội thảo trong nước', '5', "-Giấy mời hoặc bản chụp đăng ký đại biểu tham dự;\n- Tài liệu, chương trình hội thảo"],
            ['4.1.4', '', '- 10 giờ /hội thảo quốc tế', '10', ''],
            ['4.1.5', 'Tham gia sinh hoạt chuyên môn của trường.', '4 giờ/buổi,\nSố buổi theo thực tế', '4', 'Theo QĐ/Kế hoạch của Nhà trường'],
            ['4.1.6', 'Thực hiện và tham gia seminar chuyên môn cấp khoa.', '- Người trình bày: 16 giờ/cuộc;', '16', "- Theo văn bản được Nhà trường phê duyệt\n- Xác nhận của Trưởng Khoa"],
            ['4.1.7', '', '- Người tham dự: 3 giờ/cuộc', '3', ''],
            ['4.1.8', 'Tham gia sinh hoạt chuyên môn bộ môn (cấp khoa)', '4 giờ/buổi,\ntối đa 40 giờ/năm', '4', "- Xác nhận của Trưởng khoa;\n- Biên bản họp bộ môn"],
            ['4.1.9', 'Họp chi bộ', 'Tối đa 4 giờ/tháng', '4', 'Xác nhận của Bí thư chi bộ'],
            ['4.1.10', 'Họp công đoàn', 'Tối đa 16 giờ/năm', '16', "- Xác nhận của Công đoàn Trường\n- Biên bản họp"],
            ['4.1.11', 'Họp Đoàn thanh niên', 'Tối đa 16 giờ/năm', '16', "- Xác nhận của Đoàn thanh niên Trường\n- Biên bản họp"],
            ['4.1.12', 'Tham dự các cuộc họp, làm việc, trao đổi chuyên môn với khách trong nước và quốc tế tại trường theo phân công của Khoa, Trường', '3 giờ/ buổi', '3', "- Xác nhận của Trưởng khoa;\n- Kế hoạch (cấp khoa), lịch tuần (cấp trường)"],
            ['', 'Tham dự các đoàn công tác quốc gia, quốc tế, cuộc hội thảo, làm việc, trao đổi chuyên môn ngoài trường nhưng mang danh nghĩa của trường và không sử dụng ngân sách của trường', 'Theo thực tế', '', 'Giấy mời hoặc Quyết định thành lập đoàn công tác'],
            ['V', 'Tham gia các hoạt động văn thể mỹ, các sự kiện chung của Trường hoặc khoa (được tính tối đa 15% tổng số giờ định mức của NV3)', '', '', ''],
            ['', 'Tham gia các hoạt động văn thể, mỹ (tính tối đa 15% tổng số giờ định mức của NV3)', '', '', ''],
            ['', 'Ban tổ chức Cuộc thi luyện tập thể thao đẩy lùi Covid', '', '', ''],
            ['5.2.1', '- Trưởng ban', '10% NV3', '0.1', ''],
            ['5.2.2', '- Các thành viên khác', '5% NV3', '0.05', ''],
            ['', 'Công đoàn viên tham gia các hoạt động/ cuộc thi', '', '', ''],
            ['', 'Cuộc thi do công đoàn cấp trên tổ chức', '', '', ''],
            ['5.3.1', '- Giải đặc biệt', '15% NV3', '0.15', ''],
            ['5.3.2', '- Giải nhất', '14% NV3', '0.14', ''],
            ['5.3.3', '- Giải nhì', '13% NV3', '0.13', ''],
            ['5.3.4', '- Giải ba', '12% NV3', '0.12', ''],
            ['5.3.5', '- Giải khuyến khích', '11% NV3', '0.11', ''],
            ['5.3.6', '- Tham gia nhưng không đạt giải', '5% NV3', '0.05', ''],
            ['', 'Cuộc thi do công đoàn trường tổ chức', '', '', ''],
            ['5.4.1', '- Giải đặc biệt', '10% NV3', '0.1', ''],
            ['5.4.2', '- Giải nhất', '9% NV3', '0.09', ''],
            ['5.4.3', '- Giải nhì', '8% NV3', '0.08', ''],
            ['5.4.4', '- Giải ba', '7% NV3', '0.07', ''],
            ['5.4.5', '- Giải khuyến khích', '6% NV3', '0.06', ''],
            ['5.4.6', '- Tham gia nhưng không đạt giải', '3% NV3', '0.03', ''],
            ['VI', 'Hướng dẫn tập sự cho viên chức', '', '', ''],
            ['6.1.1', 'Hướng dẫn tập sự cho viên chức mới ký HĐ làm việc (áp dụng chung cho tất cả các trường hợp thuộc Phòng, Khoa, Trung tâm).', '45 giờ/năm', '45', 'Theo QĐ giao của Hiệu trưởng'],
            ['VII', 'Các công việc khác (đã được phê duyệt theo 05/QĐ-TĐHHN, ngày 04/01/2022)', 'Giờ làm việc', '', 'Minh chứng'],
            ['7.1.1', 'Rà soát, cập nhật chương trình đào tạo: Hoàn thiện các chương trình dạy học, bản mô tả chương trình đào tạo thạc sỹ', '100 giờ/ CTĐT', '100', 'Xác nhận của phòng Đào tạo'],
            ['7.2.1', 'Lập kế hoạch cải tiến chương trình đào tạo, thu thập minh chứng, viết báo cáo cải tiến chương trình đào tạo cho từng năm sau đánh giá ngoài', '200 giờ/ CTĐT', '200', 'Xác nhận của phòng Đào tạo'],
            ['7.3.1', 'Tuyên truyền, giáo dục tư tưởng chính trị, rèn luyện đạo đức, lối sống cho sinh viên', '16 giờ/ 1 giảng viên', '16', 'Lãnh đạo khoa LLCT xác nhận'],
            ['7.4.1', 'Tham gia họp lấy ý kiến, họp nghe phổ biến nghị quyết của đảng hoặc chính sách nhà nước', '4 giờ/buổi', '4', 'Theo triệu tập của Đảng, Lãnh đạo nhà trường'],
            ['7.5.1', 'Trợ giảng, tập sự: giảm 50% NV3 nếu đi học cao học đúng chuyên ngành', '50% khối lượng NV3', '0.5', 'Phòng TCHC xác nhận'],
            ['7.6.1', 'Ủy viên BCH Hội Cựu chiến binh', 'Hoàn thành 10% khối lượng NV3', '0.1', 'Phòng TCHC xác nhận'],
            ['7.7.1', 'Hỗ trợ thời gian thực hiện NV3 cho giảng viên trong công tác xây dựng tư liệu, phương pháp trong giảng dạy trực tuyến', '50 giờ/ 1 giảng viên', '50', 'Phòng Đào tạo xác nhận'],
            ['7.8.1', 'Hỗ trợ thời gian thực hiện NV3 cho giảng viên coi thi trực tuyến các học phần', '1 giờ/ 1 ca thi/ 1 giảng viên', '1', 'Phòng Đào tạo xác nhận'],
            ['7.9.1', 'Hỗ trợ thời gian thực hiện NV3 cho giảng viên chấm thi kết thúc học phần theo hình thức thi trực tuyến', '2 giờ/ 1 phòng thi/ 1 giảng viên', '2', 'Phòng KT ĐBCLGD xác nhận'],
            ['7.10.1', 'Hỗ trợ cho giảng viên tham gia lớp nâng cao trình độ tiếng Anh theo Đề án Ngoại ngữ của Nhà trường', '50 giờ/ 1 người', '50', 'Phòng TCHC xác nhận'],
            ['7.11.1', 'Hỗ trợ tham gia bảo trì thiết bị giảng dạy thực hành thí nghiệm, cài đặt phần mềm phục vụ đào tạo chuyên ngành', '100 giờ', '100', 'Kế hoạch của Khoa, Bộ môn'],
            ['7.12.1', 'Phụ trách công tác an toàn bức xạ Phòng thí nghiệm (đặc thù công việc 3 năm mới phải làm hồ sơ xin cấp phép một lần)', '100 giờ/ 1 bộ hồ sơ', '100', 'Lãnh đạo khoa Môi trường xác nhận'],
            ['7.13.1', 'Tập huấn, bồi dưỡng giảng viên Lý luận chính trị', '16 giờ/ 1 giảng viên', '16', 'Theo kế hoạch của Khoa và lịch của Bộ Giáo dục và Đào tạo'],
            ['7.14.1', 'Rà soát đề cương chi tiết môn tiếng Anh chuyên ngành, đề cương chi tiết các môn học bằng tiếng Anh của các ngành đào tạo trong trường trong xây dựng các chương trình đào tạo', '2 giờ/ 1 CTĐT', '2', 'Lãnh đạo Bộ môn Ngoại ngữ xác nhận'],
            ['7.15.1', 'Tham gia chỉ đạo, cố vấn cho các câu lạc bộ được Nhà trường cho phép thành lập', '5 giờ/ 1 tháng/ 1 câu lạc bộ', '5', 'Phòng CTSV xác nhận'],
            ['7.16.1', 'Quản lý kho vũ khí, quân trang và dụng cụ thể dục thể thao', '100 giờ/ 1 năm', '100', 'Lãnh đạo Bộ môn GDTC&GDQP xác nhận'],
            ['7.17.1', 'Phản biện các bài báo trong các Hội thảo quốc gia hay quốc tế do ĐHTNMTHN chủ trì (không kinh phí)', '- Bài tiếng Việt: 5 giờ', '5', 'Phòng KHCN&HTQT lập danh sách xác nhận'],
            ['7.17.2', '', '- Bài tiếng Anh: 10 giờ', '10', ''],
            ['', 'Tham gia viết báo cáo đánh giá CTĐT/Trường', '', '', ''],
            ['7.18.1', 'Báo cáo tổng hợp tiêu chuẩn', '100 giờ/tiêu chuẩn', '100', 'Xác nhận của phòng KT&ĐBCLGD'],
            ['7.18.2', 'Báo cáo tiêu chí', '50 giờ/tiêu chí', '50', 'Xác nhận của phòng KT&ĐBCLGD'],
            ['', 'Rà soát xây dựng văn bản quản lý, điều hành', '', '', ''],
            ['7.19.1', '- Cấp Trường', '- 20 giờ/1 văn bản mới', '20', 'Xác nhận của phòng KT&ĐBCLGD'],
            ['7.19.2', '', '- 10 giờ/1 văn bản chỉnh sửa', '10', 'Xác nhận của phòng KT&ĐBCLGD'],
            ['7.19.3', '- Cấp khoa, phòng', '- 10 giờ/1 văn bản mới', '10', 'Xác nhận của phòng KT&ĐBCLGD'],
            ['7.19.4', '', '- 5 giờ/1 văn bản chỉnh sửa', '5', 'Xác nhận của phòng KT&ĐBCLGD'],
            ['7.19.5', '- Cấp bộ môn thuộc khoa', '- 8 giờ/1 văn bản mới', '8', 'Xác nhận của phòng KT&ĐBCLGD'],
            ['7.19.6', '', '- 3 giờ/1 văn bản chỉnh sửa', '3', 'Xác nhận của phòng KT&ĐBCLGD'],
            ['7.20.1', 'Tham gia rà soát, tổng hợp, xử lý số liệu minh chứng đánh giá CTĐT/Trường', '10 giờ/tiêu chí', '10', 'Trưởng các nhóm chuyên môn xác nhận'],
            ['7.21.1', 'Rà soát, cập nhật đề cương chi tiết hàng kì', '5 giờ/đề cương', '5', 'Lãnh đạo Khoa xác nhận'],
            ['7.22.1', 'Hỗ trợ phát bằng tốt nghiệp cho sinh viên', '4 giờ/buổi/1 giảng viên', '4', 'Phòng Đào tạo xác nhận'],
            ['7.23.1', 'Tham gia thẩm định và lựa chọn sách phục vụ đào tạo', '10 giờ/ 1 Hội đồng', '10', 'Lãnh đạo Khoa xác nhận'],
            ['7.24.1', 'Lên lớp tuần sinh hoạt công dân', 'Theo thời khóa biểu tuần sinh hoạt công dân (Trường hợp chưa được thanh toán)', '', 'Phòng CTSV xác nhận'],
            ['7.25.1', 'Tham gia quá trình tổ chức hội thảo quốc tế; quốc gia theo yêu cầu của nhà trường', '100 giờ/người/năm', '100', 'Phòng KHCN&HTQT xác nhận'],
            ['7.25.2', 'Thành viên Ban thư ký hội thảo khoa học do Trường chủ trì', '05 giờ/ 1 bài Tiếng Việt', '5', 'Phòng KHCN&HTQT xác nhận'],
            ['7.25.3', '', '10 giờ/ 1 bài Tiếng Anh', '10', 'Phòng KHCN&HTQT xác nhận'],
            ['7.25.4', 'Góp ý, nhận xét bài đăng trong Kỷ yếu HTKH do Trường chủ trì không có kinh phí hỗ trợ', '10 giờ/ 1 bài Tiếng Việt', '10', 'Phòng KHCN&HTQT xác nhận'],
            ['7.25.5', '', '15 giờ/ 1 bài Tiếng Anh', '15.55', 'Phòng KHCN&HTQT xác nhận'],
            ['7.26.1', 'Thành viên Hội đồng dự giờ', '2 giờ/ giảng viên', '2', "- Kế hoạch của Bộ môn, Khoa xác nhận\n- Biên bản dự giờ"],
            ['7.27.1', 'Tổ trưởng tổ nữ công', '3% KL NV3', '3', 'Ban nữ công'],
            ['7.28.1', 'Làm các video phục vụ quảng bá hình ảnh cho các hoạt động đào tạo hoặc NCKH hoặc HTQT cho Khoa/ Nhà trường', '50 giờ/ 1 video', '50', 'Lãnh đạo khoa xác nhận'],
        ];

        // Thêm dữ liệu vào Sheet 2
        $rowIndexSheet2 = 2;
        foreach ($dataSheet2 as $rowData) {
            $sheet2->fromArray($rowData, null, 'A' . $rowIndexSheet2);
            $sheet2->getStyle('A' . $rowIndexSheet2 . ':E' . $rowIndexSheet2)->applyFromArray($styleArray);
            // Áp dụng chữ in đậm cho các dòng tiêu đề (I, II, III,...)
            if (in_array($rowData[0], ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII'])) {
                $sheet2->getStyle('A' . $rowIndexSheet2 . ':E' . $rowIndexSheet2)->getFont()->setBold(true);
            }
            $rowIndexSheet2++;
        }

        // Áp dụng màu đỏ cho các dòng ghi chú đặc biệt
        $redRows = [
            72 => '2.1.16',  // Thành viên nhóm nghiên cứu mạnh
            73 => '2.1.17',  // Thành viên Ban thư ký
            74 => '2.1.18',  // Hỗ trợ trường, khoa trong công tác đối ngoại
            75 => '2.1.19',  // Hỗ trợ Trường, khoa trong công tác sinh viên
            83 => '2.1.27',  // Tham gia góp ý các dự thảo văn bản
            84 => '2.1.28',  // (tiếp theo của 2.1.27)
            94 => '3.1.3',   // Quản trị web, fanpage khoa
            95 => '3.1.4',   // (tiếp theo của 3.1.3)
            96 => '3.1.5',   // (tiếp theo của 3.1.3)
            111 => '4.1.12', // Tham dự các cuộc họp
            114 => '5.2.1',  // Trưởng ban
            115 => '5.2.2',  // Các thành viên khác
            149 => '7.17.1', // Phản biện các bài báo
            150 => '7.17.2', // (tiếp theo của 7.17.1)
            157 => '7.24.1', // Lên lớp tuần sinh hoạt công dân
            161 => '7.25.4', // Góp ý, nhận xét bài đăng
            162 => '7.25.5', // (tiếp theo của 7.25.4)
            163 => '7.26.1', // Thành viên Hội đồng dự giờ
            164 => '7.27.1', // Tổ trưởng tổ nữ công
            165 => '7.28.1', // Làm các video phục vụ quảng bá
        ];

        foreach ($redRows as $rowNum => $code) {
            $sheet2->getStyle('A' . ($rowNum + 1) . ':E' . ($rowNum + 1))->getFont()->setColor(new Color(Color::COLOR_RED));
        }

        // Thiết lập headers để tải file Excel
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        header('Expires: 0');

        // Xuất file
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } catch (Exception $e) {
        // Hiển thị lỗi nếu có vấn đề khi tạo file
        die('Lỗi khi tạo file Excel: ' . $e->getMessage());
    }
} else {
    // Nếu không phải taskType=nhiemvukhac, chuyển hướng về report.php
    header('Location: report.php');
    exit;
}
?>