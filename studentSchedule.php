<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMS</title>
    <link rel="stylesheet" href="css/studentSchedule.css">
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap">
</head>
<body>
    <header id="header-placeholder"></header>
    <main style="border: 50px">
        <div class="topSchedule">
            <sectio style="text-align: center">
                <h1>Student Schedule</h1>
            </section>
            <section style="display: flex; justify-content: center; gap: 20px">
                <button>Download PDF</button>
                <button>Print Schedule</button>
            </section>
        </div>
        <div style="width: 99%; background-color: rgb(244, 244, 244); padding: 20px; border-radius: 10px; margin-top: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <table>
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Time</th>
                        <th>Room</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Mathematics</td>
                        <td>9:00 AM - 10:30 AM</td>
                        <td>Room 101</td>
                    </tr>
                    <tr>
                        <td>Science</td>
                        <td>10:45 AM - 12:15 PM</td>
                        <td>Room 102</td>
                    </tr>
                    <tr>
                        <td>History</td>
                        <td>1:00 PM - 2:30 PM</td>
                        <td>Room 103</td>
                    </tr>
                </tbody>
            </table>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
</body>
</html>