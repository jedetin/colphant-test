<?php
// require __DIR__ . '/vendor/autoload.php';
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Pre-set the URL for router.php so it doesn't read POST body
// (this is an internal call, not a SPA fetch)
$routerUrl = $_SERVER['REQUEST_URI'];

ob_start();
require __DIR__ . '/router.php';
$appContent = ob_get_clean();
?>
<!doctype html>
<html lang="en" class="h-100">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>pjp-generator</title>

    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <script src="assets/js/fa.js">
    </script>

    <!-- Favicons -->
    <link rel="icon" href="assets/images/favicon.ico">
    <script src="/assets/js/auth.js"></script>
</head>

<body>
    <?php include __DIR__ . '/view/include/header.php'; ?>
    <div id="app" data-spa-rendered="true">
        <?= $appContent ?>
    </div>

    <?php include __DIR__ . '/view/include/footer.php'; ?>

    <script src="assets/js/spa.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>