/** Use browser document navigation so headings, scripts, history and URLs agree. */
export function installNavigationFeedback(win, doc, loader) {
    const begin = () => {
        loader.start();
        win.dispatchEvent(new Event('iqams:navigating'));
    };

    doc.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest?.('a[href]');
        if (!link || (link.target && link.target.toLowerCase() !== '_self') || link.hasAttribute('download')) return;
        const destination = new URL(link.href, win.location.href);
        const current = new URL(win.location.href);
        if (!['http:', 'https:'].includes(destination.protocol)) return;
        if (destination.origin === current.origin && destination.pathname === current.pathname
            && destination.search === current.search && destination.hash) return;
        // Do not preventDefault, fetch HTML, replace content, or push history.
        begin();
    });

    doc.addEventListener('submit', (event) => {
        const form = event.target;
        if (!event.defaultPrevented && form instanceof win.HTMLFormElement
            && (!form.target || form.target.toLowerCase() === '_self') && form.checkValidity()) begin();
    });

    win.addEventListener('pageshow', () => loader.stop());
}
