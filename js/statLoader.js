// Generate attendance grid
function generateAttendanceGrid() {
    const grid = document.getElementById('attendanceGrid');
    const attendancePattern = [
        'present', 'present', 'late', 'excused',
        'present', 'present', 'absent', 'present', 'present', 'excused',
        'present', 'present', 'absent', 'present', 'present',
        'present', 'present', 'present', 'present', 'present',
        'present', 'present', 'present', 'present'
    ];

    attendancePattern.forEach((status, index) => {
        const day = document.createElement('div');
        day.className = `attendance-day ${status}`;
        day.title = `Day ${index + 1}: ${status}`;
        day.onclick = () => showAttendanceDetails(index + 1, status);
        grid.appendChild(day);
    });
}

// Interactive functions
function showGradeDetails(subject, grade) {
    alert(`${subject}: ${grade}%\nClick to view detailed breakdown`);
}

function showAttendanceDetails(day, status) {
    alert(`Day ${day}: ${status.charAt(0).toUpperCase() + status.slice(1)}`);
}

// Animate GPA on load
function animateGPA() {
    const gpaElement = document.getElementById('gpaDisplay');
    if (!gpaElement) return;
    
    let currentValue = 0;
    const targetValue = 90.50;
    const increment = targetValue / 50;

    const animation = setInterval(() => {
        currentValue += increment;
        if (currentValue >= targetValue) {
            currentValue = targetValue;
            clearInterval(animation);
        }
        gpaElement.textContent = currentValue.toFixed(2);
    }, 30);
}

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit for the template to load
    setTimeout(() => {
        generateAttendanceGrid();
        animateGPA();
    }, 200);
});

// Add hover effects to cards
function initializeCardEffects() {
    document.querySelectorAll('.dashboard-card, .SUBJECT_GRADES').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
}

// Initialize card effects after template loads
setTimeout(initializeCardEffects, 300);