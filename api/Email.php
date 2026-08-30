<?php
// require_once '../config.php';
require_once '../src/Email.php';

header('Content-Type: application/json');

$recipientName  = trim($_POST['name'] ?? '');
$recipientEmail = trim($_POST['email'] ?? '');
$subject        = trim($_POST['subject'] ?? '');
$body           = $_POST['body'] ?? '';


// basic validation — nothing fancy, just guard against empty/malformed input before hitting PHPMailer
if (empty($recipientName) || empty($recipientEmail) || empty($subject) || empty($body)) {
    http_response_code(422);
    echo json_encode(['status' => 'fail', 'response' => 'Name, email, subject, and body are all required.']);
    exit;
}

if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['status' => 'fail', 'response' => 'Invalid email address.']);
    exit;
}

$mailer = new Mailer();
$result = $mailer->send($recipientName, $recipientEmail, $subject, $body, null, 'other');

if ($result) {
    http_response_code(200);
    echo json_encode(['status' => 'success', 'response' => 'Mail sent successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'fail', 'response' => 'Mail send failed.', 'error' => $mailer->getError()]);
}
