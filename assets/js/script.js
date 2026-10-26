/**
 * SavorBite - Client-Side Interactive JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Auto-dismiss flash alerts after 5 seconds
    const flashAlerts = document.querySelectorAll('.flash-alert');
    flashAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });

    // 2. Active Link Highlighting in Navigation
    const currentPath = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-links a');
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && (href === currentPath || (currentPath === '' && href === 'index.php'))) {
            link.classList.add('active');
        }
    });

    // 3. User Dropdown Click Toggle for Mobile Devices
    const userDropdownBtn = document.querySelector('.user-dropdown-btn');
    const userDropdownMenu = document.querySelector('.dropdown-menu');
    if (userDropdownBtn && userDropdownMenu) {
        userDropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isDisplayed = userDropdownMenu.style.display === 'block';
            userDropdownMenu.style.display = isDisplayed ? 'none' : 'block';
        });

        document.addEventListener('click', () => {
            userDropdownMenu.style.display = 'none';
        });
    }

    // 4. Subtle image load fade-in
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        img.addEventListener('load', () => {
            img.style.opacity = '1';
        });
    });
});
