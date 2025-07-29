document.addEventListener('DOMContentLoaded', function () {
    // Lấy tất cả các checkbox có class 'column-toggle'
    const checkboxes = document.querySelectorAll('.column-toggle');

    // Lắng nghe sự kiện 'change' trên từng checkbox
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            // Lấy giá trị data-column của checkbox (ví dụ: 'cell-TenMH')
            const columnName = this.getAttribute('data-column');

            // Tìm tất cả các ô (cells) trong bảng có name khớp với data-column
            const cells = document.querySelectorAll(`[name="${columnName}"]`);

            // Nếu checkbox không được tích, ẩn cột; nếu được tích, hiện cột
            cells.forEach(cell => {
                if (this.checked) {
                    cell.style.display = 'table-cell'; // Hiện cột
                } else {
                    cell.style.display = 'none'; // Ẩn cột
                }
            });
        });
    });

    // Khởi tạo trạng thái ban đầu: ẩn các cột nếu checkbox không được tích
    checkboxes.forEach(checkbox => {
        const columnName = checkbox.getAttribute('data-column');
        const cells = document.querySelectorAll(`[name="${columnName}"]`);

        if (!checkbox.checked) {
            cells.forEach(cell => {
                cell.style.display = 'none'; // Ẩn cột nếu checkbox không được tích
            });
        }
    });
});