

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

/**
 * Keep the navigation shell in place when a sidebar link is selected.
 * Laravel still renders the destination server-side; only the content area and
 * navigation markup are swapped in the browser.
 */
const canUseInAppNavigation = (link, event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
    }

    if (link.target || link.hasAttribute('download')) {
        return false;
    }

    const destination = new URL(link.href, window.location.href);

    return destination.origin === window.location.origin && destination.pathname !== window.location.pathname;
};

const replaceNavigation = (documentResponse) => {
    const currentNavs = [...document.querySelectorAll('[data-sidebar-nav]')];
    const nextNavs = [...documentResponse.querySelectorAll('[data-sidebar-nav]')];

    currentNavs.forEach((currentNav, index) => {
        const nextNav = nextNavs[index];

        if (!nextNav) {
            return;
        }

        const scrollTop = currentNav.scrollTop;
        Alpine.destroyTree(currentNav);
        currentNav.replaceWith(nextNav);
        Alpine.initTree(nextNav);
        nextNav.scrollTop = scrollTop;
    });
};

const navigateInApp = async (url, pushState = true) => {
    try {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok || response.redirected) {
            window.location.assign(url);
            return;
        }

        const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
        const currentContent = document.querySelector('#app-content');
        const nextContent = responseDocument.querySelector('#app-content');

        if (!currentContent || !nextContent) {
            window.location.assign(url);
            return;
        }

        Alpine.destroyTree(currentContent);
        currentContent.replaceWith(nextContent);
        Alpine.initTree(nextContent);
        replaceNavigation(responseDocument);
        document.title = responseDocument.title;

        if (pushState) {
            window.history.pushState({}, '', url);
        }

        window.dispatchEvent(new CustomEvent('spa-navigated'));
        window.scrollTo(0, 0);
    } catch {
        window.location.assign(url);
    }
};

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-sidebar-link]');

    if (!link || !canUseInAppNavigation(link, event)) {
        return;
    }

    event.preventDefault();
    navigateInApp(link.href);
});

window.addEventListener('popstate', () => navigateInApp(window.location.href, false));
