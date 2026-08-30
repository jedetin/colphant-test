<?php

// namespace Ceaser\Core; // Use a proper namespace for your application
include '../vendor/autoload.php';
require 'Database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Assuming you are using Composer, you should use the autoloader.
// If not using Composer, you'll need the require statements from your driver code.
// require 'vendor/phpmailer/PHPMailer/src/Exception.php';
// require 'vendor/phpmailer/PHPMailer/src/PHPMailer.php';
// require 'vendor/phpmailer/PHPMailer/src/SMTP.php';


class Mailer
{
    /** @var PHPMailer */
    protected $mail;
    private Database $db;   // <-- declare the property
    /**
     * Mailer constructor. Initializes and configures the PHPMailer instance.
     * @param bool $exceptions Should exceptions be thrown on error? (Default: true)
     */
    public function __construct(bool $exceptions = true)
    {
        $this->mail = new PHPMailer($exceptions);

        // --- SMTP Configuration ---
        $this->mail->SMTPDebug = 0; // Set to 2 for detailed debugging
        $this->mail->isSMTP();
        $this->mail->Host = "smtp.example.com";
        $this->mail->SMTPAuth = true;
        // !! WARNING: Hardcoded credentials. Use ENV variables in production.
        $this->mail->Username = "test@example.com";
        $this->mail->Password = "";
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use 'ssl' (deprecated) or better, PHPMailer::ENCRYPTION_SMTPS
        $this->mail->Port = 465;

        // --- Default Sender Configuration ---
        try {
            // Set default 'From' address and name
            $this->mail->setFrom('test@example.com', 'Colphant System Mail');
        } catch (Exception $e) {
            // Handle the exception if setFrom fails (e.g., invalid address)
            error_log("PHPMailer setFrom failed: " . $e->getMessage());
        }

        $this->mail->iSHTML(true);
        $this->mail->AltBody = 'This is a non-HTML email. Please use an HTML-compatible viewer to see the full message.';

        $this->db = new Database('colauth');
    }


    private function logEmail(
        ?int $userId,
        string $recipientEmail,
        string $emailType,
        string $subject,
        string $status,
        ?string $providerMessageId = null,
        ?string $errorMessage = null
    ): void {
        $sentAt = $status === 'sent' ? date('Y-m-d H:i:s') : null;

        $stmt = $this->db->prepare(
            "INSERT INTO email_log (user_id, recipient_email, email_type, subject, status, provider_message_id, error_message, sent_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'isssssss',
            $userId,
            $recipientEmail,
            $emailType,
            $subject,
            $status,
            $providerMessageId,
            $errorMessage,
            $sentAt
        );
        $stmt->execute();
    }


    /**
     * Sends an email to a recipient.
     * * @param string $recipientName The recipient's name.
     * @param string $recipientMail The recipient's email address.
     * @param string $subject The subject of the email (your 'header').
     * @param string $body The HTML content of the email.
     * @return bool True on successful sending, False otherwise.
     * @throws Exception If PHPMailer throws an exception during setup/send.
     */
    public function send(string $recipientName, string $recipientMail, string $subject, string $body, ?int $userId = null, string $emailType = 'other'): bool
    {

        $this->mail->clearAddresses();

        try {
            $this->mail->addAddress($recipientMail, $recipientName);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            $sent = true;
            // $sent = $this->mail->send();

            $this->logEmail(
                $userId,
                $recipientMail,
                $emailType,
                $subject,
                $sent ? 'sent' : 'failed',
                null, // provider_message_id — PHPMailer/SMTP doesn't return one natively; leave null unless you switch to an API-based provider (SES/SendGrid) that does
                $sent ? null : $this->getError()
            );

            return $sent;
        } catch (Exception $e) {
            $this->logEmail($userId, $recipientMail, $emailType, $subject, 'failed', null, $this->getError());
            return false;
        }
        // // Clear previous addresses in case the object is reused
        // $this->mail->clearAddresses();
        // $this->mail->clearCCs();
        // $this->mail->clearBCCs();

        // // Add recipient
        // $this->mail->addAddress($recipientMail, $recipientName);

        // // Set subject and body
        // $this->mail->Subject = $subject;
        // $this->mail->Body = $body;


        // // Attempt to send
        // return $this->mail->send();


    }

    /**
     * Get the last error message from PHPMailer.
     * @return string
     */
    public function getError(): string
    {
        return $this->mail->ErrorInfo;
    }
}
