/** At most one request per task; pause while hidden or leaving the document. */
export function createPollingTask(callback, { interval = 15000, win = window, doc = document } = {}) {
    let timer = null;
    let controller = null;
    let running = false;
    let leaving = false;

    const refresh = async () => {
        if (!running || leaving || doc.hidden || controller) return;
        const request = new AbortController();
        controller = request;
        try {
            await callback(request.signal);
        } catch (error) {
            if (error.name !== 'AbortError') win.console?.warn('Attendance refresh failed; retrying later.');
        } finally {
            if (controller === request) controller = null;
        }
    };
    const pause = () => {
        leaving = true;
        controller?.abort();
    };
    const resume = () => { leaving = false; };
    const visibility = () => {
        if (doc.hidden) controller?.abort();
        else refresh();
    };

    return {
        refresh,
        start() {
            if (running) return;
            running = true;
            leaving = false;
            timer = win.setInterval(refresh, interval);
            win.addEventListener('iqams:navigating', pause);
            win.addEventListener('pagehide', pause);
            win.addEventListener('pageshow', resume);
            win.addEventListener('iqams:navigation-cancelled', resume);
            doc.addEventListener('visibilitychange', visibility);
        },
        stop() {
            running = false;
            win.clearInterval(timer);
            controller?.abort();
            win.removeEventListener('iqams:navigating', pause);
            win.removeEventListener('pagehide', pause);
            win.removeEventListener('pageshow', resume);
            win.removeEventListener('iqams:navigation-cancelled', resume);
            doc.removeEventListener('visibilitychange', visibility);
        },
    };
}
