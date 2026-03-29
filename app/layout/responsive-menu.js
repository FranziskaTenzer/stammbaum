// Responsive Menu Toggle - EINFACH & ZUVERLÄSSIG
document.addEventListener('DOMContentLoaded', function() {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    
    if (!hamburgerBtn || !sidebar) {
        console.error('Hamburger button or sidebar not found!');
        return;
    }
    
    // Click auf Hamburger Button
    hamburgerBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        hamburgerBtn.classList.toggle('active');
        sidebar.classList.toggle('active');
    });
    
    // Sidebar bei Link-Klick schließen (nur auf Mobile)
    const allLinks = sidebar.querySelectorAll('a, span.subsection-toggle, span.nav-section-title');
    allLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                hamburgerBtn.classList.remove('active');
                sidebar.classList.remove('active');
            }
        });
    });
    
    // ESC-Taste zum Schließen
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            hamburgerBtn.classList.remove('active');
            sidebar.classList.remove('active');
        }
    });
    
    // Optional: Fenster-Resize handling
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
            hamburgerBtn.classList.remove('active');
            sidebar.classList.remove('active');
        }
    });
});