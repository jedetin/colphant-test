<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">

<div class="row w-100 m-0 justify-content-center mt-5">
    <div class="col-12 col-md-6 col-lg-5 col-xl-4 mb-5">
        <div class="card border shadow-lg p-4">
            <div class="card-body">
                <div class="text-center mb-4">
                    <i class="fas fa-lock text-primary fa-2x mb-2"></i>
                    <h3 class="fw-bold">Account Login</h3>
                    <p class="text-muted small">Enter your credentials to access your workspace</p>
                </div>
                <form id="loginForm" novalidate>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control border-start-0" placeholder="name@company.com" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-semibold text-secondary mb-0">Password</label>
                            <a href="forgot" data-spa class="small text-decoration-none">Forgot password?</a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-key"></i></span>
                            <input type="password" name="password" class="form-control border-start-0" placeholder="••••••••" required>
                        </div>
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="rememberMe">
                        <label class="form-check-label small text-muted" for="rememberMe">Keep me signed in for 30 days</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-medium">Sign In</button>
                </form>
                <div class="text-center mt-4">
                    <p class="small text-muted mb-0">Don't have an account? <a href="register" data-spa class="text-decoration-none fw-medium">Sign up</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- <script src="/assets/js/auth.js"></script> -->
<script>
    bindAuthForm('loginForm', 'login',
        (form) => ({
            email: form.email.value.trim(),
            password: form.password.value
        }),
        (data) => {
            showToast('Login successful. Redirecting...', 'success');
            setTimeout(() => window.location.href = `/profile`, 800);
        }
    );
</script>