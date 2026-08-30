/**
 * spa.js
 *
 * Handles client-side navigation for the SPA.
 *
 * - Sends POST JSON to router.php for all navigations.
 * - On hard refresh / direct URL load, server has already rendered content
 *   into #app — so we skip the initial fetch entirely.
 * - Re-executes <script> tags found in fetched HTML (innerHTML suppresses them).
 * - Uses event delegation for clicks — no inline onclick attributes needed.
 */

(function () {

    const APP = document.getElementById('app');

    // --------------------------------------------------
    // Script re-execution after innerHTML injection
    // --------------------------------------------------
    function executeScripts(container) {
        container.querySelectorAll('script').forEach(oldScript => {
            const newScript = document.createElement('script');

            // Copy attributes (e.g. type, src)
            Array.from(oldScript.attributes).forEach(attr => {
                newScript.setAttribute(attr.name, attr.value);
            });

            // Copy inline content
            if (oldScript.src) {
                // External script: browser will load + execute on append
                newScript.src = oldScript.src;
            } else {
                newScript.textContent = oldScript.textContent;
            }

            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    // --------------------------------------------------
    // Core navigation
    // --------------------------------------------------
    function loadPage(url, push = true) {
        // Normalize trailing slash client-side too
        console.log('[SPA] navigating to', url);
        if (url.length > 1 && url.endsWith('/')) {
            url = url.slice(0, -1);
        }

        fetch('router.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url }),
        })
            .then(res => {
                // Surface server errors visibly rather than silently injecting them
                if (!res.ok) {
                    return res.text().then(msg => {
                        throw new Error(`[${res.status}] ${msg}`);
                    });
                }
                return res.text();
            })
            .then(html => {
                APP.innerHTML = html;
                executeScripts(APP);
                if (push) history.pushState({ url }, '', url);
                window.scrollTo(0, 0);
            })
            .catch(err => {
                console.error('SPA navigation error:', err);
                APP.innerHTML = `<div class="card border-round bg-white my-5 py-5">
                    <div class="card-body"><p>Navigation failed. Please try again.</p></div>
                </div>`;
            });
    }

    // --------------------------------------------------
    // Click delegation — handles dynamically added links too
    // --------------------------------------------------
    document.addEventListener('click', e => {
        const a = e.target.closest('a[data-spa]');
        if (!a) return;

        e.preventDefault();

        const u = new URL(a.href, location.origin);

        // Ignore cross-origin links that somehow got data-spa
        if (u.origin !== location.origin) return;

        loadPage(u.pathname + u.search);
    });

    // --------------------------------------------------
    // Back / Forward
    // --------------------------------------------------
    window.addEventListener('popstate', () => {
        loadPage(location.pathname + location.search, false);
    });

    // --------------------------------------------------
    // Initial load: skip fetch if server already rendered content
    // Server sets data-spa-rendered on #app when it bootstraps the page
    // --------------------------------------------------
    if (APP.dataset.spaRendered === 'true') {
        // Content already in DOM from server — just record state
        history.replaceState(
            { url: location.pathname + location.search },
            '',
            location.pathname + location.search
        );
    } else {
        // Fallback: #app is empty for some reason, fetch it
        loadPage(location.pathname + location.search, false);
    }
    // console.log('[SPA] navigating to', url);
})();

