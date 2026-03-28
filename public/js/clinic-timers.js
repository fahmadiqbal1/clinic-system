/**
 * Aviva HealthCare — Shared Wait Timer + Notification Timer Utilities
 * Included once in layouts/app.blade.php.
 *
 * - updateWaitTimers()    : updates .wait-timer elements (data-since attribute)
 * - updateNotifTimers()   : updates .notif-timer elements (data-assigned-at attribute)
 * - pauseOnHidden(fn, ms) : returns a managed interval that pauses when the tab is hidden
 */
(function(window) {
    'use strict';

    /**
     * Update all .wait-timer elements.
     * Element must have data-since="ISO datetime" attribute.
     */
    function updateWaitTimers() {
        document.querySelectorAll('.wait-timer').forEach(function(el) {
            var since = new Date(el.dataset.since);
            var diff = Math.max(0, Math.floor((Date.now() - since) / 1000));
            var h = Math.floor(diff / 3600);
            var m = Math.floor((diff % 3600) / 60);
            var s = diff % 60;
            el.textContent = (h > 0 ? h + 'h ' : '') + m + 'm ' + s + 's';
            if (diff < 180)       { el.style.color = 'var(--accent-success)'; }
            else if (diff < 600)  { el.style.color = 'var(--accent-warning)'; }
            else if (diff < 1200) { el.style.color = 'var(--accent-secondary)'; }
            else                   { el.style.color = 'var(--accent-danger)'; }
        });
    }

    /**
     * Update all .notif-timer elements.
     * Element must have data-assigned-at="ISO datetime" attribute.
     */
    function updateNotifTimers() {
        document.querySelectorAll('.notif-timer').forEach(function(el) {
            var since = new Date(el.dataset.assignedAt);
            var diff = Math.max(0, Math.floor((Date.now() - since) / 1000));
            var h = Math.floor(diff / 3600);
            var m = Math.floor((diff % 3600) / 60);
            var s = diff % 60;
            el.textContent = h > 0 ? h + 'h ' + m + 'm ago' : m > 0 ? m + 'm ago' : s + 's ago';
        });
    }

    /**
     * Combined update (called from layout).
     */
    function updateTimers() {
        updateWaitTimers();
        updateNotifTimers();
    }

    /**
     * Create a setInterval that automatically pauses when the browser tab is
     * hidden and resumes when it becomes visible again.
     * Returns a handle object with a .stop() method.
     *
     * @param {Function} fn   Callback to run on each tick
     * @param {number}   ms   Interval in milliseconds
     */
    function pauseOnHidden(fn, ms) {
        var id = null;

        function start() {
            if (id === null) {
                fn(); // run once immediately on resume
                id = setInterval(fn, ms);
            }
        }

        function stop() {
            if (id !== null) {
                clearInterval(id);
                id = null;
            }
        }

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) { stop(); } else { start(); }
        });

        start(); // kick off straight away

        return { stop: stop, start: start };
    }

    // Expose globally
    window.updateWaitTimers  = updateWaitTimers;
    window.updateNotifTimers = updateNotifTimers;
    window.updateTimers      = updateTimers;
    window.pauseOnHidden     = pauseOnHidden;

}(window));
