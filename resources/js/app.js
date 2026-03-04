import './bootstrap';

/**
 * Runtime JS Error Logger (development only)
 * Catches uncaught errors and unhandled promise rejections,
 * logs them to a fixed overlay at the bottom of the viewport.
 */
if (import.meta.env.DEV) {
    const createOverlay = () => {
        const el = document.createElement('div');
        el.id = 'js-error-log';
        Object.assign(el.style, {
            position: 'fixed',
            bottom: '0',
            left: '0',
            right: '0',
            maxHeight: '180px',
            overflowY: 'auto',
            background: 'rgba(220,53,69,0.95)',
            color: '#fff',
            fontFamily: 'monospace',
            fontSize: '12px',
            padding: '8px 12px',
            zIndex: '99999',
            display: 'none',
        });
        document.body.appendChild(el);
        return el;
    };

    const logError = (msg) => {
        let overlay = document.getElementById('js-error-log') || createOverlay();
        overlay.style.display = 'block';
        const line = document.createElement('div');
        line.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
        overlay.prepend(line);
        console.error('[ErrorLogger]', msg);
    };

    window.addEventListener('error', (e) => {
        logError(`${e.message} (${e.filename}:${e.lineno}:${e.colno})`);
    });

    window.addEventListener('unhandledrejection', (e) => {
        logError(`Unhandled Promise: ${e.reason}`);
    });
}
