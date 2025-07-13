<?php
session_start();
require_once 'config/database.php';

// Sample schedule data (in real app, get from database)
$schedule = [
    'Monday' => [
        ['time' => '8:00-9:00', 'subject' => 'Mathematics', 'teacher' => 'Mrs. Santos', 'room' => 'Room 101'],
        ['time' => '9:00-10:00', 'subject' => 'English', 'teacher' => 'Mr. Garcia', 'room' => 'Room 102'],
        ['time' => '10:00-11:00', 'subject' => 'Science', 'teacher' => 'Mrs. Lopez', 'room' => 'Room 103'],
        ['time' => '11:00-12:00', 'subject' => 'Filipino', 'teacher' => 'Mrs. Reyes', 'room' => 'Room 104']
    ],
    'Tuesday' => [
        ['time' => '8:00-9:00', 'subject' => 'Filipino', 'teacher' => 'Mrs. Reyes', 'room' => 'Room 104'],
        ['time' => '9:00-10:00', 'subject' => 'Mathematics', 'teacher' => 'Mrs. Santos', 'room' => 'Room 101'],
        ['time' => '10:00-11:00', 'subject' => 'Arts', 'teacher' => 'Mr. Cruz', 'room' => 'Art Room'],
        ['time' => '11:00-12:00', 'subject' => 'Physical Education', 'teacher' => 'Coach Rivera', 'room' => 'Gym']
    ],
    'Wednesday' => [
        ['time' => '8:00-9:00', 'subject' => 'Science', 'teacher' => 'Mrs. Lopez', 'room' => 'Room 103'],
        ['time' => '9:00-10:00', 'subject' => 'English', 'teacher' => 'Mr. Garcia', 'room' => 'Room 102'],
        ['time' => '10:00-11:00', 'subject' => 'Araling Panlipunan', 'teacher' => 'Mr. Torres', 'room' => 'Room 105'],
        ['time' => '11:00-12:00', 'subject' => 'Music', 'teacher' => 'Ms. Hernandez', 'room' => 'Music Room']
    ],
    'Thursday' => [
        ['time' => '8:00-9:00', 'subject' => 'Mathematics', 'teacher' => 'Mrs. Santos', 'room' => 'Room 101'],
        ['time' => '9:00-10:00', 'subject' => 'TLE', 'teacher' => 'Mr. Valdez', 'room' => 'TLE Room'],
        ['time' => '10:00-11:00', 'subject' => 'ESP', 'teacher' => 'Mrs. Morales', 'room' => 'Room 106'],
        ['time' => '11:00-12:00', 'subject' => 'Health', 'teacher' => 'Mrs. Aquino', 'room' => 'Room 107']
    ],
    'Friday' => [
        ['time' => '8:00-9:00', 'subject' => 'English', 'teacher' => 'Mr. Garcia', 'room' => 'Room 102'],
        ['time' => '9:00-10:00', 'subject' => 'Science', 'teacher' => 'Mrs. Lopez', 'room' => 'Room 103'],
        ['time' => '10:00-11:00', 'subject' => 'Filipino', 'teacher' => 'Mrs. Reyes', 'room' => 'Room 104'],
        ['time' => '11:00-12:00', 'subject' => 'MAPEH Review', 'teacher' => 'Various', 'room' => 'Various']
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Schedule - ERUDLITE</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/schedule.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <div class="topBar">
        <div class="title">ERUDLITE</div>
        <div class="navBar">
            <a href="quickAccess.php">Home</a>
            <a href="about.php">About</a>
            <a href="studentDashboard.php"><img src="assets/profile.png" alt="Profile" class="logo"></a>
        </div>
    </div>

    <main>
        <div class="schedule-container">
            <h1><i class="fas fa-calendar-alt"></i> Class Schedule</h1>
            
            <div class="schedule-grid">
                <?php foreach ($schedule as $day => $classes): ?>
                    <div class="day-schedule">
                        <h2><?= $day ?></h2>
                        <div class="classes-list">
                            <?php foreach ($classes as $class): ?>
                                <div class="class-item">
                                    <div class="class-time">
                                        <i class="fas fa-clock"></i>
                                        <?= htmlspecialchars($class['time']) ?>
                                    </div>
                                    <div class="class-details">
                                        <h3><?= htmlspecialchars($class['subject']) ?></h3>
                                        <p><i class="fas fa-user"></i> <?= htmlspecialchars($class['teacher']) ?></p>
                                        <p><i class="fas fa-door-open"></i> <?= htmlspecialchars($class['room']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> ERUDLITE PMS</p>
    </footer>
</body>
</html>