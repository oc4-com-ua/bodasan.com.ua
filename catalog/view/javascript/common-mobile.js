const searchOpen = document.getElementById('search-open');
const searchForm = document.getElementById('search-form');
if (searchForm && searchForm) {
    searchOpen.addEventListener('click', () => {
        const body = document.body;
        body.classList.add('search-opened');

        const backdrop = document.createElement('div');
        backdrop.className = 'search-overlay';
        body.appendChild(backdrop);
    });

    document.addEventListener('click', (e) => {
        const searchClose = e.target.closest('.search-overlay');

        if (searchClose) {
            searchClose.remove();
            document.body.classList.remove('search-opened');
        }
    });
}


const menuOpen = document.getElementById('menu-open');
const menuPanel = document.getElementById('menu-panel');
if (menuOpen && menuPanel) {
    menuOpen.addEventListener('click', () => {
        const body = document.body;
        body.classList.add('menu-opened');

        const backdrop = document.createElement('div');
        backdrop.className = 'menu-overlay';
        body.appendChild(backdrop);
    });

    document.addEventListener('click', (e) => {
        const menuClose = e.target.closest('.menu-overlay, #menu-close');

        if (menuClose) {
            document.querySelector('.menu-overlay')?.remove();
            document.body.classList.remove('menu-opened');
        }
    });

    const catalogOpen = document.getElementById('catalog-open');
    if (catalogOpen) {
        catalogOpen.addEventListener('click', () => {
            menuPanel.classList.add('mobile-panel_catalog-opened');
        });
    }

    const catalogClose = document.getElementById('catalog-close');
    if (catalogClose) {
        catalogClose.addEventListener('click', () => {
            menuPanel.classList.remove('mobile-panel_catalog-opened');
        });
    }
}

