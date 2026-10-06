document.addEventListener('DOMContentLoaded', function () {

    const navbar = document.getElementById('navbar');
    if (navbar) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 60) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }

    const navToggle     = document.getElementById('navToggle');
    const drawer        = document.getElementById('s5Drawer');
    const drawerBackdrop= document.getElementById('s5DrawerBackdrop');
    const drawerClose   = document.getElementById('s5DrawerClose');

    if (navToggle && drawer) {
        const openMenu = () => {
            drawer.classList.add('is-open');
            if (drawerBackdrop) drawerBackdrop.classList.add('is-open');
            document.body.classList.add('s5-no-scroll');
            navToggle.setAttribute('aria-expanded', 'true');
            drawer.setAttribute('aria-hidden', 'false');
        };
        const closeMenu = () => {
            drawer.classList.remove('is-open');
            if (drawerBackdrop) drawerBackdrop.classList.remove('is-open');
            document.body.classList.remove('s5-no-scroll');
            navToggle.setAttribute('aria-expanded', 'false');
            drawer.setAttribute('aria-hidden', 'true');
        };
        navToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            drawer.classList.contains('is-open') ? closeMenu() : openMenu();
        });
        if (drawerClose) drawerClose.addEventListener('click', closeMenu);
        if (drawerBackdrop) drawerBackdrop.addEventListener('click', closeMenu);
        drawer.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) closeMenu();
        });
    }

    const navLinks = document.querySelector('.nav-links');

    const revealEls = document.querySelectorAll('.cottage-card, .amenity-item, .stat-card');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, i) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, i * 80);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        revealEls.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(el);
        });
    }

    const galleryGrid    = document.querySelector('.gallery-grid');
    const filterBtns     = document.querySelectorAll('.gallery-filter-btn');
    const lightbox        = document.getElementById('galleryLightbox');
    const lightboxImg     = document.getElementById('lightboxImg');
    const lightboxCaption = document.getElementById('lightboxCaption');
    const lightboxClose   = document.getElementById('lightboxClose');
    const lightboxPrev    = document.getElementById('lightboxPrev');
    const lightboxNext    = document.getElementById('lightboxNext');

    if (galleryGrid) {
        const allItems = Array.from(galleryGrid.querySelectorAll('.gallery-item'));
        let visibleItems = allItems.slice();
        let currentIndex = 0;

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const filter = this.dataset.filter;

                allItems.forEach(item => {
                    const show = filter === 'all' || item.dataset.category === filter;
                    item.classList.toggle('gallery-hidden', !show);
                });
                visibleItems = allItems.filter(item => !item.classList.contains('gallery-hidden'));
            });
        });

        const openLightbox = (index) => {
            currentIndex = index;
            const item = visibleItems[currentIndex];
            if (!item) return;
            lightboxImg.src = item.dataset.full;
            lightboxImg.alt = item.dataset.caption || '';
            lightboxCaption.textContent = item.dataset.caption || '';
            lightbox.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        };
        const closeLightbox = () => {
            lightbox.classList.remove('is-open');
            document.body.style.overflow = '';
        };
        const showRelative = (delta) => {
            if (!visibleItems.length) return;
            currentIndex = (currentIndex + delta + visibleItems.length) % visibleItems.length;
            openLightbox(currentIndex);
        };

        allItems.forEach(item => {
            item.addEventListener('click', () => {
                openLightbox(visibleItems.indexOf(item));
            });
        });

        if (lightboxClose) lightboxClose.addEventListener('click', closeLightbox);
        if (lightboxPrev)  lightboxPrev.addEventListener('click', () => showRelative(-1));
        if (lightboxNext)  lightboxNext.addEventListener('click', () => showRelative(1));
        if (lightbox) {
            lightbox.addEventListener('click', function (e) {
                if (e.target === lightbox) closeLightbox();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (!lightbox || !lightbox.classList.contains('is-open')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') showRelative(-1);
            if (e.key === 'ArrowRight') showRelative(1);
        });
    }

    const posterItems = document.querySelectorAll('.poster-item');
    const postersLightbox = document.getElementById('postersLightbox');
    if (posterItems.length && postersLightbox) {
        const postersLightboxImg     = document.getElementById('postersLightboxImg');
        const postersLightboxCaption = document.getElementById('postersLightboxCaption');
        const postersLightboxClose   = document.getElementById('postersLightboxClose');
        const postersLightboxPrev    = document.getElementById('postersLightboxPrev');
        const postersLightboxNext    = document.getElementById('postersLightboxNext');
        const posterList = Array.from(posterItems);
        let posterIndex = 0;

        const openPosterLightbox = (i) => {
            posterIndex = (i + posterList.length) % posterList.length;
            const item = posterList[posterIndex];
            postersLightboxImg.src = item.dataset.full;
            postersLightboxImg.alt = item.dataset.caption || '';
            postersLightboxCaption.textContent = item.dataset.caption || '';
            postersLightbox.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        };
        const closePosterLightbox = () => {
            postersLightbox.classList.remove('is-open');
            document.body.style.overflow = '';
        };

        posterList.forEach((item, i) => item.addEventListener('click', () => openPosterLightbox(i)));
        if (postersLightboxClose) postersLightboxClose.addEventListener('click', closePosterLightbox);
        if (postersLightboxPrev)  postersLightboxPrev.addEventListener('click', () => openPosterLightbox(posterIndex - 1));
        if (postersLightboxNext)  postersLightboxNext.addEventListener('click', () => openPosterLightbox(posterIndex + 1));
        postersLightbox.addEventListener('click', function (e) {
            if (e.target === postersLightbox) closePosterLightbox();
        });
        document.addEventListener('keydown', function (e) {
            if (!postersLightbox.classList.contains('is-open')) return;
            if (e.key === 'Escape') closePosterLightbox();
            if (e.key === 'ArrowLeft') openPosterLightbox(posterIndex - 1);
            if (e.key === 'ArrowRight') openPosterLightbox(posterIndex + 1);
        });
    }

    const bottomNav = document.querySelector('.s5-bottom-nav');
    if (bottomNav) {
        const bnItems = bottomNav.querySelectorAll('.s5-bn-item[data-section]');

        bnItems.forEach(item => {
            item.addEventListener('click', function () {
                bnItems.forEach(i => i.classList.remove('is-active'));
                this.classList.add('is-active');
            });
        });

        const sectionEls = [];
        bnItems.forEach(item => {
            const sec = document.getElementById(item.dataset.section);
            if (sec) sectionEls.push({ id: item.dataset.section, el: sec, item });
        });

        if (sectionEls.length) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        bnItems.forEach(i => i.classList.remove('is-active'));
                        const match = sectionEls.find(s => s.el === entry.target);
                        if (match) match.item.classList.add('is-active');
                    }
                });
            }, { rootMargin: '-45% 0px -50% 0px', threshold: 0 });

            sectionEls.forEach(s => observer.observe(s.el));
        }
    }

    const checkIn = document.getElementById('check_in');
    const checkOut = document.getElementById('check_out');
    if (checkIn && checkOut) {
        checkIn.addEventListener('change', function () {
            const d = new Date(this.value);
            d.setDate(d.getDate() + 1);
            checkOut.min = d.toISOString().split('T')[0];
            if (checkOut.value && checkOut.value <= this.value) {
                checkOut.value = '';
            }
        });
    }
});