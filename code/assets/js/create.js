document.addEventListener("DOMContentLoaded", function () {
    const calendarTable = document.getElementById("calendarTable");
    const currentMonthYear = document.getElementById("currentMonthYear");
    const scheduleDates = document.querySelectorAll(".date-cell");
    const scheduleWeekdays = document.querySelectorAll(".weekday-cell");
    const scheduleTable = document.getElementById("scheduleTable");
    const searchSubject = document.getElementById("searchSubject");
    const subjectList = document.getElementById("subjectList");
    const sessionSelect = document.getElementById("sessionSelect");
    const daySelect = document.getElementById("daySelect");
    const roomSelect = document.getElementById("roomSelect");
    const addScheduleBtn = document.getElementById("addSchedule");
    const startDateInput = document.getElementById("startDate");

    let currentDate = new Date();
    let today = new Date();
    const scheduleGrid = window.scheduleGrid || [];
    const role = window.role || "Giảng viên";

    const sessions = [
        { value: "1", text: "Tiết 1 (07:00 - 07:50)" },
        { value: "2", text: "Tiết 2 (07:55 - 08:45)" },
        { value: "3", text: "Tiết 3 (08:50 - 09:40)" },
        { value: "4", text: "Tiết 4 (09:50 - 10:40)" },
        { value: "5", text: "Tiết 5 (10:45 - 11:35)" },
        { value: "6", text: "Tiết 6 (12:30 - 13:20)" },
        { value: "7", text: "Tiết 7 (13:25 - 14:15)" },
        { value: "8", text: "Tiết 8 (14:20 - 15:10)" },
        { value: "9", text: "Tiết 9 (15:20 - 16:10)" },
        { value: "10", text: "Tiết 10 (16:15 - 17:05)" },
        { value: "11", text: "Tiết 11 (17:30 - 18:20)" },
        { value: "12", text: "Tiết 12 (18:25 - 19:15)" },
        { value: "13", text: "Tiết 13 (19:20 - 20:10)" },
        { value: "14", text: "Tiết 14 (20:15 - 21:05)" }
    ];

    const days = [
        { value: "2", text: "Thứ 2" },
        { value: "3", text: "Thứ 3" },
        { value: "4", text: "Thứ 4" },
        { value: "5", text: "Thứ 5" },
        { value: "6", text: "Thứ 6" },
        { value: "7", text: "Thứ 7" },
        { value: "8", text: "Chủ Nhật" }
    ];

    const rooms = [
        { value: "A101", text: "Phòng A101" },
        { value: "B202", text: "Phòng B202" },
        { value: "C303", text: "Phòng C303" },
        { value: "D404", text: "Phòng D404" }
    ];

    let holidays = window.holidays || JSON.parse(localStorage.getItem("holidays")) || [
        "2025-04-30", "2025-05-01", "2025-09-02", "2025-01-01",
        "2025-02-03", "2025-02-04", "2025-02-05"
    ];
    localStorage.setItem("holidays", JSON.stringify(holidays));

    sessions.forEach(session => {
        let option = document.createElement("option");
        option.value = session.value;
        option.textContent = session.text;
        sessionSelect.appendChild(option);
    });

    days.forEach(day => {
        let option = document.createElement("option");
        option.value = day.value;
        option.textContent = day.text;
        daySelect.appendChild(option);
    });

    rooms.forEach(room => {
        let option = document.createElement("option");
        option.value = room.value;
        option.textContent = room.text;
        roomSelect.appendChild(option);
    });

    const modal = document.getElementById("scheduleModal");
    const modalSubject = document.getElementById("modalSubject");
    const modalTime = document.getElementById("modalTime");
    const modalSession = document.getElementById("modalSession");
    const modalRoom = document.getElementById("modalRoom");
    const deleteScheduleBtn = document.getElementById("deleteScheduleBtn");
    const closeModal = document.getElementsByClassName("close")[0];

    function attachClickEventsToCells() {
        const cells = document.querySelectorAll("#scheduleTable tbody td.subject");
        cells.forEach(cell => {
            cell.removeEventListener("click", handleCellClick);
            cell.addEventListener("click", handleCellClick);
        });
    }

    function handleCellClick() {
        const subject = this.getAttribute("data-subject");
        const startDate = this.getAttribute("data-start-date");
        const endDate = this.getAttribute("data-end-date");
        const session = this.getAttribute("data-session");
        const room = this.getAttribute("data-room");
        const scheduleId = this.getAttribute("data-schedule-id");

        modalSubject.textContent = subject || "Không có dữ liệu";
        modalTime.textContent = startDate && endDate ? `${startDate} đến ${endDate}` : "Không có dữ liệu";
        modalSession.textContent = session || "Không có dữ liệu";
        modalRoom.textContent = room || "Không có dữ liệu";
        deleteScheduleBtn.setAttribute("data-schedule-id", scheduleId);

        modal.classList.add('show');
    }

    attachClickEventsToCells();

    if (closeModal) {
        closeModal.addEventListener("click", function () {
            modal.classList.remove('show');
        });
    }

    window.addEventListener("click", function (event) {
        if (event.target == modal) {
            modal.classList.remove('show');
        }
    });

    if (deleteScheduleBtn) {
        deleteScheduleBtn.addEventListener("click", function () {
            const scheduleId = this.getAttribute("data-schedule-id");
            if (!scheduleId) {
                alert("Không tìm thấy ID lịch học để xóa.");
                return;
            }

            fetch("delete_schedule.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `scheduleId=${scheduleId}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Xóa môn học thành công!");
                        modal.classList.remove('show');
                        const selectedDay = parseInt(document.querySelector(".day.selected").textContent);
                        updateSchedule(selectedDay);
                    } else {
                        alert("Xóa môn học thất bại: " + data.message);
                    }
                })
                .catch(error => {
                    alert("Đã xảy ra lỗi khi xóa môn học: " + error.message);
                });
        });
    }

    function updateSchedule(selectedDay) {
        let startDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), selectedDay);
        startDate.setDate(startDate.getDate() - (startDate.getDay() === 0 ? 6 : startDate.getDay() - 1));
        const weekdays = ["T2", "T3", "T4", "T5", "T6", "T7", "CN"];

        if (scheduleWeekdays.length === 0 || scheduleDates.length === 0) {
            alert("Không thể cập nhật thời khóa biểu: Bảng không tồn tại.");
            return;
        }

        scheduleWeekdays.forEach((cell, index) => {
            let newDate = new Date(startDate);
            newDate.setDate(startDate.getDate() + index);
            cell.textContent = weekdays[newDate.getDay() === 0 ? 6 : newDate.getDay() - 1];
        });
        scheduleDates.forEach((cell, index) => {
            let newDate = new Date(startDate);
            newDate.setDate(startDate.getDate() + index);
            cell.textContent = `${newDate.getDate()}/${newDate.getMonth() + 1}/${newDate.getFullYear()}`;
        });

        const selectedDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), selectedDay);
        const selectedDateStr = selectedDate.toISOString().split('T')[0];
        fetch(`create_schedule.php?selected_date=${selectedDateStr}`, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `holidays=${encodeURIComponent(JSON.stringify(holidays))}`
        })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTable = doc.querySelector('#scheduleTable tbody');
                document.querySelector('#scheduleTable tbody').innerHTML = newTable.innerHTML;

                attachClickEventsToCells();
            })
            .catch(error => {
                alert("Đã xảy ra lỗi khi tải thời khóa biểu.");
            });
    }

    function renderCalendar() {
        const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
        const prevLastDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 0);
        const daysInMonth = lastDay.getDate();
        const firstDayIndex = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;
        const prevDays = prevLastDay.getDate();

        currentMonthYear.textContent = `Tháng ${currentDate.getMonth() + 1} - ${currentDate.getFullYear()}`;

        let days = "";
        let dayCounter = 1 - firstDayIndex;
        for (let row = 0; row < 6; row++) {
            days += "<tr>";
            for (let col = 0; col < 7; col++) {
                let isToday = (dayCounter === today.getDate() &&
                    today.getMonth() === currentDate.getMonth() &&
                    today.getFullYear() === currentDate.getFullYear());
                if (dayCounter <= 0) {
                    days += `<td class="disabled">${prevDays + dayCounter}</td>`;
                } else if (dayCounter > daysInMonth) {
                    days += `<td class="disabled">${dayCounter - daysInMonth}</td>`;
                } else {
                    days += `<td class="day ${isToday ? "selected" : ""}">${dayCounter}</td>`;
                }
                dayCounter++;
            }
            days += "</tr>";
        }
        calendarTable.innerHTML = days;
        addDateClickEvent();
    }

    function addDateClickEvent() {
        document.querySelectorAll(".day").forEach(day => {
            day.addEventListener("click", function () {
                document.querySelectorAll(".day").forEach(d => d.classList.remove("selected"));
                this.classList.add("selected");
                const selectedDay = parseInt(this.textContent);
                updateSchedule(selectedDay);

                const selectedDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), selectedDay);
                const selectedDateStr = selectedDate.toISOString().split('T')[0];
                window.history.pushState({}, '', `?selected_date=${selectedDateStr}`);
            });
        });
    }

    document.getElementById("prevMonth").addEventListener("click", function () {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    });

    document.getElementById("nextMonth").addEventListener("click", function () {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    });

    function initSchedule() {
        currentDate = new Date(today.getFullYear(), today.getMonth(), 1);
        renderCalendar();
        updateSchedule(today.getDate());
    }

    initSchedule();

    const subjects = [
        { id: 1, name: "Công nghệ .NET", credits: 3 },
        { id: 2, name: "Phát triển hệ thống thông tin địa lý", credits: 3 },
        { id: 3, name: "Linux và phần mềm mã nguồn mở", credits: 3 },
        { id: 4, name: "Chủ nghĩa xã hội khoa học", credits: 2 },
        { id: 5, name: "Tin học ứng dụng trong Tài nguyên và Môi trường", credits: 3 },
        { id: 6, name: "Kinh tế chính trị Mác-Lênin", credits: 2 },
        { id: 7, name: "Phân tích thiết kế hệ thống thông tin", credits: 3 },
        { id: 8, name: "Toán cao cấp 2", credits: 2 },
        { id: 9, name: "Lập trình hướng đối tượng", credits: 3 },
        { id: 10, name: "Kiểm thử và đảm bảo chất lượng phần mềm", credits: 3 },
        { id: 11, name: "Lịch sử Đảng Cộng sản Việt Nam", credits: 2 },
        { id: 12, name: "Cấu trúc dữ liệu và giải thuật", credits: 3 },
        { id: 13, name: "Kiến trúc máy tính", credits: 2 },
        { id: 14, name: "Quản lý dự án Công nghệ thông tin", credits: 3 },
        { id: 15, name: "Khai phá dữ liệu", credits: 2 },
        { id: 16, name: "Triết học Mác-Lênin", credits: 3 },
        { id: 17, name: "Xác suất thống kê", credits: 2 },
        { id: 18, name: "Thực hành cơ sở dữ liệu", credits: 3 },
        { id: 19, name: "Xử lý ảnh", credits: 2 },
        { id: 20, name: "Tin học cơ sở", credits: 3 },
        { id: 21, name: "Tin học đại cương", credits: 3 },
        { id: 22, name: "An toàn và bảo mật hệ thống thông tin", credits: 2 },
        { id: 23, name: "Tư tưởng Hồ Chí Minh", credits: 2 },
        { id: 24, name: "Nguyên lý hệ điều hành", credits: 2 },
        { id: 25, name: "Toán rời rạc", credits: 3 },
        { id: 26, name: "Kỹ năng mềm Công nghệ thông tin", credits: 2 },
        { id: 27, name: "Tiếng Anh 3", credits: 2 },
        { id: 28, name: "Phát triển ứng dụng trên nền Web", credits: 3 },
        { id: 29, name: "Vật lý đại cương", credits: 3 },
        { id: 30, name: "Tương tác người máy thông minh", credits: 3 },
        { id: 31, name: "Công nghệ dữ liệu lớn", credits: 3 },
        { id: 32, name: "Kỹ thuật điện tử số", credits: 2 },
        { id: 33, name: "Mạng máy tính", credits: 3 },
        { id: 34, name: "Cơ sở dữ liệu nâng cao", credits: 3 },
        { id: 35, name: "Tiếng Anh 2", credits: 3 },
        { id: 36, name: "Tiếng Anh 1", credits: 3 },
        { id: 37, name: "Công nghệ phần mềm", credits: 2 },
        { id: 38, name: "Lập trình hệ thống nhúng", credits: 3 },
        { id: 39, name: "Phát triển ứng dụng cho các thiết bị di động", credits: 3 },
        { id: 40, name: "Pháp luật đại cương", credits: 2 },
        { id: 41, name: "Công nghệ Java", credits: 3 },
        { id: 42, name: "Trí tuệ nhân tạo", credits: 2 },
        { id: 43, name: "Toán cao cấp 1", credits: 3 },
        { id: 44, name: "Tiếng Anh chuyên ngành Công nghệ thông tin", credits: 3 },
        { id: 45, name: "Cơ sở dữ liệu", credits: 3 },
    ];

    searchSubject.addEventListener("input", function () {
        let input = this.value.trim().toLowerCase();
        subjectList.innerHTML = "";
        if (input.length >= 3) {
            let matchedSubjects = subjects.filter(subject => subject.name.toLowerCase().includes(input));
            matchedSubjects.forEach(subject => {
                let li = document.createElement("li");
                li.textContent = subject.name;
                li.dataset.id = subject.id;
                li.addEventListener("click", function () {
                    searchSubject.value = subject.name;
                    subjectList.innerHTML = "";
                });
                subjectList.appendChild(li);
            });
        }
    });

    function calculateSchedule(subjectName, startDate, weekday, sessions, room) {
        const subject = subjects.find(s => s.name === subjectName);
        if (!subject) {
            return null;
        }

        const totalLessons = subject.credits === 2 ? 30 : 45;
        const lessonsPerDay = sessions.length;
        const totalDays = Math.ceil(totalLessons / lessonsPerDay);

        let currentDay = new Date(startDate);
        if (isNaN(currentDay.getTime())) {
            return null;
        }

        currentDay.setDate(currentDay.getDate() - (currentDay.getDay() === 0 ? 6 : currentDay.getDay() - 1));
        currentDay.setDate(currentDay.getDate() + (parseInt(weekday) - 2));

        let scheduleDates = [];
        let remainingLessons = totalLessons;

        for (let i = 0; i < totalDays; i++) {
            let dateStr = currentDay.toISOString().split('T')[0];
            const isHoliday = holidays.some(holiday => holiday.trim() === dateStr);
            if (isHoliday) {
                currentDay.setDate(currentDay.getDate() + 7);
                continue;
            }

            let daySessions = sessions.slice();
            if (remainingLessons < lessonsPerDay) {
                daySessions = daySessions.slice(0, remainingLessons);
            }

            scheduleDates.push(dateStr);
            remainingLessons -= lessonsPerDay;
            currentDay.setDate(currentDay.getDate() + 7);
            if (remainingLessons <= 0) break;
        }

        if (scheduleDates.length === 0) {
            return null;
        }

        return {
            start_date: scheduleDates[0],
            end_date: scheduleDates[scheduleDates.length - 1],
            weekday: parseInt(weekday),
            sessions: sessions.join(", "),
            room: room,
            subject: subjectName
        };
    }

    // Hàm reset các trường nhập liệu
    function resetForm() {
        searchSubject.value = "";
        sessionSelect.selectedIndex = -1; // Bỏ chọn tất cả các tiết
        daySelect.selectedIndex = 0; // Chọn lại giá trị mặc định (rỗng)
        roomSelect.selectedIndex = 0; // Chọn lại giá trị mặc định (rỗng)
        startDateInput.value = ""; // Xóa ngày bắt đầu
        subjectList.innerHTML = ""; // Xóa danh sách gợi ý môn học
    }

    // Thêm hàm kiểm tra và điều chỉnh ngày bắt đầu
    function adjustStartDate(startDate, weekday) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        let selectedDate = new Date(startDate);
        selectedDate.setHours(0, 0, 0, 0);

        // Tìm ngày của thứ đã chọn trong tuần của ngày bắt đầu
        const selectedDay = parseInt(weekday);
        const currentDay = selectedDate.getDay() || 7; // Chuyển chủ nhật từ 0 thành 7
        const daysToAdd = selectedDay - currentDay;
        selectedDate.setDate(selectedDate.getDate() + daysToAdd);

        // Nếu ngày đã chọn ở quá khứ, tự động nhảy đến tuần kế tiếp
        if (selectedDate < today) {
            selectedDate.setDate(selectedDate.getDate() + 7);
        }

        return selectedDate;
    }

    // Thêm event listener cho input ngày bắt đầu
    startDateInput.addEventListener('change', function() {
        const weekday = daySelect.value;
        if (weekday && this.value) {
            const adjustedDate = adjustStartDate(this.value, weekday);
            this.value = adjustedDate.toISOString().split('T')[0];
        }
    });

    // Thêm event listener cho select thứ
    daySelect.addEventListener('change', function() {
        const startDate = startDateInput.value;
        if (startDate && this.value) {
            const adjustedDate = adjustStartDate(startDate, this.value);
            startDateInput.value = adjustedDate.toISOString().split('T')[0];
        }
    });

    // Cập nhật hàm saveSchedule
    function saveSchedule(schedule, overwrite = false) {
        const saveData = {
            ...schedule,
            overwrite: overwrite
        };

        fetch("save_schedule.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(saveData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(
                    overwrite 
                        ? "Đã cập nhật thời khóa biểu! Lịch cũ vẫn được giữ đến trước ngày áp dụng lịch mới."
                        : "Đã thêm vào thời khóa biểu!"
                );
                resetForm();
                const selectedDay = parseInt(document.querySelector(".day.selected").textContent);
                updateSchedule(selectedDay);
            } else {
                alert("Lỗi: " + data.message);
            }
        })
        .catch(error => {
            alert("Đã xảy ra lỗi khi thêm thời khóa biểu: " + error.message);
        });
    }

    // Cập nhật hàm kiểm tra trùng lịch
    addScheduleBtn.addEventListener("click", function () {
        let subject = searchSubject.value.trim();
        let sessions = Array.from(sessionSelect.selectedOptions).map(opt => opt.textContent);
        let weekday = daySelect.value;
        let room = roomSelect.value;
        let startDate = startDateInput.value;

        // Kiểm tra validate các trường input
        if (!subject) {
            alert("Vui lòng nhập môn học!");
            return;
        }
        if (sessions.length === 0) {
            alert("Vui lòng chọn ít nhất một tiết học!");
            return;
        }
        if (!weekday) {
            alert("Vui lòng chọn thứ trong tuần!");
            return;
        }
        if (!room) {
            alert("Vui lòng chọn phòng học!");
            return;
        }
        if (!startDate) {
            alert("Vui lòng chọn ngày bắt đầu!");
            return;
        }

        // Kiểm tra ngày bắt đầu không được ở quá khứ
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selectedDate = new Date(startDate);
        selectedDate.setHours(0, 0, 0, 0);

        if (selectedDate < today) {
            alert("Không thể thêm lịch học cho ngày đã qua!");
            return;
        }

        const schedule = calculateSchedule(subject, startDate, weekday, sessions, room);
        if (!schedule) {
            alert("Không thể tạo lịch học! Kiểm tra môn học, ngày bắt đầu hoặc ngày nghỉ lễ.");
            return;
        }

        // Kiểm tra trùng lịch
        fetch("check_schedule_conflict.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(schedule)
        })
        .then(response => response.json())
        .then(data => {
            if (data.hasConflict) {
                const confirmOverwrite = confirm(
                    `Phát hiện trùng lịch!\n\n` +
                    `Môn học hiện tại: ${data.conflictDetails.subject}\n` +
                    `Thời gian: ${data.conflictDetails.time}\n` +
                    `Phòng: ${data.conflictDetails.room}\n\n` +
                    `Nếu tiếp tục:\n` +
                    `- Lịch cũ sẽ được giữ nguyên đến ${new Date(schedule.start_date).toLocaleDateString('vi-VN')}\n` +
                    `- Từ ${new Date(schedule.start_date).toLocaleDateString('vi-VN')} sẽ áp dụng lịch mới\n\n` +
                    `Bạn có muốn tiếp tục không?`
                );

                if (confirmOverwrite) {
                    saveSchedule(schedule, true);
                }
            } else {
                saveSchedule(schedule, false);
            }
        })
        .catch(error => {
            alert("Đã xảy ra lỗi khi kiểm tra trùng lịch: " + error.message);
        });
    });
});