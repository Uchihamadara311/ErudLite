fetch("template.php")
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');


        // ==== BODY MERGE ====

        const head = doc.querySelector('head');
        const header = doc.querySelector('header');
        const footer = doc.querySelector('footer');
        
        if (head) document.head.innerHTML += head.innerHTML;
        if (header) document.getElementById('header-placeholder').appendChild(header);
        if (footer) document.getElementById('footer-placeholder').appendChild(footer);
    });