<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">

<div class="row w-100 m-0 justify-content-center mt-5">

    <div class="col-12 col-md-6 col-lg-5 col-xl-4 mb-5">

        <div class="card border-0 shadow-sm p-4">
            <div class="card-body">

                <div id="forget-confirm" class="mb-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-shield-alt text-warning fa-2x mb-2"></i>
                        <h3 class="fw-bold">Reset Password</h3>
                        <p class="text-muted small">Enter your verified email to receive a password recovery security token.</p>
                    </div>
                    <form id="forgotForm" autocomplete="off">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Account Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-envelope"></i></span>
                                <input name="email" type="email" class="form-control border-start-0" placeholder="alex@company.com" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-medium">Send Reset Token</button>
                    </form>
                </div>

                <hr class="text-muted my-4 opacity-25">



                <div class="text-center mt-4">
                    <a href="login" data-spa class="small text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Back to login</a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- <script src="/assets/js/auth.js"></script> -->
<script>
    bindAuthForm('forgotForm', 'reset-request',
        (form) => ({
            email: form.email.value.trim()
        }),
        (data) => {
            showToast(data.response, 'success');
            setTimeout(() => window.location.href = `verify-code`, 8000);
        }
    );
</script>