<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">
<div class="row w-100 m-0 justify-content-center mt-5">
    <div class="col-12 col-md-6 col-lg-5 col-xl-4 mb-5">
        <div class="card border shadow-lg p-4">
            <div class="card-body">
                <div class="text-center mb-4">
                    <i class="fas fa-user-plus text-primary fa-2x mb-2"></i>
                    <h3 class="fw-bold">Get Started</h3>
                    <p class="text-muted small">Create your free Administrator account today</p>
                </div>
                <form id="registerForm" novalidate autocomplete="off">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">First Name</label>
                            <input name="username" type="text" class="form-control" placeholder="Alex" value="admin" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">Last Name</label>
                            <input name="" value="admin" type="text" class="form-control" placeholder="Jones" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Work Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-envelope"></i></span>
                            <input name="email" type="email" class="form-control border-start-0" placeholder="alex@company.com" value="admin@colpare.com" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-lock"></i></span>
                            <input name="password" type="password" class="form-control border-start-0" value="admin1234" placeholder="Minimum 8 characters" required>
                        </div>
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="terms" required>
                        <label class="form-check-label small text-muted" for="terms">I accept the <a href="#" class="text-decoration-none">Terms of Service</a> & <a href="#" class="text-decoration-none">Privacy Policy</a></label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-medium">Create Account</button>
                </form>
                <div class="text-center mt-4">
                    <p class="small text-muted mb-0">Already have an account? <a href="#login" class="text-decoration-none fw-medium">Log in</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- <script src="assets/js/auth.js"></script> -->
<script>
    bindAuthForm('registerForm', 'register',
        (form) => ({
            username: form.username.value.trim(),
            email: form.email.value.trim(),
            password: form.password.value
        }),
        (data) => {
            showToast('Registration successful. Please log in.', 'success');
            setTimeout(() => window.location.href = `/login`, 800);
        }
    );
</script>