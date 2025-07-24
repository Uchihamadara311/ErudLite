<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMS</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/studentReport.css">
</head>
<body>
    <header id="header-placeholder"></header>
    <main style="border: 50px">
        <div class="topReport">
            <section style="text-align: center">
                <h1>Student List for ___ Class</h1>
            </section>
            <section style="display: flex; justify-content: center; gap: 20px">
                <button>Download PDF</button>
                <button>Print Report</button>
            </section>
        </div>
        <a href="studentDashboard.php" class="backButton">
            <i class="fa fa-arrow-left" style="margin-right: 10px"></i>Back to Dashboard
        </a>
        <div style="width: 99%; background-color: rgb(244, 244, 244); padding: 20px; border-radius: 10px; margin-top: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Last Name</th>
                        <th>First Name</th>
                        <th>Middle Initial</th>
                        <th>Birth Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>-----</td>
                        <td>Doe</td>
                        <td>John</td>
                        <td>A.</td>
                        <td>01/01/25</td>
                        <td>Active</td>
                    </tr>
                </tbody>
            </table>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
</body>
</html>