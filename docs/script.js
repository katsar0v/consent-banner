(function() {
    'use strict';

    const body = document.body;
    const storageKey = body.getAttribute('data-lang-storage') || 'plugin-docs-lang';
    const defaultLang = body.getAttribute('data-default-lang') || 'en';
    const supportedLangs = ['en', 'bg'];
    const langNames = {
        en: 'EN',
        bg: 'BG'
    };

    function getCurrentLang() {
        const storedLang = localStorage.getItem(storageKey);
        if (supportedLangs.includes(storedLang)) {
            return storedLang;
        }

        const browserLang = (navigator.language || '').split('-')[0];
        if (supportedLangs.includes(browserLang)) {
            return browserLang;
        }

        return defaultLang;
    }

    function updateContent(lang) {
        document.querySelectorAll('[data-en][data-bg]').forEach(function(element) {
            const translated = element.getAttribute('data-' + lang);
            if (translated) {
                element.textContent = translated;
            }
        });

        const current = document.querySelector('.lang-current');
        if (current) {
            current.textContent = langNames[lang];
        }

        document.documentElement.lang = lang;
    }

    function toggleLanguage() {
        const currentLang = getCurrentLang();
        const nextLang = supportedLangs[(supportedLangs.indexOf(currentLang) + 1) % supportedLangs.length];
        localStorage.setItem(storageKey, nextLang);
        updateContent(nextLang);
    }

    function initMobileMenu() {
        const menuToggle = document.getElementById('mobileMenuToggle');
        const nav = document.querySelector('.nav');

        if (!menuToggle || !nav) {
            return;
        }

        menuToggle.addEventListener('click', function() {
            nav.classList.toggle('active');
            menuToggle.classList.toggle('active');
        });

        nav.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                nav.classList.remove('active');
                menuToggle.classList.remove('active');
            });
        });
    }

    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(event) {
                const targetId = anchor.getAttribute('href');
                if (!targetId || targetId === '#') {
                    return;
                }

                const target = document.querySelector(targetId);
                const header = document.querySelector('.header');
                if (!target || !header) {
                    return;
                }

                event.preventDefault();
                const offset = header.offsetHeight + 16;
                window.scrollTo({
                    top: target.getBoundingClientRect().top + window.pageYOffset - offset,
                    behavior: 'smooth'
                });
            });
        });
    }

    function init() {
        updateContent(getCurrentLang());
        initMobileMenu();
        initSmoothScroll();

        const switcher = document.getElementById('langSwitcher');
        if (switcher) {
            switcher.addEventListener('click', toggleLanguage);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
