<?php
require_once 'includes/db.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Ensure user is logged in and has admin permissions
if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: quickAccess.php");
    exit();
}

// Initialize messages
$success_message = '';
$error_message = '';

// Handle form submission for adding/updating/deleting schedules
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = isset($_POST['operation']) ? $_POST['operation'] : 'add_schedule';
    $redirect_year = isset($_GET['year']) ? $_GET['year'] : date('Y') . '-' . (date('Y') + 1);

    $conn->begin_transaction();

    try {
        if ($operation == 'add_schedule' || $operation == 'update_schedule') {
            $instructor_id = (int)$_POST['instructor_id'];
            $subject_id = (int)$_POST['subject_id'];
            $class_id = (int)$_POST['class_id'];
            $selected_days = isset($_POST['days']) ? $_POST['days'] : [];
            $start_time = cleanInput($_POST['start_time']);
            $end_time = cleanInput($_POST['end_time']);
            
            // For updates, we need the original keys to find the record
            $original_schedule_id = isset($_POST['original_schedule_id']) ? (int)$_POST['original_schedule_id'] : 0;
            $original_day = isset($_POST['original_day']) ? cleanInput($_POST['original_day']) : '';
            $original_start_time = isset($_POST['original_start_time']) ? cleanInput($_POST['original_start_time']) : '';
            $original_end_time = isset($_POST['original_end_time']) ? cleanInput($_POST['original_end_time']) : '';

            if (empty($instructor_id) || empty($subject_id) || empty($class_id) || empty($selected_days) || empty($start_time) || empty($end_time)) {
                throw new Exception("All fields are required, including at least one day.");
            }

            // Validate that end time is after start time
            if (strtotime($end_time) <= strtotime($start_time)) {
                throw new Exception("End time must be after start time.");
            }

            // Clean the selected days
            $clean_days = [];
            foreach ($selected_days as $day) {
                $clean_day = cleanInput($day);
                if (in_array($clean_day, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])) {
                    $clean_days[] = $clean_day;
                }
            }

            if (empty($clean_days)) {
                throw new Exception("Please select at least one valid day.");
            }

            // Check for conflicts for each selected day (check for time overlap)
            foreach ($clean_days as $day) {
                $conflict_sql = "SELECT sd.Schedule_ID
                                FROM schedule_details sd
                                JOIN schedule s ON sd.Schedule_ID = s.Schedule_ID
                                WHERE s.Instructor_ID = ? AND sd.Day = ? 
                                AND (? < sd.End_Time AND ? > sd.Start_Time)";
                
                // For updates, exclude the original record being modified
                if ($operation == 'update_schedule' && !empty($original_schedule_id)) {
                    $conflict_sql .= " AND NOT (s.Schedule_ID = ? AND sd.Day = ? AND sd.Start_Time = ? AND sd.End_Time = ?)";
                    $stmt = $conn->prepare($conflict_sql);
                    $stmt->bind_param("isssssss", $instructor_id, $day, $start_time, $end_time, $original_schedule_id, $original_day, $original_start_time, $original_end_time);
                } else {
                    $stmt = $conn->prepare($conflict_sql);
                    $stmt->bind_param("isss", $instructor_id, $day, $start_time, $end_time);
                }
                
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("Schedule conflict detected! The instructor already has an overlapping class on $day between $start_time and $end_time.");
                }
            }

            if ($operation == 'add_schedule') {
                // Create a new schedule entry
                $stmt = $conn->prepare("INSERT INTO schedule (Instructor_ID, Subject_ID, Class_ID) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $instructor_id, $subject_id, $class_id);
                $stmt->execute();
                $schedule_id = $conn->insert_id;
                
                // Insert schedule details for each selected day
                $sql = "INSERT INTO schedule_details (Schedule_ID, Start_Time, End_Time, Day) VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($sql);
                
                foreach ($clean_days as $day) {
                    $insert_stmt->bind_param("isss", $schedule_id, $start_time, $end_time, $day);
                    if (!$insert_stmt->execute()) {
                        throw new Exception("Error creating schedule details for $day: " . $insert_stmt->error);
                    }
                }
                
                $day_count = count($clean_days);
                $_SESSION['success_message'] = "Schedule created successfully for $day_count day(s)!";
            } else { // update_schedule
                // For updates, we'll delete the old entry and insert new ones
                $delete_sql = "DELETE FROM schedule_details WHERE Schedule_ID = ? AND Start_Time = ? AND End_Time = ? AND Day = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("isss", $original_schedule_id, $original_start_time, $original_end_time, $original_day);
                if (!$delete_stmt->execute()) {
                    throw new Exception("Error deleting old schedule: " . $delete_stmt->error);
                }
                
                // Update the schedule record
                $update_schedule_sql = "UPDATE schedule SET Instructor_ID = ?, Subject_ID = ?, Class_ID = ? WHERE Schedule_ID = ?";
                $update_schedule_stmt = $conn->prepare($update_schedule_sql);
                $update_schedule_stmt->bind_param("iiii", $instructor_id, $subject_id, $class_id, $original_schedule_id);
                if (!$update_schedule_stmt->execute()) {
                    throw new Exception("Error updating schedule: " . $update_schedule_stmt->error);
                }
                
                // Insert new schedule details for each selected day
                $sql = "INSERT INTO schedule_details (Schedule_ID, Start_Time, End_Time, Day) VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($sql);
                
                foreach ($clean_days as $day) {
                    $insert_stmt->bind_param("isss", $original_schedule_id, $start_time, $end_time, $day);
                    if (!$insert_stmt->execute()) {
                        throw new Exception("Error updating schedule details for $day: " . $insert_stmt->error);
                    }
                }
                
                $day_count = count($clean_days);
                $_SESSION['success_message'] = "Schedule updated successfully for $day_count day(s)!";
            }
        } elseif ($operation == 'delete_schedule') {
            $schedule_id = (int)$_POST['schedule_id'];
            $day = cleanInput($_POST['day']);
            $start_time = cleanInput($_POST['start_time']);
            $end_time = cleanInput($_POST['end_time']);

            $sql = "DELETE FROM schedule_details WHERE Schedule_ID = ? AND Start_Time = ? AND End_Time = ? AND Day = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $schedule_id, $start_time, $end_time, $day);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Schedule deleted successfully!";
            } else {
                throw new Exception("Error deleting schedule: " . $stmt->error);
            }
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header("Location: adminSchedule.php?year=" . urlencode($redirect_year));
    exit();
}

