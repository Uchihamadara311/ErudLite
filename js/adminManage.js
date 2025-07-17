// Generic Admin Management JavaScript
// Works for both Subject and User management

document.addEventListener('DOMContentLoaded', function() {
    // Add role change handler for user management
    const roleSelect = document.getElementById('permissions');
    if (roleSelect) {
        roleSelect.addEventListener('change', handleRoleChange);
    }
    
    // Add cancel button handler
    const cancelBtn = document.getElementById('cancel-btn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', cancelEdit);
    }
    
    // Add delete button handler
    const deleteBtn = document.getElementById('delete-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', deleteCurrentItem);
    }
    
    // Search bar functionality
    const searchBar = document.getElementById('searchBar');
    if (searchBar) {
        searchBar.addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll("#subjects-table tbody tr, #users-table tbody tr");
            rows.forEach(function(row) {
                let text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(filter) > -1 ? "" : "none";
            });
        });
    }
    
    // Initialize autocomplete
    initializeAutocomplete();
    
    // Initialize hover effects for clickable rows
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

function initializeAutocomplete() {
    const searchInput = document.getElementById('subject_name') || document.getElementById('email');
    const suggestionsDiv = document.getElementById('subject-suggestions') || document.getElementById('user-suggestions');
    
    if (!searchInput || !suggestionsDiv) return;
    
    // Get existing items from the table
    function getExistingItems() {
        const items = [];
        const rows = document.querySelectorAll("#subjects-table tbody tr.clickable-row, #users-table tbody tr.clickable-row");
        
        rows.forEach(function(row) {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 4) {
                // Get the onclick attribute to extract ID
                const onclickAttr = row.getAttribute('onclick');
                let itemId = 0;
                
                // Try to extract subject_id or user_id
                const subjectIdMatch = onclickAttr.match(/editSubject\((\d+)/);
                const userIdMatch = onclickAttr.match(/editUser\((\d+)/);
                
                if (subjectIdMatch) itemId = parseInt(subjectIdMatch[1]);
                else if (userIdMatch) itemId = parseInt(userIdMatch[1]);
                
                // Handle subjects
                if (document.getElementById('subject_name')) {
                    items.push({
                        id: itemId,
                        name: cells[0].textContent.trim(),
                        description: cells[1].textContent.trim(),
                        grade: cells[2].textContent.trim(),
                        requirements: cells[3].textContent.trim()
                    });
                }
                // Handle users
                else if (document.getElementById('email')) {
                    items.push({
                        id: itemId,
                        name: cells[0].textContent.trim(),
                        email: cells[1].textContent.trim(),
                        role: cells[2].textContent.trim(),
                        contact: cells[3].textContent.trim()
                    });
                }
            }
        });
        
        return items;
    }
    
    // Filter items based on input
    function filterItems(input) {
        const items = getExistingItems();
        const query = input.toLowerCase();
        
        return items.filter(item => 
            item.name.toLowerCase().includes(query) ||
            (item.email && item.email.toLowerCase().includes(query))
        );
    }
    
    // Show suggestions
    function showSuggestions(suggestions) {
        if (suggestions.length === 0) {
            suggestionsDiv.style.display = 'none';
            return;
        }
        
        suggestionsDiv.innerHTML = '';
        
        suggestions.forEach(function(item) {
            const suggestionDiv = document.createElement('div');
            suggestionDiv.className = 'autocomplete-suggestion';
            
            // Different display for subjects vs users
            if (document.getElementById('subject_name')) {
                suggestionDiv.innerHTML = `
                    <span class="subject-name">${item.name}</span>
                    <span class="subject-grade">${item.grade}</span>
                `;
                
                suggestionDiv.addEventListener('click', function() {
                    const gradeMatch = item.grade.match(/Grade (\d+)/);
                    const gradeLevel = gradeMatch ? parseInt(gradeMatch[1]) : 0;
                    
                    editSubject(
                        item.id,
                        item.name,
                        item.description === 'No description' ? '' : item.description,
                        gradeLevel,
                        item.requirements === 'No requirements' ? '' : item.requirements
                    );
                    
                    suggestionsDiv.style.display = 'none';
                });
            } else if (document.getElementById('email')) {
                suggestionDiv.innerHTML = `
                    <span class="user-name">${item.name}</span>
                    <span class="user-role">${item.role}</span>
                `;
                
                suggestionDiv.addEventListener('click', function() {
                    editUser(item.id, item);
                    suggestionsDiv.style.display = 'none';
                });
            }
            
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
    searchInput.addEventListener('input', function() {
        const value = this.value.trim();
        
        if (value.length >= 1) {
            const suggestions = filterItems(value);
            showSuggestions(suggestions);
        } else {
            suggestionsDiv.style.display = 'none';
        }
    });
    
    searchInput.addEventListener('focus', function() {
        const value = this.value.trim();
        if (value.length >= 1) {
            const suggestions = filterItems(value);
            showSuggestions(suggestions);
        }
    });
    
    searchInput.addEventListener('blur', hideSuggestions);
    
    // Handle keyboard navigation
    searchInput.addEventListener('keydown', function(e) {
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

// Subject Management Functions
function editSubject(subjectId, subjectName, description, gradeLevel, requirements) {
    if (!document.getElementById('subject_name')) return;
    
    // Update form title
    document.getElementById('form-title').textContent = 'Edit Subject: ' + subjectName;
    
    // Update submit button
    document.getElementById('submit-btn').textContent = 'Update Subject';
    
    // Show cancel and delete buttons
    document.getElementById('cancel-btn').style.display = 'inline-block';
    if (document.getElementById('delete-btn')) {
        document.getElementById('delete-btn').style.display = 'inline-block';
    }
    
    // Set operation mode
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

// User Management Functions
function editUser(userId, userData) {
    if (!document.getElementById('email')) return;
    
    // Update form title
    document.getElementById('form-title').textContent = 'Edit User: ' + userData.name;
    
    // Update submit button
    document.getElementById('submit-btn').textContent = 'Update User';
    
    // Show cancel and delete buttons
    document.getElementById('cancel-btn').style.display = 'inline-block';
    if (document.getElementById('delete-btn')) {
        document.getElementById('delete-btn').style.display = 'inline-block';
    }
    
    // Set operation mode
    document.getElementById('operation').value = 'edit';
    document.getElementById('user_id').value = userId;
    
    // Make password field optional for editing
    const passwordField = document.getElementById('password');
    const passwordLabel = document.getElementById('password-label');
    if (passwordField) {
        passwordField.required = false;
        passwordField.placeholder = 'Leave blank to keep current password';
    }
    if (passwordLabel) {
        passwordLabel.textContent = 'Password (optional)';
    }
    
    // Populate form fields
    document.getElementById('first_name').value = userData.first_name || '';
    document.getElementById('last_name').value = userData.last_name || '';
    document.getElementById('email').value = userData.email || '';
    document.getElementById('contact_number').value = userData.contact_number || '';
    document.getElementById('address').value = userData.address || '';
    document.getElementById('nationality').value = userData.nationality || '';
    document.getElementById('gender').value = userData.gender || '';
    document.getElementById('emergency_contact').value = userData.emergency_contact || '';
    document.getElementById('permissions').value = userData.permissions || '';
    
    // Handle instructor specialization
    const instructorFields = document.getElementById('instructorFields');
    const specializationField = document.getElementById('specialization');
    if (userData.permissions === 'Instructor') {
        if (instructorFields) instructorFields.classList.remove('hidden');
        if (specializationField) specializationField.value = userData.specialization || '';
    } else {
        if (instructorFields) instructorFields.classList.add('hidden');
    }
    
    // Scroll to form
    document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
}

// Generic Reset Form
function resetForm() {
    // Determine if we're in subject or user management
    const isSubject = document.getElementById('subject_name');
    const isUser = document.getElementById('email');
    
    if (isSubject) {
        document.getElementById('form-title').textContent = 'Add New Subject';
        document.getElementById('submit-btn').textContent = 'Add Subject';
        document.getElementById('operation').value = 'add';
        document.getElementById('subject_id').value = '';
    } else if (isUser) {
        document.getElementById('form-title').textContent = 'Add New User';
        document.getElementById('submit-btn').textContent = 'Register User';
        document.getElementById('operation').value = 'add';
        document.getElementById('user_id').value = '';
        
        // Reset password field requirements for add mode
        const passwordField = document.getElementById('password');
        const passwordLabel = document.getElementById('password-label');
        if (passwordField) {
            passwordField.required = true;
            passwordField.placeholder = 'Enter password';
        }
        if (passwordLabel) {
            passwordLabel.textContent = 'Password *';
        }
    }
    
    // Hide buttons
    document.getElementById('cancel-btn').style.display = 'none';
    if (document.getElementById('delete-btn')) {
        document.getElementById('delete-btn').style.display = 'none';
    }
    
    // Reset form
    const form = document.getElementById('subject-form') || document.getElementById('user-form');
    if (form) {
        form.reset();
        
        // Re-set hidden fields
        if (isSubject) {
            document.getElementById('operation').value = 'add';
            document.getElementById('subject_id').value = '';
        } else if (isUser) {
            document.getElementById('operation').value = 'add';
            document.getElementById('user_id').value = '';
        }
    }
}

// Generic Delete Functions
function deleteCurrentItem() {
    const subjectId = document.getElementById('subject_id');
    const userId = document.getElementById('user_id');
    
    if (subjectId && subjectId.value) {
        const subjectName = document.getElementById('subject_name').value;
        deleteSubject(subjectId.value, subjectName);
    } else if (userId && userId.value) {
        const userName = document.getElementById('first_name').value + ' ' + document.getElementById('last_name').value;
        deleteUser(userId.value, userName);
    }
}

function deleteSubject(subjectId, subjectName) {
    if (confirm('Are you sure you want to delete the subject "' + subjectName + '"? This action cannot be undone.')) {
        submitDeleteForm('delete', 'subject_id', subjectId, 'adminSubjectManagement.php');
    }
}

function deleteUser(userId, userName) {
    if (confirm('Are you sure you want to delete the user "' + userName + '"? This action cannot be undone.')) {
        submitDeleteForm('delete', 'user_id', userId, 'adminUserManagement.php');
    }
}

function submitDeleteForm(operation, idField, idValue, actionUrl) {
    const deleteForm = document.createElement('form');
    deleteForm.method = 'POST';
    deleteForm.action = actionUrl;
    deleteForm.style.display = 'none';
    
    const operationInput = document.createElement('input');
    operationInput.type = 'hidden';
    operationInput.name = 'operation';
    operationInput.value = operation;
    deleteForm.appendChild(operationInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = idField;
    idInput.value = idValue;
    deleteForm.appendChild(idInput);
    
    document.body.appendChild(deleteForm);
    deleteForm.submit();
}

// Role change handler
function handleRoleChange() {
    const roleSelect = document.getElementById('permissions');
    const instructorFields = document.getElementById('instructorFields');
    const specializationField = document.getElementById('specialization');
    
    if (roleSelect && instructorFields && specializationField) {
        if (roleSelect.value === 'Instructor') {
            instructorFields.classList.remove('hidden');
            specializationField.required = true;
        } else {
            instructorFields.classList.add('hidden');
            specializationField.required = false;
            specializationField.value = '';
        }
    }
}

// Enhanced cancel edit function
function cancelEdit() {
    resetForm();
    
    // Clear search
    const searchInput = document.getElementById('subject_name') || document.getElementById('email');
    if (searchInput) {
        searchInput.value = '';
    }
    
    // Hide suggestions
    const suggestionsDiv = document.getElementById('subject-suggestions') || document.getElementById('user-suggestions');
    if (suggestionsDiv) {
        suggestionsDiv.style.display = 'none';
    }
    
    // Hide instructor fields if they exist
    const instructorFields = document.getElementById('instructorFields');
    if (instructorFields) {
        instructorFields.classList.add('hidden');
    }
}
