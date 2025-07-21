document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchBar = document.getElementById('searchBar');
    if (searchBar) {
        searchBar.addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll("#subjects-table tbody tr");
            rows.forEach(function(row) {
                let text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(filter) > -1 ? "" : "none";
            });
        });
    }
    
    // Initialize autocomplete
    initializeAutocomplete();
    
    // Add hover effects to rows
    const rows = document.querySelectorAll('.clickable-row');
    rows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            if (!this.classList.contains('selected')) {
                this.style.backgroundColor = '#f8f9fa';
            }
        });
        row.addEventListener('mouseleave', function() {
            if (!this.classList.contains('selected')) {
                this.style.backgroundColor = '';
            }
        });
    });
});

function editSubject(subject_id, data) {
    // Remove selected class from all rows
    document.querySelectorAll('.clickable-row').forEach(row => row.classList.remove('selected'));
    
    // Add selected class to clicked row
    event.currentTarget.classList.add('selected');
    
    // Update form values
    document.getElementById('operation').value = 'edit';
    document.getElementById('subject_id').value = subject_id;
    document.getElementById('subject_name').value = data.subject_name;
    document.getElementById('description').value = data.description || '';
    document.getElementById('grade_level').value = data.grade_level || '';
    
    // Update form title and buttons
    document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> Edit Subject';
    document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Subject';
    document.getElementById('cancel-btn').style.display = 'inline-block';
    document.getElementById('delete-btn').style.display = 'inline-block';
    
    // Smooth scroll to form with offset
    const formSection = document.querySelector('.form-section');
    const header = document.querySelector('#header-placeholder');
    const headerHeight = header ? header.offsetHeight : 0;
    const offset = headerHeight + 20;
    
    window.scrollTo({
        top: formSection.offsetTop - offset,
        behavior: 'smooth'
    });
}

function resetForm() {
    // Reset form values
    document.getElementById('operation').value = 'add';
    document.getElementById('subject_id').value = '';
    document.getElementById('subject_name').value = '';
    document.getElementById('description').value = '';
    document.getElementById('grade_level').value = '';
    
    // Reset form appearance
    document.getElementById('form-title').innerHTML = '<i class="fas fa-book-open"></i> Add New Subject';
    document.getElementById('submit-btn').innerHTML = '<i class="fas fa-plus"></i> Add Subject';
    document.getElementById('cancel-btn').style.display = 'none';
    document.getElementById('delete-btn').style.display = 'none';
    
    // Remove selected state from all rows
    document.querySelectorAll('.clickable-row').forEach(row => {
        row.classList.remove('selected');
        row.style.backgroundColor = '';
    });
}

function deleteSubject(subject_id) {
    event.stopPropagation(); // Prevent row click event
    
    if (confirm('Are you sure you want to delete this subject? This action cannot be undone.')) {
        document.getElementById('operation').value = 'delete';
        document.getElementById('subject_id').value = subject_id;
        document.getElementById('subject-form').submit();
    }
}

function deleteCurrentSubject() {
    const subjectId = document.getElementById('subject_id').value;
    
    if (!subjectId) {
        console.error('No subject selected for deletion');
        return;
    }
    
    deleteSubject(subjectId);
}

function initializeAutocomplete() {
    const subjectNameInput = document.getElementById('subject_name');
    const suggestionsDiv = document.getElementById('subject-suggestions');
    
    if (!subjectNameInput || !suggestionsDiv) return;
    
    subjectNameInput.addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        if (filter.length < 2) {
            suggestionsDiv.style.display = 'none';
            return;
        }
        
        const rows = document.querySelectorAll("#subjects-table tbody tr");
        const suggestions = Array.from(rows)
            .map(row => ({
                id: row.getAttribute('data-id'),
                name: row.cells[0].textContent.trim(),
                description: row.cells[1].textContent.trim()
            }))
            .filter(subject => subject.name.toLowerCase().includes(filter));
            
        showSuggestions(suggestions);
    });
}

function showSuggestions(suggestions) {
    const suggestionsDiv = document.getElementById('subject-suggestions');
    if (!suggestionsDiv) return;
    
    if (suggestions.length === 0) {
        suggestionsDiv.style.display = 'none';
        return;
    }
    
    suggestionsDiv.innerHTML = '';
    suggestions.forEach(subject => {
        const div = document.createElement('div');
        div.className = 'suggestion-item';
        div.innerHTML = `<i class="fas fa-book"></i> ${subject.name}`;
        div.addEventListener('click', () => {
            document.getElementById('subject_name').value = subject.name;
            suggestionsDiv.style.display = 'none';
        });
        suggestionsDiv.appendChild(div);
    });
    
    suggestionsDiv.style.display = 'block';
}