document.addEventListener('DOMContentLoaded', function () {

    const alerts = document.querySelectorAll('.alert-success, .alert-error');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });

    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm)) e.preventDefault();
        });
    });

    const categorySelect  = document.querySelector('select[name="category"]');
    const pavilionBox     = document.getElementById('pavilionPricingBox');
    if (categorySelect && pavilionBox) {
        const syncPavilionBox = () => {
            pavilionBox.style.display = categorySelect.value === 'Pavilion' ? 'block' : 'none';
        };
        categorySelect.addEventListener('change', syncPavilionBox);
        syncPavilionBox();
    }

    const sidebarToggle  = document.getElementById('sidebarToggle');
    const adminSidebar   = document.getElementById('adminSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    if (sidebarToggle && adminSidebar && sidebarOverlay) {
        const openSidebar = () => {
            adminSidebar.classList.add('is-open');
            sidebarOverlay.classList.add('is-visible');
        };
        const closeSidebar = () => {
            adminSidebar.classList.remove('is-open');
            sidebarOverlay.classList.remove('is-visible');
        };
        sidebarToggle.addEventListener('click', () => {
            adminSidebar.classList.contains('is-open') ? closeSidebar() : openSidebar();
        });
        sidebarOverlay.addEventListener('click', closeSidebar);
        adminSidebar.querySelectorAll('.nav-item, .btn-logout, .view-site').forEach(link => {
            link.addEventListener('click', closeSidebar);
        });
    }

    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href === currentPage) item.classList.add('active');
    });
});