document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.choice-section a');
    const bgTarget = document.querySelector('.background-change');
    const bgImages = [
        'url(assets/bg-announcement.jpg)',
        'url(assets/bg-reportcard.jpg)',
        'url(assets/bg-schedule.jpg)',
        'url(assets/bg-clearance.jpg)'
    ];

    let currentIndex = -1;
    let fadeTimeout;

    buttons.forEach((button, index) => {
        button.addEventListener('mouseenter', () => {
            if (currentIndex === index) return; // same button, skip

            clearTimeout(fadeTimeout);
            bgTarget.style.opacity = '0'; // start fade-out

            fadeTimeout = setTimeout(() => {
                bgTarget.style.backgroundImage = bgImages[index];
                bgTarget.style.opacity = '0.5'; // fade in new image
                currentIndex = index;
            }, 150); // wait for partial fade before swapping
        });

        button.addEventListener('mouseleave', () => {
            bgTarget.style.opacity = '0';
            currentIndex = -1;
        });
    });
});