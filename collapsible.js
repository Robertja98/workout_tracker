document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('details.collapsible[data-storage]').forEach(detail => {
        const key = detail.dataset.storage;
        if (!key) return;
        const storedState = localStorage.getItem(key);
        if (storedState !== null) {
            detail.open = storedState === 'true';
        }
        detail.addEventListener('toggle', () => {
            localStorage.setItem(key, detail.open ? 'true' : 'false');
        });
    });
});
