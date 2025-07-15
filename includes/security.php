<?php
class Security {
    private $db;
    private $session_lifetime = 86400; // 24 hours
    
    public function __construct($database) {
        $this->db = $database;
        $this->startSecureSession();
    }
    
    /**
     * Start a secure session with proper configuration
     */
    private function startSecureSession() {
        if (session_status() == PHP_SESSION_NONE) {
            // Configure session security settings
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) {
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize input data
     */
    public function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email format
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Hash password securely
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    
    /**
     * Verify password hash
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Check login rate limiting
     */
    public function checkLoginRateLimit($ip, $email = null) {
        $stmt = $this->db->prepare("
            SELECT attempts_count, locked_until 
            FROM login_attempts 
            WHERE ip_address = ? OR email = ?
            ORDER BY last_attempt DESC 
            LIMIT 1
        ");
        $stmt->execute([$ip, $email]);
        $result = $stmt->fetch();
        
        if ($result) {
            // Check if currently locked
            if ($result['locked_until'] && strtotime($result['locked_until']) > time()) {
                return false;
            }
            
            // Check if too many attempts
            if ($result['attempts_count'] >= 5) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Record login attempt
     */
    public function recordLoginAttempt($ip, $email, $success = false) {
        if ($success) {
            // Clear failed attempts on successful login
            $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR email = ?");
            $stmt->execute([$ip, $email]);
        } else {
            // Record failed attempt
            $stmt = $this->db->prepare("
                INSERT INTO login_attempts (ip_address, email, attempts_count, last_attempt, locked_until) 
                VALUES (?, ?, 1, NOW(), NULL)
                ON DUPLICATE KEY UPDATE 
                    attempts_count = attempts_count + 1,
                    last_attempt = NOW(),
                    locked_until = CASE 
                        WHEN attempts_count >= 4 THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                        ELSE NULL
                    END
            ");
            $stmt->execute([$ip, $email]);
        }
    }
    
    /**
     * Create user session
     */
    public function createUserSession($user_id, $user_type = 'user') {
        $session_id = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + $this->session_lifetime);
        
        // Store session in database
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $session_id,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expires_at
        ]);
        
        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_type'] = $user_type;
        $_SESSION['session_id'] = $session_id;
        $_SESSION['expires_at'] = $expires_at;
        
        return $session_id;
    }
    
    /**
     * Validate user session
     */
    public function validateUserSession() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            SELECT user_id, expires_at 
            FROM user_sessions 
            WHERE session_id = ? AND expires_at > NOW()
        ");
        $stmt->execute([$_SESSION['session_id']]);
        $result = $stmt->fetch();
        
        if (!$result || $result['user_id'] != $_SESSION['user_id']) {
            $this->destroyUserSession();
            return false;
        }
        
        return true;
    }
    
    /**
     * Destroy user session
     */
    public function destroyUserSession() {
        if (isset($_SESSION['session_id'])) {
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE session_id = ?");
            $stmt->execute([$_SESSION['session_id']]);
        }
        
        session_destroy();
        session_start();
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return $this->validateUserSession();
    }
    
    /**
     * Get current user ID
     */
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current user type
     */
    public function getUserType() {
        return $_SESSION['user_type'] ?? null;
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encryptData($data, $key = null) {
        if ($key === null) {
            $key = $this->getEncryptionKey();
        }
        
        $method = 'AES-256-GCM';
        $iv = random_bytes(16);
        $tag = '';
        
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv, $tag);
        
        return base64_encode($iv . $tag . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decryptData($encryptedData, $key = null) {
        if ($key === null) {
            $key = $this->getEncryptionKey();
        }
        
        $data = base64_decode($encryptedData);
        $method = 'AES-256-GCM';
        
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $encrypted = substr($data, 32);
        
        return openssl_decrypt($encrypted, $method, $key, 0, $iv, $tag);
    }
    
    /**
     * Get encryption key (should be stored securely)
     */
    private function getEncryptionKey() {
        // In production, store this key securely (environment variable, key management service, etc.)
        return hash('sha256', 'your-secret-encryption-key-change-this-in-production');
    }
    
    /**
     * Generate secure random token
     */
    public function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions() {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
        $stmt->execute();
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent($action, $details = null) {
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs (user_id, action, ip_address, user_agent, new_values) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $this->getUserId(),
            $action,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $details ? json_encode($details) : null
        ]);
    }
    
    /**
     * Validate file upload
     */
    public function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        if ($file['size'] > $maxSize) {
            return false;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate 2FA secret
     */
    public function generate2FASecret() {
        return $this->generateSecureToken(16);
    }
    
    /**
     * Verify 2FA token
     */
    public function verify2FAToken($secret, $token) {
        // Simple TOTP implementation
        $timeSlice = floor(time() / 30);
        
        for ($i = -1; $i <= 1; $i++) {
            $calculatedToken = $this->generateTOTP($secret, $timeSlice + $i);
            if (hash_equals($calculatedToken, $token)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate TOTP token
     */
    private function generateTOTP($secret, $timeSlice) {
        $key = base32_decode($secret);
        $time = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $code = ((ord($hash[$offset]) & 0x7f) << 24) |
                ((ord($hash[$offset + 1]) & 0xff) << 16) |
                ((ord($hash[$offset + 2]) & 0xff) << 8) |
                (ord($hash[$offset + 3]) & 0xff);
        return sprintf('%06d', $code % 1000000);
    }
}

/**
 * Base32 decode function for 2FA
 */
function base32_decode($input) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper($input);
    $output = '';
    
    for ($i = 0; $i < strlen($input); $i += 8) {
        $chunk = substr($input, $i, 8);
        $chunk = str_pad($chunk, 8, '=');
        
        $binaryString = '';
        for ($j = 0; $j < 8; $j++) {
            $char = $chunk[$j];
            if ($char === '=') {
                break;
            }
            $binaryString .= sprintf('%05b', strpos($alphabet, $char));
        }
        
        for ($j = 0; $j < strlen($binaryString); $j += 8) {
            $byte = substr($binaryString, $j, 8);
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }
    }
    
    return $output;
}
?>