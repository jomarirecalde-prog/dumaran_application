// Dumaran Hiring System - Office Theme JavaScript

// Mobile sidebar toggle with overlay
function initSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const mainWrapper = document.querySelector('.main-wrapper');
    
    // Create overlay if it doesn't exist
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay && sidebar) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }
    
    if (menuToggle && sidebar) {
        // Toggle sidebar on menu button click
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('active');
            overlay?.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        });
        
        // Close sidebar when clicking overlay
        overlay?.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        // Close sidebar when clicking a navigation link on mobile
        const navLinks = sidebar.querySelectorAll('.sidebar-nav-item');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('active');
                    overlay?.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
    }
}

// Floating Action Button (Scroll to top)
function initFAB() {
    const fab = document.querySelector('.fab');
    
    if (fab) {
        // Show/hide FAB based on scroll position
        let lastScroll = 0;
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 300) {
                fab.style.display = 'flex';
            } else {
                fab.style.display = 'none';
            }
            
            lastScroll = currentScroll;
        });
        
        // Scroll to top on click
        fab.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
}

// Search input auto-submit with debounce
function initSearchDebounce() {
    const searchInput = document.querySelector('input[name="search"]');
    const searchForm = document.querySelector('#searchForm');
    
    if (searchInput && searchForm) {
        let searchTimeout;
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchForm.submit();
            }, 500);
        });
    }
}

// Filter functionality
function initFilters() {
    // Make filter chips interactive
    const filterChips = document.querySelectorAll('.filter-chip');
    filterChips.forEach(chip => {
        chip.addEventListener('click', function(e) {
            const url = new URL(window.location.href);
            const filterType = this.dataset.filterType;
            const filterValue = this.dataset.filterValue;
            
            if (filterValue) {
                url.searchParams.set(filterType, filterValue);
            } else {
                url.searchParams.delete(filterType);
            }
            
            window.location.href = url.toString();
        });
    });
}

// Add animation to elements as they enter viewport
function initScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // Observe cards and stat cards
    const animatedElements = document.querySelectorAll('.card, .stat-card, .position-card, .application-list-item');
    animatedElements.forEach(el => observer.observe(el));
}

// Set active navigation item
function setActiveNav() {
    const currentPath = window.location.pathname;
    const navItems = document.querySelectorAll('.sidebar-nav-item, .mobile-nav-item');
    
    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href && currentPath.includes(href.split('?')[0])) {
            item.classList.add('active');
        } else if (item.classList.contains('active') && !currentPath.includes(href?.split('?')[0] || '')) {
            item.classList.remove('active');
        }
    });
}

// Handle window resize
let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (window.innerWidth > 768 && sidebar) {
            sidebar.classList.remove('active');
            overlay?.classList.remove('active');
            document.body.style.overflow = '';
        }
    }, 250);
});

// Pull to refresh functionality for mobile
let startY = 0;
let isPulling = false;

function initPullToRefresh() {
    if (window.innerWidth > 768) return;
    
    const content = document.querySelector('.content');
    if (!content) return;
    
    content.addEventListener('touchstart', (e) => {
        if (window.scrollY === 0) {
            startY = e.touches[0].pageY;
            isPulling = true;
        }
    });
    
    content.addEventListener('touchmove', (e) => {
        if (!isPulling) return;
        
        const currentY = e.touches[0].pageY;
        const pullDistance = currentY - startY;
        
        if (pullDistance > 80 && window.scrollY === 0) {
            // Show refresh indicator
            console.log('Pull to refresh triggered');
        }
    });
    
    content.addEventListener('touchend', () => {
        if (isPulling) {
            isPulling = false;
            // Could trigger page refresh here
        }
    });
}

// Initialize all functions on page load
document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initFAB();
    initSearchDebounce();
    initFilters();
    initScrollAnimations();
    setActiveNav();
    initPullToRefresh();
    
    // Add stagger animation to initial cards
    const cards = document.querySelectorAll('.card, .stat-card');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '1';
            card.classList.add('fade-in');
        }, index * 50);
    });
});

// Confirm dialogs with better UX - Now uses modern toast system
window.confirmAction = async function(message) {
    return await showConfirm({
        message: message,
        title: 'Confirm Action',
        type: 'warning'
    });
};

// Helper function to set filter values (used in filter forms)
window.setFilter = function(filterType, filterValue) {
    const form = document.querySelector('#searchForm') || document.querySelector('form');
    if (form) {
        const input = form.querySelector(`input[name="${filterType}"]`) || 
                     form.querySelector(`#${filterType}Filter`);
        if (input) {
            input.value = filterValue;
            form.submit();
        }
    }
};
