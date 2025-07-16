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
    });