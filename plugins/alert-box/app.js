/**
 * Alert Box Plugin - Client script
 * Intercepts all window.alert calls and redirects them to window.showToast
 */
(function() {
    const originalAlert = window.alert;
    window.alert = function(message) {
        if (typeof window.showToast === 'function') {
            const lower = (message || '').toLowerCase();
            let type = 'info';
            if (lower.includes('failed') || lower.includes('error') || lower.includes('invalid') || lower.includes('require') || lower.includes('not configured')) {
                type = 'error';
            }
            window.showToast(message, type);
        } else {
            originalAlert(message);
        }
    };
})();
