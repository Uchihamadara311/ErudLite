<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Report Card - ERUDLITE</title>
    <link rel="stylesheet" href="css/essential.css">
    <!-- <link rel="stylesheet" href="css/reportCard.css"> -->
     <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap">
</head>
<body>
    <header id="header-placeholder"></header>
    <main class="report-container">
        <div class="report-header">
            <h1>DIGITAL REPORT CARD</h1>
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
            <h3>Student Information</h3>
            <div class="student-info">
                <div class="info-section">
                    <label>Student Name:</label>
                    <input type="text" id="studentName" placeholder="Enter full name">
                </div>
                <div class="info-section">
                    <label>Student ID:</label>
                    <input type="text" id="studentId" placeholder="Enter ID number">
                </div>
                <div class="info-section">
                    <label>Grade Level:</label>
                    <select id="gradeLevel">
                        <option value="">Select Grade</option>
                        <option value="Grade 1">Grade 1</option>
                        <option value="Grade 2">Grade 2</option>
                        <option value="Grade 3">Grade 3</option>
                        <option value="Grade 4">Grade 4</option>
                        <option value="Grade 5">Grade 5</option>
                        <option value="Grade 6">Grade 6</option>
                    </select>
                </div>
                <div class="info-section">
                    <label>Section:</label>
                    <input type="text" id="section" placeholder="Enter section">
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
                    <thead>
                        <tr>
                            <th rowspan="2">Learning Area</th>
                            <th colspan="4">GRADING PERIOD</th>
                            <th rowspan="2">Final Rating</th>
                            <th rowspan="2">REMARKS</th>
                        </tr>
                        <tr>
                            <th>1st</th>
                            <th>2nd</th>
                            <th>3rd</th>
                            <th>4th</th>
                        </tr>
                    </thead>
                    <tbody id="gradesBody">
                        <!-- Grades will be populated by JavaScript -->
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