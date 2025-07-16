// Profile Dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing dropdown...');
    const profileDropdown = document.querySelector('.profile-dropdown');
    const profileTrigger = document.querySelector('.profile-trigger');
    
    console.log('Profile dropdown:', profileDropdown);
    console.log('Profile trigger:', profileTrigger);
    
    if (profileTrigger && profileDropdown) {
        console.log('Elements found, adding event listeners...');
        // Toggle dropdown on click
        profileTrigger.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Profile trigger clicked');
            profileDropdown.classList.toggle('active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('active');
            }
        });
        
        // Close dropdown when pressing Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                profileDropdown.classList.remove('active');
            }
        });
    } else {
        console.log('Elements not found!');
    }
});
