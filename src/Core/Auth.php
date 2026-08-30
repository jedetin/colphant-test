<?php

// namespace Auth;

// use mysqli;

class Auth
{


    private $host = 'localhost';
    private $username = 'root';
    private $password = '';
    private $database = 'colauth';
    private $connection;

    public function __construct()
    {
        date_default_timezone_set('Asia/Kolkata');
        $this->connection = new mysqli($this->host, $this->username, $this->password, $this->database);
        if ($this->connection->connect_error) {
            throw new \RuntimeException('Auth DB connection failed');
        }
    }
    public function query($sql, $params = [])
    {
        $stmt = $this->connection->prepare($sql);
        if ($params) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt;
    }

    // Get user details for profile display
    public function getUser($userId)
    {
        $stmt = $this->query("SELECT id, username, email, profile_picture_url, bio FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
        return $stmt->get_result()->fetch_assoc();
    }

    // Check if user exists
    public function queryUser($email)
    {
        $stmt = $this->query("SELECT * FROM users WHERE email = ?", [$email]);

        return $stmt->get_result()->fetch_assoc();
    }

    // Register a new user
    public function registerUser($username, $email, $password)
    {
        if (empty($username) || empty($email) || empty($password)) {
            return "All fields are required";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email format";
        }

        if ($this->queryUser($email)) {
            return "If this email isn't taken, check your inbox";
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $result = $this->query(
            "INSERT INTO users (username, email, password) VALUES (?, ?, ?)",
            [$username, $email, $hashedPassword]
        );

        return $result ? true : "Database error during registration";
    }

    // Handle user login
    public function loginUser($email, $password)
    {
        static $dummyHash = '$2y$10$abcdefghijklmnopqrstuuVGdummydummydummydummydumm';

        $user = $this->queryUser($email);
        if (!$user || $user['deleted_at'] !== null) {
            password_verify($password, $dummyHash);
            $this->logLoginAttempt(null, 'failed', 'no_such_user');
            return ["code" => 401, "status" => "fail", 'response' => 'Invalid credentials.'];
        }

        if ($user['lock_until'] && strtotime($user['lock_until']) > time()) {
            $this->logLoginAttempt($user['id'], 'locked', 'account_locked');
            return ["code" => 400, "status" => "fail", 'response' => 'Account locked. Try again later.'];
        }

        if (password_verify($password, $user['password'])) {
            $this->query("UPDATE users SET last_login = NOW(), failed_login_attempts = 0, lock_until = NULL WHERE id = ?", [$user['id']]);

            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $this->query("UPDATE users SET password = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }

            unset($user['password'], $user['reset_token'], $user['reset_token_expiry'], $user['failed_login_attempts']);

            $this->logLoginAttempt($user['id'], 'success');
            return ["code" => 200, "status" => "success", 'response' => 'Login successful.', 'user' => $user];
        }

        $failedAttempts = $user['failed_login_attempts'] + 1;
        $lockTime = ($failedAttempts >= 5) ? date('Y-m-d H:i:s', strtotime('+30 minutes')) : NULL;
        $this->query("UPDATE users SET failed_login_attempts = ?, lock_until = ? WHERE id = ?", [$failedAttempts, $lockTime, $user['id']]);

        $this->logLoginAttempt($user['id'], 'failed', 'wrong_password');
        return ["code" => 401, "status" => "fail", 'response' => 'Invalid credentials.'];
    }

    public function lockUser($userId, $minutes = 30)
    {
        return $this->query("UPDATE users SET lock_until = ? WHERE id = ?", [date('Y-m-d H:i:s', strtotime("+$minutes minutes")), $userId]);
    }

    // Unlock user account manually
    public function unlockUser($userId)
    {
        return $this->query("UPDATE users SET lock_until = NULL WHERE id = ?", [$userId]);
    }

    public function generateResetToken($email)
    {
        $user = $this->queryUser($email); // Assuming this returns a user array/object
        if (!$user) {
            return ['error' => 'User not found.'];
        }

        // --- COOLDOWN CHECK ---
        // If the expiry is in the future, a request was already made recently.
        if (!empty($user['reset_token_expiry']) && strtotime($user['reset_token_expiry']) > time()) {
            return ['error' => 'limit_reached', 'message' => 'A reset link was already sent. Please wait before requesting another.'];
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $this->query("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?", [$tokenHash, $expiry, $email]);
        return [
            'token' => $token, // raw token — only ever exists here and in the email you send. Never stored.
            'name'  => $user['name'] ?? 'User',
            'email' => $email
        ];
    }
    public function verifyResetToken($email, $token)
    {
        $tokenHash = hash('sha256', $token);
        $user = $this->query(
            "SELECT email FROM users WHERE email = ? AND reset_token = ? AND reset_token_expiry > NOW()",
            [$email, $tokenHash]
        )->get_result()->fetch_assoc();
        if (!$user) {
            return ['error' => 'Invalid or expired reset link'];
        }
        return ['success' => true];
    }
    // Reset user password
    public function resetPassword($token, $newPassword)
    {
        $tokenHash = hash('sha256', $token);
        $user = $this->query("SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()", [$tokenHash])->get_result()->fetch_assoc();
        if (!$user) {
            return ['error' => 'Invalid or expired token.'];
        }
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->query("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?", [$hashedPassword, $user['id']]);
        return ['success' => 'Password reset successfully.'];
    }

    // Soft delete a user
    public function deleteUser($userId)
    {
        return $this->query("UPDATE users SET deleted_at = NOW() WHERE id = ?", [$userId]);
    }

    private function logLoginAttempt($userId, $status, $failureReason = null)
    {
        $ip = $this->getClientIp();
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 1000); // TEXT column, but cap it — avoid junk floods

        $this->query(
            "INSERT INTO login_log (user_id, ip_address, user_agent, status, failure_reason) VALUES (?, ?, ?, ?, ?)",
            [$userId, $ip, $userAgent, $status, $failureReason]
        );
    }

    private function getClientIp()
    {
        // Never trust X-Forwarded-For blindly — trivially spoofable by the client.
        // Only trust it if you control the proxy in front of this (e.g. your own nginx/load balancer)
        // and that proxy overwrites rather than appends. Otherwise REMOTE_ADDR is the only honest value.
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        return $ip ? substr($ip, 0, 45) : null;
    }

    public function close()
    {
        $this->connection->close();
    }
}
