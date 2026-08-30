<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">

<div class="row w-100 m-0 justify-content-center mt-5">

    <div class="col-12 col-md-6 col-lg-5 col-xl-4 mb-5">
        <div class="card border-0 shadow-sm p-4">
            <div class="card-body">
                <div id="forget-verify">
                    <div class="text-center mb-4">
                        <i class="fas fa-key text-success fa-2x mb-2"></i>
                        <h3 class="fw-bold">Verify & Update</h3>
                        <p class="text-muted small">Input the 6-digit code sent to your email along with your updated password choice.</p>
                    </div>
                    <form id="resetForm" autocomplete="off" novalidate>
                        <div class="mb-3">
                            <label for="token" class="form-label small fw-semibold text-secondary">Verification Token</label>
                            <input
                                name="token"
                                id="token"
                                type="text"
                                class="form-control font-monospace"
                                style="letter-spacing: 1px;"
                                placeholder="Paste the code from your email"
                                autocomplete="off"
                                required>
                            <div class="invalid-feedback">Please enter the verification token.</div>
                        </div>

                        <div class="mb-3">
                            <label for="newPassword" class="form-label small fw-semibold text-secondary">New Password</label>
                            <div class="input-group">
                                <input
                                    name="password"
                                    id="newPassword"
                                    type="password"
                                    class="form-control"
                                    placeholder="Choose a strong password"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword" tabindex="-1">
                                    👁
                                </button>
                            </div>
                            <div class="form-text" id="pwStrengthHint">At least 8 characters.</div>
                        </div>

                        <div class="mb-4">
                            <label for="confirmPassword" class="form-label small fw-semibold text-secondary">Confirm Password</label>
                            <div class="input-group">
                                <input
                                    name="confirmPassword"
                                    id="confirmPassword"
                                    type="password"
                                    class="form-control"
                                    placeholder="Re-enter password"
                                    autocomplete="new-password"
                                    required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword" tabindex="-1">
                                    👁
                                </button>
                            </div>
                            <div class="invalid-feedback" id="pwMismatchFeedback">Passwords do not match.</div>
                        </div>

                        <button type="submit" class="btn btn-success w-100 py-2 fw-medium" id="resetBtn">
                            Update Password
                        </button>
                    </form>
                </div>

                <div class="text-center mt-4">
                    <a href="login" data-spa class="small text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Back to login</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- <script src="/assets/js/auth.js"></script> -->
<script>
    bindAuthForm('resetForm', 'reset-confirm',
        (form) => ({
            token: form.token.value.trim(),
            password: form.password.value
        }),
        (data) => {
            showToast('Password reset successful. Please log in.', 'success');
            setTimeout(() => window.location.href = `/login`, 800);
        }
    );

    // Client-side confirm-password check — runs before bindAuthForm's own listener
    document.getElementById('resetForm')?.addEventListener('submit', (e) => {
        const pw = document.getElementById('newPassword');
        const confirmPw = document.getElementById('confirmPassword');

        if (pw.value !== confirmPw.value) {
            e.preventDefault();
            e.stopImmediatePropagation(); // stop bindAuthForm's listener from also firing
            confirmPw.classList.add('is-invalid');
            showToast('Passwords do not match.', 'warning');
            return;
        }
        confirmPw.classList.remove('is-invalid');

        if (pw.value.length < 8) {
            e.preventDefault();
            e.stopImmediatePropagation();
            pw.classList.add('is-invalid');
            showToast('Password must be at least 8 characters.', 'warning');
            return;
        }
        pw.classList.remove('is-invalid');
    }, true); // capture phase — runs first

    // Show/hide password toggles
    function bindPasswordToggle(btnId, inputId) {
        const btn = document.getElementById(btnId);
        const input = document.getElementById(inputId);
        if (!btn || !input) return;

        btn.addEventListener('click', () => {
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.querySelector('i').className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    }

    bindPasswordToggle('toggleNewPassword', 'newPassword');
    bindPasswordToggle('toggleConfirmPassword', 'confirmPassword');
</script>