<?php

declare(strict_types=1);

// require_once __DIR__ . '/../config.php';      // defines BASE_URL, starts session config
require_once '../src/Core/Auth.php';

header('Content-Type: application/json');

// 1. Method + Content-Type enforcement — mirrors the SPA router hardening
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'fail', 'response' => 'Method not allowed.']);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') === false) {
    http_response_code(415);
    echo json_encode(['status' => 'fail', 'response' => 'Content-Type must be application/json.']);
    exit;
}

// 2. CSRF check — custom header, not form field, since this is fetch-only.
//    Token issued at session start, echoed into a <meta> tag on every view.
session_start();
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfHeader)) {
    http_response_code(403);
    echo json_encode(['status' => 'fail', 'response' => 'Invalid or missing CSRF token.']);
    exit;
}

// 3. Parse body once, reused by every action
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['status' => 'fail', 'response' => 'Malformed JSON body.']);
    exit;
}

// 4. Resolve action from the route segment after /api/auth/
//    router.php should pass this in — adjust the source if yours works differently.
//    e.g. POST /api/auth/login -> $action = 'login'
// $action = $routeParams['action'] ?? null; // set by router.php before including this file
$action = $_GET['action'] ?? null;

$allowedActions = ['login', 'register', 'reset-request', 'reset-confirm', 'logout'];

if (!in_array($action, $allowedActions, true)) {
    http_response_code(404);
    echo json_encode(['status' => 'fail', 'response' => 'Unknown auth action.']);
    exit;
}


$auth = new Auth();

try {
    switch ($action) {

        case 'login':
            $email    = trim($body['email'] ?? '');
            $password = $body['password'] ?? '';

            if (empty($email) || empty($password)) {
                http_response_code(422);
                echo json_encode(['status' => 'fail', 'response' => 'Email and password are required.']);
                break;
            }

            $result = $auth->loginUser($email, $password);
            http_response_code($result['code'] ?? 200);

            if (($result['status'] ?? '') === 'success') {
                // Regenerate session ID on privilege change — prevents session fixation
                session_regenerate_id(true);
                $_SESSION['user_id'] = $result['user']['id'];
            }

            echo json_encode($result);
            break;

        case 'register':
            $username = trim($body['username'] ?? '');
            $email    = trim($body['email'] ?? '');
            $password = $body['password'] ?? '';

            $result = $auth->registerUser($username, $email, $password);

            if ($result === true) {
                http_response_code(201);
                echo json_encode(['status' => 'success', 'response' => 'Registration successful.']);
            } else {
                http_response_code(422);
                echo json_encode(['status' => 'fail', 'response' => $result]);
            }
            break;

        case 'reset-request':
            $email = trim($body['email'] ?? '');
            $result = $auth->generateResetToken($email);
            // Always return the same generic response regardless of outcome —
            // don't let this branch leak whether the email exists.
            http_response_code(200);
            echo json_encode(['status' => 'success', 'response' => 'If that email is registered, a reset link has been sent. Code:' . $result['token']]); //. $result['token']]);
            // echo ("<script>console.log(" . json_encode($result) . ")</script>");
            // Send $result['token'] via email here — never in the JSON response.
            break;

        // case 'reset-verify':
        //     $email = trim($body['email'] ?? '');
        //     $token = $body['token'] ?? '';
        //     $result = $auth->verifyResetToken($email, $token);
        //     http_response_code(isset($result['success']) ? 200 : 400);
        //     echo json_encode($result);
        //     break;

        case 'reset-confirm':
            $token       = $body['token'] ?? '';
            $newPassword = $body['password'] ?? '';

            if (empty($token) || empty($newPassword)) {
                http_response_code(422);
                echo json_encode(['status' => 'fail', 'response' => 'Code and new password are required.']);
                break;
            }
            // at the top of the reset-confirm case, before calling resetPassword()
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            // pseudocode — implement against a table or even a simple file/APCu counter locally:
            // if (attempts_from_ip($ip, 'reset-confirm', last_10_minutes) > 10) { 429 + exit; }
            $result = $auth->resetPassword($token, $newPassword);

            if (isset($result['success'])) {
                http_response_code(200);
                echo json_encode(['status' => 'success', 'response' => "Password reset successfully."]);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'fail', 'response' => $result['error'] ?? 'Invalid or expired token.']);
            }
            http_response_code(isset($result['success']) ? 200 : 400);
            // echo json_encode($result);
            break;

        case 'logout':
            session_unset();
            session_destroy();
            http_response_code(200);
            echo json_encode(['status' => 'success', 'response' => 'Logged out.']);
            break;

        default:
            http_response_code(404);
            echo json_encode(['status' => 'fail', 'response' => 'Unknown auth action.']);
    }
} catch (\Throwable $e) {
    error_log($e->getMessage()); // never echo raw exception to client
    http_response_code(500);
    echo json_encode(['status' => 'fail', 'response' => 'Something went wrong. Please try again.']);
} finally {
    $auth->close();
}
