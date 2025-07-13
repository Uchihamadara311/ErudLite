<?php
// Start the session to manage user authentication and data
session_start();

// Team members data (in real app, get from database)
$teamMembers = [
    [
        'name' => 'ELON MUSK',
        'position' => 'Innovation and Technology Leader',
        'image' => 'assets/profile.png',
        'description' => 'Leading innovation in educational technology'
    ],
    [
        'name' => 'JAMES BOND',
        'position' => 'Licensed Educational Consultant',
        'image' => 'assets/profile.png',
        'description' => 'Expert in educational systems and security'
    ],
    [
        'name' => 'JOHN DOE',
        'position' => 'Academic Excellence Coordinator',
        'image' => 'assets/profile.png',
        'description' => 'Ensuring academic excellence and student success'
    ],
    [
        'name' => 'JANE SMITH',
        'position' => 'Student Success Manager',
        'image' => 'assets/profile.png',
        'description' => 'Dedicated to student achievement and growth'
    ]
];

// School information
$schoolInfo = [
    'name' => 'ERUDLITE',
    'mission' => 'To provide quality education through innovative technology',
    'vision' => 'To be the leading educational institution in the digital age',
    'established' => '2020',
    'students' => '500+',
    'teachers' => '50+'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - ERUDLITE</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/about.css">
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
        <div class="school-info">
            <h1><?= $schoolInfo['name'] ?></h1>
            <div class="info-grid">
                <div class="info-card">
                    <h3>Mission</h3>
                    <p><?= $schoolInfo['mission'] ?></p>
                </div>
                <div class="info-card">
                    <h3>Vision</h3>
                    <p><?= $schoolInfo['vision'] ?></p>
                </div>
                <div class="info-card">
                    <h3>Established</h3>
                    <p><?= $schoolInfo['established'] ?></p>
                </div>
            </div>
        </div>

        <section class="team-section">
            <h2>Our Team</h2>
            <div class="placeholder">
                <?php foreach ($teamMembers as $member): ?>
                    <div class="team-member">
                        <img src="<?= $member['image'] ?>" alt="<?= $member['name'] ?>" class="profilePicture">
                        <h3><?= $member['name'] ?></h3>
                        <p class="position"><?= $member['position'] ?></p>
                        <p class="description"><?= $member['description'] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="stats-section">
            <h2>School Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?= $schoolInfo['students'] ?></h3>
                    <p>Students</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h3><?= $schoolInfo['teachers'] ?></h3>
                    <p>Teachers</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-alt"></i>
                    <h3><?= date('Y') - (int)$schoolInfo['established'] ?></h3>
                    <p>Years of Excellence</p>
                </div>
            </div>
        </section>
    </main>
    <footer>
        <p>&copy; <?= date('Y') ?> ERUDLITE PMS - Empowering Education Through Technology</p>
    </footer>
</body>
</html>