// Retrieve messages from session and then unset them
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// --- Data Fetching for Form Population ---

// Get academic year filter
$selected_year = isset($_GET['year']) ? cleanInput($_GET['year']) : date('Y') . '-' . (date('Y') + 1);

// Get instructors
$instructors_sql = "SELECT i.Instructor_ID, pb.Given_Name, pb.Last_Name
                     FROM Instructor i
                     JOIN Profile p ON i.Profile_ID = p.Profile_ID
                     JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                     ORDER BY pb.Last_Name, pb.Given_Name";
$instructors_result = $conn->query($instructors_sql);

// Get all schedules for the main table display
$schedules_sql = "SELECT sd.Day, sd.Start_Time, sd.End_Time,
                         s.Schedule_ID, s.Instructor_ID, s.Subject_ID, s.Class_ID,
                         ipb.Given_Name as Instructor_First, ipb.Last_Name as Instructor_Last,
                         sub.Subject_Name, cl.Grade_Level, cr.Section, cr.Room
                  FROM schedule_details sd
                  JOIN schedule s ON sd.Schedule_ID = s.Schedule_ID
                  JOIN Instructor i ON s.Instructor_ID = i.Instructor_ID
                  JOIN Profile p ON i.Profile_ID = p.Profile_ID
                  JOIN Profile_Bio ipb ON p.Profile_ID = ipb.Profile_ID
                  JOIN Subject sub ON s.Subject_ID = sub.Subject_ID
                  JOIN Class c ON s.Class_ID = c.Class_ID
                  JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                  JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                  WHERE cl.School_Year = ?
                  ORDER BY sd.Day, sd.Start_Time";
