<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Report Card - ERUDLITE</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/reportCard.css">
</head>
<body>
    <header id="header-placeholder"></header>
    <a href="studentDashboard.php" class="backButton">
        <i class="fa fa-arrow-left" style="margin-right: 10px"></i>Back to Dashboard
    </a>
    <main class="report-container">
        <div class="report-header">
            <h1>SUBJECT REQUIREMENTS</h1>
            <div class="action-buttons">
                <button class="btn-primary" onclick="downloadPDF()">
                    <i class="fas fa-download"></i> Download PDF
                </button>
                <button class="btn-secondary" onclick="printReport()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>

        <div class="student-info-card">
            <h3>Subject Information</h3>
            <div class="student-info">
                <div class="info-section">
                    <label>Quarter:</label>
                    <select id="quarter">
                        <option value="">Select term</option>
                        <option value="1">1st</option>
                        <option value="2">2nd</option>
                        <option value="3">3rd</option>
                        <option value="4">4th</option>
                    </select>
                </div>
                <div class="info-section">
                    <label>Subject:</label>
                    <input type="text" id="subjectName" placeholder="Enter subject name">
                </div>
                <div class="info-section">
                    <label>School Year:</label>
                    <input type="text" id="schoolYear" placeholder="2024-2025">
                </div>
                <div class="info-section">
                    <label>Teacher:</label>
                    <input type="text" id="teacher" placeholder="Enter teacher name">
                </div>
            </div>
        </div>

        <div class="grades-card">
            <h3>Academic Performance</h3>
            <div class="grades-table">
                <table id="reportTable">
                    <h3 style="padding: 4px;">Mathematics - Fourth Quarter</h3>
                    <thead>
                        <tr>
                            <th>Assignments</th>
                            <th>Status</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Test</td>
                            <td>Not Submitted</td>
                            <td>N/A</td>
                        </tr>
                        <tr>
                            <td>Project</td>
                            <td>Pending</td>
                            <td>N/A</td>
                        </tr>
                        <tr>
                            <td>Homework</td>
                            <td>Submitted</td>
                            <td>14/25</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="report-actions">
            <button class="btn-success" onclick="saveReport()">
                <i class="fas fa-save"></i> Save Report
            </button>
            <button class="btn-warning" onclick="loadReport()">
                <i class="fas fa-folder-open"></i> Load Report
            </button>
            <button class="btn-primary" onclick="generateSample()">
                <i class="fas fa-magic"></i> Generate Sample
            </button>
            <button class="btn-danger" onclick="clearReport()">
                <i class="fas fa-trash"></i> Clear All
            </button>
        </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script src="js/reportCard.js"></script>
</body>
</html>