<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['table_name'])) {
    $table_name = $_POST['table_name'];
    
    // Kiểm tra tên bảng hợp lệ
    $valid_tables = ['nckhcc_history', 'huongdansv_history', 'bai_bao_history', 'vietsach_history'];
    
    if (in_array($table_name, $valid_tables)) {
        $_SESSION['current_table'] = $table_name;
        
        // Map tên bảng sang tên hiển thị
        $display_names = [
            'nckhcc_history' => 'Nghiên cứu khoa học các cấp',
            'huongdansv_history' => 'Hướng dẫn sinh viên làm nghiên cứu khoa học',
            'bai_bao_history' => 'Viết bài báo',
            'vietsach_history' => 'Viết sách'
        ];
        
        echo json_encode([
            'success' => true,
            'display_name' => $display_names[$table_name]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tên bảng không hợp lệ']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ']);
} 