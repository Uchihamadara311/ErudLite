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
                <h1>Student Report</h1>
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
                        <th rowspan="2">Learning Area&nbsp;</th>
                        <th colspan="4">Grading Period</th>
                        <th rowspan="2">Final Rating</th>
                        <th rowspan="2">REMARKS</th>
                    </tr>
                    <tr>
                        <th>First Quarter</th>
                        <th>Second Quarter</th>
                        <th>Third Quarter</th>
                        <th>Fourth Quarter</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Mathematics</td>
                        <td>90.5</td>
                        <td>90.5</td>
                        <td>90.5</td>
                        <td>90.5</td>
                        <td>90.5</td>
                        <td>Promoted</td>
                    </tr>
                </tbody>
            </table>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
</body>
</html>