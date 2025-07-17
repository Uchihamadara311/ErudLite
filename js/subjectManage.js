    document.addEventListener('DOMContentLoaded', function() {
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
    
    // Initialize autocomplete for subject name
    initializeAutocomplete();
});

function initializeAutocomplete() {
    const subjectNameInput = document.getElementById('subject_name');
    const suggestionsDiv = document.getElementById('subject-suggestions');
    
    if (!subjectNameInput || !suggestionsDiv) return;
    
    // Get existing subjects from the table
    function getExistingSubjects() {
        const subjects = [];
        const rows = document.querySelectorAll("#subjects-table tbody tr.clickable-row");
        
        rows.forEach(function(row) {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 4) {
                // Get the onclick attribute to extract subject_id
                const onclickAttr = row.getAttribute('onclick');
                const subjectIdMatch = onclickAttr.match(/editSubject\((\d+)/);
                const subjectId = subjectIdMatch ? parseInt(subjectIdMatch[1]) : 0;
                
                subjects.push({
                    id: subjectId,
                    name: cells[0].textContent.trim(),
                    description: cells[1].textContent.trim(),
                    grade: cells[2].textContent.trim(),
                    requirements: cells[3].textContent.trim()
                });
            }
        });
        
        return subjects;
    }
    
    // Filter subjects based on input
    function filterSubjects(input) {
        const subjects = getExistingSubjects();
        const query = input.toLowerCase();
        
        return subjects.filter(subject => 
            subject.name.toLowerCase().includes(query)
        );
    }
    
    // Show suggestions
    function showSuggestions(suggestions) {
        if (suggestions.length === 0) {
            suggestionsDiv.style.display = 'none';
            return;
        }
        
        suggestionsDiv.innerHTML = '';
        
        suggestions.forEach(function(subject) {
            const suggestionDiv = document.createElement('div');
            suggestionDiv.className = 'autocomplete-suggestion';
            
            // Only show subject name and grade
            suggestionDiv.innerHTML = `
                <span class="subject-name">${subject.name}</span>
                <span class="subject-grade">${subject.grade}</span>
            `;
            
            suggestionDiv.addEventListener('click', function() {
                // Extract grade level number from the grade text (e.g., "Grade 3" -> 3)
                const gradeMatch = subject.grade.match(/Grade (\d+)/);
                const gradeLevel = gradeMatch ? parseInt(gradeMatch[1]) : 0;
                
                // Switch to edit mode and populate the form
                editSubject(
                    subject.id,
                    subject.name,
                    subject.description === 'No description' ? '' : subject.description,
                    gradeLevel,
                    subject.requirements === 'No requirements' ? '' : subject.requirements
                );
                
                suggestionsDiv.style.display = 'none';
            });
            
            suggestionsDiv.appendChild(suggestionDiv);
        });
        
        suggestionsDiv.style.display = 'block';
    }
    
    // Hide suggestions
    function hideSuggestions() {
        setTimeout(() => {
            suggestionsDiv.style.display = 'none';
        }, 200);
    }
    
    // Event listeners
    subjectNameInput.addEventListener('input', function() {
        const value = this.value.trim();
        
        if (value.length >= 1) {
            const suggestions = filterSubjects(value);
            showSuggestions(suggestions);
        } else {
            suggestionsDiv.style.display = 'none';
        }
    });
    
    subjectNameInput.addEventListener('focus', function() {
        const value = this.value.trim();
        if (value.length >= 1) {
            const suggestions = filterSubjects(value);
            showSuggestions(suggestions);
        }
    });
    
    subjectNameInput.addEventListener('blur', hideSuggestions);
    
    // Handle keyboard navigation
    subjectNameInput.addEventListener('keydown', function(e) {
        const suggestions = suggestionsDiv.querySelectorAll('.autocomplete-suggestion');
        const activeSuggestion = suggestionsDiv.querySelector('.autocomplete-suggestion.active');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (activeSuggestion) {
                activeSuggestion.classList.remove('active');
                const next = activeSuggestion.nextElementSibling;
                if (next) {
                    next.classList.add('active');
                } else {
                    suggestions[0].classList.add('active');
                }
            } else if (suggestions.length > 0) {
                suggestions[0].classList.add('active');
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (activeSuggestion) {
                activeSuggestion.classList.remove('active');
                const prev = activeSuggestion.previousElementSibling;
                if (prev) {
                    prev.classList.add('active');
                } else {
                    suggestions[suggestions.length - 1].classList.add('active');
                }
            } else if (suggestions.length > 0) {
                suggestions[suggestions.length - 1].classList.add('active');
            }
        } else if (e.key === 'Enter') {
            if (activeSuggestion) {
                e.preventDefault();
                activeSuggestion.click();
            }
        } else if (e.key === 'Escape') {
            suggestionsDiv.style.display = 'none';
        }
    });
}


function editSubject(subjectId, subjectName, description, gradeLevel, requirements) {
    // Update form title to show the current subject name
    document.getElementById('form-title').textContent = 'Edit Subject: ' + subjectName;

    // Update submit button
    document.getElementById('submit-btn').textContent = 'Update Subject';

    // Show cancel and delete buttons
    document.getElementById('cancel-btn').style.display = 'inline-block';
    document.getElementById('delete-btn').style.display = 'inline-block';

    // Set operation mode to edit
    document.getElementById('operation').value = 'edit';
    document.getElementById('subject_id').value = subjectId;

    // Populate form fields
    document.getElementById('subject_name').value = subjectName;
    document.getElementById('description').value = description || '';
    document.getElementById('grade_level').value = gradeLevel;
    document.getElementById('requirements').value = requirements || '';

    // Scroll to form
    document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
    // Reset form title
    document.getElementById('form-title').textContent = 'Add New Subject';

    // Reset submit button
    document.getElementById('submit-btn').textContent = 'Add Subject';

    // Hide cancel and delete buttons
    document.getElementById('cancel-btn').style.display = 'none';
    document.getElementById('delete-btn').style.display = 'none';

    // Reset operation mode to add
    document.getElementById('operation').value = 'add';
    document.getElementById('subject_id').value = '';

    // Clear form fields
    document.getElementById('subject-form').reset();

    // Re-set the hidden fields after reset (since reset clears them)
    document.getElementById('operation').value = 'add';
    document.getElementById('subject_id').value = '';
}

function deleteCurrentSubject() {
    const subjectId = document.getElementById('subject_id').value;
    const subjectName = document.getElementById('subject_name').value;
    
    if (subjectId && subjectName) {
        deleteSubject(subjectId, subjectName);
    }
}

function deleteSubject(subjectId, subjectName) {
    if (confirm('Are you sure you want to delete the subject "' + subjectName + '"? This action cannot be undone.')) {
        // Create a form to submit the delete request
        const deleteForm = document.createElement('form');
        deleteForm.method = 'POST';
        deleteForm.action = 'adminSubjectManagement.php';
        deleteForm.style.display = 'none';
        
        // Add hidden fields
        const operationInput = document.createElement('input');
        operationInput.type = 'hidden';
        operationInput.name = 'operation';
        operationInput.value = 'delete';
        deleteForm.appendChild(operationInput);
        
        const subjectIdInput = document.createElement('input');
        subjectIdInput.type = 'hidden';
        subjectIdInput.name = 'subject_id';
        subjectIdInput.value = subjectId;
        deleteForm.appendChild(subjectIdInput);
        
        // Add form to document and submit
        document.body.appendChild(deleteForm);
        deleteForm.submit();
    }
}

// Add hover effect for clickable rows
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('.clickable-row');
    rows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
            this.style.cursor = 'pointer';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
});