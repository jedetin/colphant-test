<?php
// require_once __DIR__ . '/../../config.php';
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">

<div class="container" style="max-width: 400px; margin-top: 80px;">
    <h3 class="mb-4">Login</h3>
    <form id="loginForm" novalidate>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100" id="loginBtn">Login</button>
    </form>
</div>

<!-- Bootstrap toast container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="authToast" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="authToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
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
            setTimeout(() => window.location.href = `/home`, 800);
        }
    );
</script>