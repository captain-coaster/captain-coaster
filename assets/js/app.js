/*
 * Captain Coaster - Main JavaScript Entry Point
 * Tailwind CSS v4 - No Bootstrap Dependencies
 */

// Tailwind CSS v4 with custom theme
import '../css/app.css';

// Start the Stimulus application
import './bootstrap.js';

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    // Sidebar toggle for desktop
    document.querySelectorAll('.sidebar-toggle').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            document.body.classList.toggle('sidebar-collapsed');
            const isCollapsed =
                document.body.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed.toString());
        });
    });

    // Restore sidebar state from localStorage
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }
});
