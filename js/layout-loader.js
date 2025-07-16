fetch("template.php")
    .then(response => response.text())
    .then(html => {
        console.log("Template loaded successfully");
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');


        // ==== BODY MERGE ====

        const head = doc.querySelector('head');
        const header = doc.querySelector('header');
        const footer = doc.querySelector('footer');
        
        if (head) document.head.innerHTML += head.innerHTML;
        if (header) document.getElementById('header-placeholder').appendChild(header);
        if (footer) document.getElementById('footer-placeholder').appendChild(footer);
        
        console.log("Template elements inserted");
        
        // Initialize profile dropdown after template is loaded
        setTimeout(initializeProfileDropdown, 100);
    })
    .catch(error => {
        console.error("Error loading template:", error);
    });

// Profile Dropdown functionality
function initializeProfileDropdown() {
    console.log("Initializing profile dropdown...");
    const profileDropdown = document.querySelector('.profile-dropdown');
    const profileTrigger = document.querySelector('.profile-trigger');
    
    console.log("Profile dropdown element:", profileDropdown);
    console.log("Profile trigger element:", profileTrigger);
    
    if (profileTrigger && profileDropdown) {
        console.log("Setting up dropdown event listeners");
        // Toggle dropdown on click
        profileTrigger.addEventListener('click', function(e) {
            e.preventDefault();
            console.log("Profile trigger clicked, toggling dropdown");
            profileDropdown.classList.toggle('active');
            console.log("Dropdown active state:", profileDropdown.classList.contains('active'));
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
        console.log("Profile dropdown elements not found!");
    }
}