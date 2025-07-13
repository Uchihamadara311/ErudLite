// Elementary School Subjects Configuration
const subjects = [
    'Filipino',
    'English',
    'Mathematics',
    'Science',
    'Araling Panlipunan (AP)',
    'Edukasyon sa Pagpapakatao (ESP)',
    'Technology and Livelihood Education (TLE)',
    'MAPEH - Music',
    'MAPEH - Arts',
    'MAPEH - Physical Education',
    'MAPEH - Health'
];

// Grade scale for elementary
const gradeScale = {
    'Outstanding': { min: 90, max: 100, letter: 'A' },
    'Very Satisfactory': { min: 85, max: 89, letter: 'B' },
    'Satisfactory': { min: 80, max: 84, letter: 'C' },
    'Fairly Satisfactory': { min: 75, max: 79, letter: 'D' },
    'Did Not Meet Expectations': { min: 0, max: 74, letter: 'F' }
};

// Initialize the report card when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeReportCard();
    loadLastReport();
});

// Initialize the report card table
function initializeReportCard() {
    const gradesBody = document.getElementById('gradesBody');
    gradesBody.innerHTML = '';
    
    subjects.forEach((subject, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="subject-name">${subject}</td>
            <td><input type="number" class="grade-input" min="0" max="100" placeholder="0-100" onchange="calculateFinalRating(${index})"></td>
            <td><input type="number" class="grade-input" min="0" max="100" placeholder="0-100" onchange="calculateFinalRating(${index})"></td>
            <td><input type="number" class="grade-input" min="0" max="100" placeholder="0-100" onchange="calculateFinalRating(${index})"></td>
            <td><input type="number" class="grade-input" min="0" max="100" placeholder="0-100" onchange="calculateFinalRating(${index})"></td>
            <td class="final-rating">-</td>
            <td class="remarks-cell">-</td>
        `;
        gradesBody.appendChild(row);
    });
}

// Calculate final rating and determine remarks
function calculateFinalRating(rowIndex) {
    const rows = document.querySelectorAll('#gradesBody tr');
    const row = rows[rowIndex];
    const gradeInputs = row.querySelectorAll('.grade-input');
    const finalRatingCell = row.querySelector('.final-rating');
    const remarksCell = row.querySelector('.remarks-cell');
    
    let total = 0;
    let count = 0;
    let hasValidGrades = false;
    
    gradeInputs.forEach(input => {
        const value = parseFloat(input.value);
        if (!isNaN(value) && value >= 0 && value <= 100) {
            total += value;
            count++;
            hasValidGrades = true;
        }
    });
    
    if (hasValidGrades && count > 0) {
        const average = total / count;
        finalRatingCell.textContent = average.toFixed(2);
        
        // Determine remarks and styling
        let remarks = '';
        let remarkClass = '';
        
        if (average >= 75) {
            remarks = 'Promoted';
            remarkClass = 'remarks-promoted';
        } else {
            remarks = 'Needs Improvement';
            remarkClass = 'remarks-needs-improvement';
        }
        
        remarksCell.textContent = remarks;
        remarksCell.className = `remarks-cell ${remarkClass}`;
    } else {
        finalRatingCell.textContent = '-';
        remarksCell.textContent = '-';
        remarksCell.className = 'remarks-cell';
    }
}

// Save report to localStorage
function saveReport() {
    const studentData = getStudentData();
    
    if (!validateStudentData(studentData)) {
        alert('Please fill in all required student information fields.');
        return;
    }
    
    const reportData = {
        ...studentData,
        grades: getGradesData(),
        timestamp: new Date().toISOString()
    };
    
    const reportKey = `report_${studentData.studentId}_${Date.now()}`;
    localStorage.setItem(reportKey, JSON.stringify(reportData));
    localStorage.setItem('lastReport', reportKey);
    
    showNotification('Report saved successfully!', 'success');
}

// Load report from localStorage
function loadReport() {
    const reports = getStoredReports();
    
    if (reports.length === 0) {
        alert('No saved reports found.');
        return;
    }
    
    // Create a simple selection dialog
    const reportList = reports.map((report, index) => 
        `${index + 1}. ${report.data.studentName} (${report.data.gradeLevel}) - ${new Date(report.data.timestamp).toLocaleDateString()}`
    ).join('\n');
    
    const selection = prompt(`Select a report to load:\n\n${reportList}\n\nEnter the number:`);
    const selectedIndex = parseInt(selection) - 1;
    
    if (selectedIndex >= 0 && selectedIndex < reports.length) {
        loadReportData(reports[selectedIndex].data);
        showNotification('Report loaded successfully!', 'success');
    }
}

// Load the last saved report
function loadLastReport() {
    const lastReportKey = localStorage.getItem('lastReport');
    if (lastReportKey) {
        const reportData = JSON.parse(localStorage.getItem(lastReportKey));
        if (reportData) {
            loadReportData(reportData);
        }
    }
}

// Load report data into the form
function loadReportData(reportData) {
    // Load student information
    document.getElementById('studentName').value = reportData.studentName || '';
    document.getElementById('studentId').value = reportData.studentId || '';
    document.getElementById('gradeLevel').value = reportData.gradeLevel || '';
    document.getElementById('section').value = reportData.section || '';
    document.getElementById('schoolYear').value = reportData.schoolYear || '';
    document.getElementById('teacher').value = reportData.teacher || '';
    
    // Load grades
    const rows = document.querySelectorAll('#gradesBody tr');
    reportData.grades.forEach((gradeData, index) => {
        if (rows[index]) {
            const gradeInputs = rows[index].querySelectorAll('.grade-input');
            gradeData.grades.forEach((grade, gradeIndex) => {
                if (gradeInputs[gradeIndex]) {
                    gradeInputs[gradeIndex].value = grade;
                }
            });
            calculateFinalRating(index);
        }
    });
}

// Get student data from form
function getStudentData() {
    return {
        studentName: document.getElementById('studentName').value,
        studentId: document.getElementById('studentId').value,
        gradeLevel: document.getElementById('gradeLevel').value,
        section: document.getElementById('section').value,
        schoolYear: document.getElementById('schoolYear').value,
        teacher: document.getElementById('teacher').value
    };
}

// Get grades data from table
function getGradesData() {
    const rows = document.querySelectorAll('#gradesBody tr');
    return Array.from(rows).map(row => {
        const subject = row.querySelector('.subject-name').textContent;
        const grades = Array.from(row.querySelectorAll('.grade-input')).map(input => input.value);
        const finalRating = row.querySelector('.final-rating').textContent;
        const remarks = row.querySelector('.remarks-cell').textContent;
        
        return {
            subject,
            grades,
            finalRating,
            remarks
        };
    });
}

// Validate student data
function validateStudentData(data) {
    return data.studentName && data.studentId && data.gradeLevel && data.section && data.schoolYear;
}

// Get stored reports
function getStoredReports() {
    const reports = [];
    for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key.startsWith('report_')) {
            const data = JSON.parse(localStorage.getItem(key));
            reports.push({ key, data });
        }
    }
    return reports.sort((a, b) => new Date(b.data.timestamp) - new Date(a.data.timestamp));
}

// Generate sample data for testing
function generateSample() {
    // Fill sample student data
    document.getElementById('studentName').value = 'Juan Dela Cruz';
    document.getElementById('studentId').value = '2024-001';
    document.getElementById('gradeLevel').value = 'Grade 4';
    document.getElementById('section').value = 'Mabini';
    document.getElementById('schoolYear').value = '2024-2025';
    document.getElementById('teacher').value = 'Mrs. Maria Santos';
    
    // Fill sample grades
    const rows = document.querySelectorAll('#gradesBody tr');
    rows.forEach((row, index) => {
        const gradeInputs = row.querySelectorAll('.grade-input');
        gradeInputs.forEach(input => {
            const randomGrade = Math.floor(Math.random() * 21) + 80; // Random grade between 80-100
            input.value = randomGrade;
        });
        calculateFinalRating(index);
    });
    
    showNotification('Sample data generated!', 'info');
}

// Clear all data
function clearReport() {
    if (confirm('Are you sure you want to clear all data? This action cannot be undone.')) {
        document.querySelectorAll('input').forEach(input => input.value = '');
        document.getElementById('gradeLevel').value = '';
        initializeReportCard();
        showNotification('All data cleared!', 'warning');
    }
}

// Print report
function printReport() {
    const studentData = getStudentData();
    if (!validateStudentData(studentData)) {
        alert('Please fill in all required student information before printing.');
        return;
    }
    
    window.print();
}

// Download PDF (placeholder - requires PDF library)
function downloadPDF() {
    const studentData = getStudentData();
    if (!validateStudentData(studentData)) {
        alert('Please fill in all required student information before downloading.');
        return;
    }
    
    // This would require a PDF library like jsPDF or Puppeteer
    alert('PDF download feature requires additional setup. For now, please use the Print function and save as PDF.');
}

// Show notification
function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        background-color: ${type === 'success' ? 'var(--success-color)' : 
                          type === 'warning' ? 'var(--warning-color)' : 
                          type === 'error' ? 'var(--error-color)' : 'var(--primary-color)'};
        color: white;
        border-radius: 8px;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        transition: all 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Auto-save functionality
setInterval(() => {
    const studentData = getStudentData();
    if (studentData.studentName && studentData.studentId) {
        const reportData = {
            ...studentData,
            grades: getGradesData(),
            timestamp: new Date().toISOString()
        };
        localStorage.setItem('autosave_report', JSON.stringify(reportData));
    }
}, 30000); // Auto-save every 30 seconds