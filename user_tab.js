function initUserTab() {
    const bodyId = document.body ? document.body.dataset.userId : '';
    const storedId = sessionStorage.getItem('activeUserId');
    const activeUserId = bodyId || storedId;

    if (activeUserId) {
        sessionStorage.setItem('activeUserId', activeUserId);
        window.ACTIVE_USER_ID = activeUserId;
    }

    if (!activeUserId) {
        return;
    }

    const switchSelect = document.querySelector('[data-user-switch]');
    if (switchSelect) {
        if (switchSelect.value !== activeUserId) {
            switchSelect.value = activeUserId;
        }
        switchSelect.addEventListener('change', () => {
            const nextId = switchSelect.value;
            if (!nextId) {
                return;
            }
            window.dispatchEvent(new CustomEvent('workout:beforeUserSwitch', { detail: { nextUserId: nextId } }));
            sessionStorage.setItem('activeUserId', nextId);
            window.ACTIVE_USER_ID = nextId;
            const nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set('user', nextId);
            window.location.href = nextUrl.pathname + nextUrl.search + nextUrl.hash;
        });
    }

    document.querySelectorAll('a[href]').forEach(link => {
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
            return;
        }
        try {
            const url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin) {
                return;
            }
            if (!url.searchParams.has('user')) {
                url.searchParams.set('user', activeUserId);
                link.setAttribute('href', url.pathname + url.search + url.hash);
            }
        } catch (error) {
            return;
        }
    });

    document.querySelectorAll('form').forEach(form => {
        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        const fieldName = method === 'GET' ? 'user' : 'user_id';
        if (form.querySelector(`input[name="${fieldName}"]`)) {
            return;
        }
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = fieldName;
        input.value = activeUserId;
        form.appendChild(input);
    });
}

if (document.body) {
    initUserTab();
} else {
    document.addEventListener('DOMContentLoaded', initUserTab);
}