$stmt_schedules = $conn->prepare($schedules_sql);
$stmt_schedules->bind_param("s", $selected_year);
$stmt_schedules->execute();
$schedules_result = $stmt_schedules->get_result();
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/adminLinks.css">
    <link rel="stylesheet" href="css/adminManagement.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 8px;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 500;
            color: #333;
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            transition: all 0.3s ease;
            background: #fff;
        }
        
        .checkbox-label:hover {
            border-color: #4CAF50;
            background: #f8f9fa;
        }
        
        .checkbox-label input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.2);
            accent-color: #4CAF50;
        }
        
        .checkbox-label input[type="checkbox"]:checked + span,
        .checkbox-label:has(input[type="checkbox"]:checked) {
            background: #e8f5e8;
            border-color: #4CAF50;
            color: #2e7d32;
        }
    </style>
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
        <div class="admin-back-btn-wrap admin-back-btn-upperleft">
            <a href="adminLinks.php" class="admin-back-btn"><i class="fa fa-arrow-left"></i> Back to Admin Dashboard</a>
        </div>
        <h1 class="page-title">Schedule Management System</h1>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-filter"></i> Filter by Academic Year</h2>
            <form method="GET" action="adminSchedule.php" id="year-filter-form" style="padding: 20px;">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="year"><i class="fas fa-calendar-alt"></i> Academic Year</label>
                        <select class="form-select" name="year" id="year" onchange="this.form.submit()">
                            <?php
                            $years_sql = "SELECT DISTINCT School_Year FROM Clearance ORDER BY School_Year DESC";
                            $years_result_dd = $conn->query($years_sql);
                            if ($years_result_dd) {
                                while($year = $years_result_dd->fetch_assoc()) {
                                    $is_selected = ($year['School_Year'] == $selected_year) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($year['School_Year']) . "' $is_selected>" . htmlspecialchars($year['School_Year']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </form>
        </section>

        <section class="form-section">
            <h2 class="form-title" id="form-title"><i class="fas fa-calendar-plus"></i> Create New Schedule</h2>
            <form method="POST" action="adminSchedule.php?year=<?php echo urlencode($selected_year); ?>" id="schedule-form">
                <input type="hidden" id="operation" name="operation" value="add_schedule">
                <input type="hidden" id="original_schedule_id" name="original_schedule_id" value="">
                <input type="hidden" id="original_day" name="original_day" value="">
                <input type="hidden" id="original_start_time" name="original_start_time" value="">
                <input type="hidden" id="original_end_time" name="original_end_time" value="">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="instructor_id"><i class="fas fa-user-tie"></i> Instructor *</label>
                        <select class="form-select" name="instructor_id" id="instructor_id" required onchange="handleInstructorChange()">
                            <option value="">Select an Instructor</option>
                            <?php 
                            if ($instructors_result && $instructors_result->num_rows > 0) {
                                $instructors_result->data_seek(0);
                                while($instructor = $instructors_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $instructor['Instructor_ID']; ?>">
                                    <?php echo htmlspecialchars($instructor['Given_Name'] . ' ' . $instructor['Last_Name']); ?>
                                </option>
                            <?php 
                                endwhile; 
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="subject_id"><i class="fas fa-book"></i> Subject *</label>
                        <select class="form-select" name="subject_id" id="subject_id" required onchange="handleSubjectChange()" disabled>
                            <option value="">Select a Subject</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="class_id"><i class="fas fa-school"></i> Class *</label>
                        <select class="form-select" name="class_id" id="class_id" required disabled>
                            <option value="">Select a Class</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="days"><i class="fas fa-calendar-day"></i> Days *</label>
                        <div class="checkbox-group" id="days">
                            <label class="checkbox-label">
                                <input type="checkbox" name="days[]" value="Monday" class="day-checkbox"> Monday
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="days[]" value="Tuesday" class="day-checkbox"> Tuesday
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="days[]" value="Wednesday" class="day-checkbox"> Wednesday
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="days[]" value="Thursday" class="day-checkbox"> Thursday
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="days[]" value="Friday" class="day-checkbox"> Friday
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="start_time"><i class="fas fa-clock"></i> Start Time *</label>
                        <input type="time" class="form-select" name="start_time" id="start_time" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="end_time"><i class="fas fa-clock"></i> End Time *</label>
                        <input type="time" class="form-select" name="end_time" id="end_time" required>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="submit-btn" id="submit-btn"><i class="fas fa-save"></i> Create Schedule</button>
                    <button type="button" class="cancel-btn" onclick="resetForm()"><i class="fas fa-times"></i> Reset</button>
                </div>
            </form>
        </section>

        <section class="table-section">
            <div class="section-header">
                <div class="header-icon-title">
                    <i class="fas fa-calendar-alt"></i>
                    <h2>Current Schedules (<?php echo htmlspecialchars($selected_year); ?>)</h2>
                </div>
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchBar" class="search-input" placeholder="Search schedules...">
                </div>
            </div>
            <div class="table-container">
                <table class="data-table" id="schedules-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user-tie"></i> Instructor</th>
                            <th><i class="fas fa-book"></i> Subject</th>
                            <th><i class="fas fa-school"></i> Class</th>
                            <th><i class="fas fa-calendar-day"></i> Day</th>
                            <th><i class="fas fa-clock"></i> Time Period</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($schedules_result && $schedules_result->num_rows > 0) {
                            while($schedule = $schedules_result->fetch_assoc()): 
                        ?>
                            <tr style="cursor:pointer;" onclick='editSchedule(<?php echo json_encode($schedule); ?>)'>
                                <td><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($schedule['Instructor_First'] . ' ' . $schedule['Instructor_Last']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['Subject_Name']); ?></td>
                                <td>Grade <?php echo htmlspecialchars($schedule['Grade_Level']); ?> - <?php echo htmlspecialchars($schedule['Section']); ?> (Room <?php echo htmlspecialchars($schedule['Room']); ?>)</td>
                                <td><?php echo htmlspecialchars($schedule['Day']); ?></td>
                                <td><?php echo date('g:i A', strtotime($schedule['Start_Time'])) . ' - ' . date('g:i A', strtotime($schedule['End_Time'])); ?></td>
                                <td class="action-buttons">
                                    <button class="edit-btn" onclick="event.stopPropagation(); editSchedule(<?php echo htmlspecialchars(json_encode($schedule)); ?>)"><i class="fas fa-edit"></i> Edit</button>
                                    <button class="delete-btn" onclick="event.stopPropagation(); deleteSchedule(<?php echo $schedule['Schedule_ID']; ?>, '<?php echo $schedule['Day']; ?>', '<?php echo $schedule['Start_Time']; ?>', '<?php echo $schedule['End_Time']; ?>')"><i class="fas fa-trash"></i> Delete</button>
                                </td>
                            </tr>
                        <?php 
                            endwhile;
                        } else {
                            echo "<tr><td colspan='6' class='no-data'><i class='fas fa-info-circle'></i> No schedules found for this academic year.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script>
        // Get the current academic year for AJAX requests
        const currentYear = '<?php echo htmlspecialchars($selected_year); ?>';

        function handleInstructorChange() {
            const instructorId = document.getElementById('instructor_id').value;
            const subjectSelect = document.getElementById('subject_id');
            const classSelect = document.getElementById('class_id');
            
            // Reset subsequent dropdowns
            subjectSelect.innerHTML = '<option value="">Select a Subject</option>';
            subjectSelect.disabled = true;
            classSelect.innerHTML = '<option value="">Select a Class</option>';
            classSelect.disabled = true;
            
            if (instructorId) {
                // Show loading indicator
                subjectSelect.innerHTML = '<option value="">Loading subjects...</option>';
                
                // Make AJAX request to get subjects
                fetch('ajax_schedule_handlers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_subjects&instructor_id=${instructorId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        subjectSelect.innerHTML = '<option value="">Select a Subject</option>';
                        data.subjects.forEach(subject => {
                            const option = document.createElement('option');
                            option.value = subject.Subject_ID;
                            option.textContent = `${subject.Subject_Name} (Grade ${subject.Grade_Level})`;
                            subjectSelect.appendChild(option);
                        });
                        subjectSelect.disabled = false;
                    } else {
                        subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
                });
            }
        }

        function handleSubjectChange() {
            const subjectId = document.getElementById('subject_id').value;
            const classSelect = document.getElementById('class_id');
            
            // Reset class dropdown
            classSelect.innerHTML = '<option value="">Select a Class</option>';
            classSelect.disabled = true;
            
            if (subjectId) {
                // Show loading indicator
                classSelect.innerHTML = '<option value="">Loading classes...</option>';
                
                // Make AJAX request to get classes
                fetch('ajax_schedule_handlers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_classes&subject_id=${subjectId}&year=${encodeURIComponent(currentYear)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        classSelect.innerHTML = '<option value="">Select a Class</option>';
                        data.classes.forEach(classItem => {
                            const option = document.createElement('option');
                            option.value = classItem.Class_ID;
                            option.textContent = `Grade ${classItem.Grade_Level} - ${classItem.Section} (Room ${classItem.Room})`;
                            classSelect.appendChild(option);
                        });
                        classSelect.disabled = false;
                    } else {
                        classSelect.innerHTML = '<option value="">Error loading classes</option>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    classSelect.innerHTML = '<option value="">Error loading classes</option>';
                });
            }
        }

        function editSchedule(scheduleData) {
            document.getElementById('operation').value = 'update_schedule';
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Schedule';
            document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> Edit Schedule';

            // Store original keys for the update WHERE clause
            document.getElementById('original_schedule_id').value = scheduleData.Schedule_ID;
            document.getElementById('original_day').value = scheduleData.Day;
            document.getElementById('original_start_time').value = scheduleData.Start_Time;
            document.getElementById('original_end_time').value = scheduleData.End_Time;

            // Set simple values
            document.getElementById('start_time').value = scheduleData.Start_Time;
            document.getElementById('end_time').value = scheduleData.End_Time;

            // Set the day checkbox
            const dayCheckboxes = document.querySelectorAll('.day-checkbox');
            dayCheckboxes.forEach(checkbox => {
                checkbox.checked = (checkbox.value === scheduleData.Day);
            });

            // Set instructor first
            const instructorSelect = document.getElementById('instructor_id');
            instructorSelect.value = scheduleData.Instructor_ID;
            
            // Load subjects for this instructor
            handleInstructorChange();
            
            // After subjects load, set the subject value
            setTimeout(() => {
                const subjectSelect = document.getElementById('subject_id');
                subjectSelect.value = scheduleData.Subject_ID;
                
                // Load classes for this subject
                handleSubjectChange();
                
                // After classes load, set the class value
                setTimeout(() => {
                    const classSelect = document.getElementById('class_id');
                    classSelect.value = scheduleData.Class_ID;
                }, 500);
            }, 500);
            
            // Scroll to form
            setTimeout(() => {
                const formSection = document.querySelector('.form-section:nth-of-type(2)');
                const header = document.querySelector('#header-placeholder');
                const headerHeight = header ? header.offsetHeight : 0;
                const offset = headerHeight + 20;
                
                window.scrollTo({
                    top: formSection.offsetTop - offset,
                    behavior: 'smooth'
                });
            }, 100);
        }

        function deleteSchedule(scheduleId, day, startTime, endTime) {
            if(confirm('Are you sure you want to delete this schedule entry?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'adminSchedule.php?year=<?php echo urlencode($selected_year); ?>';

                const operationInput = document.createElement('input');
                operationInput.type = 'hidden';
                operationInput.name = 'operation';
                operationInput.value = 'delete_schedule';
                form.appendChild(operationInput);

                const scheduleIdInput = document.createElement('input');
                scheduleIdInput.type = 'hidden';
                scheduleIdInput.name = 'schedule_id';
                scheduleIdInput.value = scheduleId;
                form.appendChild(scheduleIdInput);

                const dayInput = document.createElement('input');
                dayInput.type = 'hidden';
                dayInput.name = 'day';
                dayInput.value = day;
                form.appendChild(dayInput);

                const startTimeInput = document.createElement('input');
                startTimeInput.type = 'hidden';
                startTimeInput.name = 'start_time';
                startTimeInput.value = startTime;
                form.appendChild(startTimeInput);

                const endTimeInput = document.createElement('input');
                endTimeInput.type = 'hidden';
                endTimeInput.name = 'end_time';
                endTimeInput.value = endTime;
                form.appendChild(endTimeInput);

                document.body.appendChild(form);
                form.submit();
            }
        }

        function resetForm() {
            const year = document.getElementById('year').value;
            window.location.href = 'adminSchedule.php?year=' + encodeURIComponent(year);
        }

        // Form validation
        document.getElementById('schedule-form').addEventListener('submit', function(e) {
            const checkedDays = document.querySelectorAll('.day-checkbox:checked');
            if (checkedDays.length === 0) {
                e.preventDefault();
                alert('Please select at least one day for the schedule.');
                return false;
            }
            
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            
            if (startTime && endTime && startTime >= endTime) {
                e.preventDefault();
                alert('End time must be after start time.');
                return false;
            }
        });

        // Search functionality
        document.getElementById('searchBar').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll("#schedules-table tbody tr");
            rows.forEach(function(row) {
                let text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(filter) > -1 ? "" : "none";
            });
        });
    </script>
</body>
</html>