fetch("/template.html")
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');


        // ==== BODY MERGE ====

        const header = doc.querySelector('header');
        const footer = doc.querySelector('footer');

        if (header) document.getElementById('header-placeholder').appendChild(header);
        if (footer) document.getElementById('footer-placeholder').appendChild(footer);
    });