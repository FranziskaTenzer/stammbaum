// ================================
// MENU INTERACTIVITY
// ================================

/**
 * Toggle main nav sections (Stammbaum, Admin)
 */
function toggleSection(element) {
    const menu = element.nextElementSibling;
    
    element.classList.toggle('collapsed');
    menu.classList.toggle('collapsed');
    
    // Save state to localStorage
    const sectionName = element.textContent.trim();
    const isCollapsed = element.classList.contains('collapsed');
    localStorage.setItem('section-' + sectionName, isCollapsed);
}

/**
 * Toggle subsections (Daten verwalten, Verwaltung, Profil)
 */
function toggleSubsection(event) {
    event.stopPropagation();
    
    const toggle = event.currentTarget;
    const submenu = toggle.nextElementSibling;
    
    // Close other open submenus at same level
    const siblings = toggle.parentElement.parentElement.querySelectorAll('.nav-submenu');
    siblings.forEach(menu => {
        if (menu !== submenu) {
            menu.classList.add('collapsed');
            menu.previousElementSibling.classList.add('collapsed');
        }
    });
    
    toggle.classList.toggle('collapsed');
    submenu.classList.toggle('collapsed');
    
    // Save state
    const subsectionName = toggle.textContent.trim();
    const isCollapsed = toggle.classList.contains('collapsed');
    localStorage.setItem('subsection-' + subsectionName, isCollapsed);
}

/**
 * Toggle mobile menu
 */
function toggleMobileMenu() {
    const nav = document.querySelector('.sidebar-nav');
    nav.classList.toggle('open');
}

/**
 * Initialize menu from localStorage on page load
 */
document.addEventListener('DOMContentLoaded', function() {
    // Restore section states
    document.querySelectorAll('.nav-section-title').forEach(section => {
        const sectionName = section.textContent.trim();
        const isCollapsed = localStorage.getItem('section-' + sectionName) === 'true';
        
        if (isCollapsed) {
            section.classList.add('collapsed');
            section.nextElementSibling.classList.add('collapsed');
        }
    });
    
    // Restore subsection states
    document.querySelectorAll('.subsection-toggle').forEach(toggle => {
        const subsectionName = toggle.textContent.trim();
        const isCollapsed = localStorage.getItem('subsection-' + subsectionName) === 'true';
        
        if (isCollapsed) {
            toggle.classList.add('collapsed');
            toggle.nextElementSibling.classList.add('collapsed');
        }
    });
    
    // Highlight current page
    highlightCurrentPage();
});

/**
 * Highlight link of current page
 */
function highlightCurrentPage() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    
    document.querySelectorAll('.nav-menu a, .nav-submenu a').forEach(link => {
        const href = link.getAttribute('href').split('/').pop();
        
        if (href === currentPage) {
            link.style.background = 'var(--primary-color, #667eea)';
            link.style.color = 'white';
            link.style.fontWeight = 'bold';
            
            // Expand parent sections
            let parent = link.closest('.nav-menu, .nav-submenu');
            while (parent) {
                if (parent.classList.contains('collapsed')) {
                    const toggle = parent.previousElementSibling;
                    if (toggle) {
                        toggle.classList.remove('collapsed');
                        parent.classList.remove('collapsed');
                    }
                }
                parent = parent.parentElement?.closest('.nav-menu, .nav-submenu');
            }
        }
    });
}

/**
 * Close menu when clicking outside (mobile)
 */
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar-nav');
    const toggle = document.querySelector('.mobile-toggle');
    
    if (!event.target.closest('.sidebar') && window.innerWidth <= 768) {
        sidebar.classList.remove('open');
    }
});

/**
 * Handle window resize
 */
window.addEventListener('resize', function() {
    const sidebar = document.querySelector('.sidebar-nav');
    
    if (window.innerWidth > 768) {
        sidebar.classList.remove('open');
    }
});