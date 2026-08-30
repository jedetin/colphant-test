class AuthClient {
    static csrfToken() {
        return document.querySelector('meta[name="csrf-token"]').content;
    }

    static async post(action, payload) {
        const res = await fetch(`api/Auth.php?action=${encodeURIComponent(action)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken()
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });

        let data;
        try {
            data = await res.json();
        } catch {
            data = { status: 'fail', response: 'Unexpected server response.' };
        }
        return { ok: res.ok, status: res.status, data };
    }
}

function showToast(message, type = 'danger') {
    const toastEl = document.getElementById('authToast');
    const bodyEl = document.getElementById('authToastBody');

    toastEl.classList.remove('text-bg-danger', 'text-bg-success', 'text-bg-warning');
    toastEl.classList.add(`text-bg-${type}`);

    // textContent, never innerHTML — this renders server-supplied error strings,
    // must not be interpretable as HTML even if the API response is ever compromised
    bodyEl.textContent = message;

    const toast = bootstrap.Toast.getOrCreateInstance(toastEl);
    toast.show();
}

// document.getElementById('loginForm')?.addEventListener('submit', async (e) => {
//     e.preventDefault();

//     const btn = document.getElementById('loginBtn');
//     btn.disabled = true;
//     btn.textContent = 'Logging in...';

//     const email = document.getElementById('email').value.trim();
//     const password = document.getElementById('password').value;

//     const { ok, data } = await AuthClient.post('login', { email, password });

//     if (ok && data.status === 'success') {
//         showToast('Login successful. Redirecting...', 'success');
//         setTimeout(() => window.location.href = `${BASE_URL}/dashboard`, 800);
//     } else {
//         showToast(data.response || 'Login failed.', 'danger');
//         btn.disabled = false;
//         btn.textContent = 'Login';
//     }
// });

function bindAuthForm(formId, action, buildPayload, onSuccess) {
    const form = document.getElementById(formId);
    if (!form) return; // form not on this page, skip silently

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Please wait...';

        const payload = buildPayload(form);
        const { ok, data } = await AuthClient.post(action, payload);

        if (ok && data.status === 'success') {
            onSuccess(data);
        } else {
            showToast(data.response || 'Something went wrong.', 'danger');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
}