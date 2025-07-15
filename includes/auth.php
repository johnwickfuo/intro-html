<?php
require_once 'security.php';
require_once 'email.php';

class Auth {
    private $db;
    private $security;
    private $email;
    
    public function __construct($database) {
        $this->db = $database;
        $this->security = new Security($database);
        $this->email = new Email($database);
    }
    
    /**
     * Register a new user
     */
    public function register($userData) {
        $errors = [];
        
        // Validate input
        if (empty($userData['username']) || strlen($userData['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters long';
        }
        
        if (empty($userData['email']) || !$this->security->validateEmail($userData['email'])) {
            $errors[] = 'Please enter a valid email address';
        }
        
        if (empty($userData['password']) || strlen($userData['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if ($userData['password'] !== $userData['confirm_password']) {
            $errors[] = 'Passwords do not match';
        }
        
        if (empty($userData['first_name']) || empty($userData['last_name'])) {
            $errors[] = 'First name and last name are required';
        }
        
        if (empty($userData['country'])) {
            $errors[] = 'Country is required';
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        // Check if username or email already exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$userData['username'], $userData['email']]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'errors' => ['Username or email already exists']];
        }
        
        // Hash password
        $passwordHash = $this->security->hashPassword($userData['password']);
        
        // Generate email verification token
        $emailToken = $this->security->generateSecureToken();
        
        // Insert user into database
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password_hash, first_name, last_name, phone, country, date_of_birth, email_verification_token) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $userData['username'],
            $userData['email'],
            $passwordHash,
            $userData['first_name'],
            $userData['last_name'],
            $userData['phone'] ?? null,
            $userData['country'],
            $userData['date_of_birth'] ?? null,
            $emailToken
        ]);
        
        if ($result) {
            $userId = $this->db->lastInsertId();
            
            // Create default wallets for the user
            $this->createDefaultWallets($userId);
            
            // Send verification email
            $this->email->sendVerificationEmail($userData['email'], $userData['first_name'], $emailToken);
            
            // Log registration
            $this->security->logSecurityEvent('user_registered', ['user_id' => $userId, 'email' => $userData['email']]);
            
            return ['success' => true, 'user_id' => $userId];
        }
        
        return ['success' => false, 'errors' => ['Registration failed. Please try again.']];
    }
    
    /**
     * Login user
     */
    public function login($email, $password, $twoFactorToken = null) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Check rate limiting
        if (!$this->security->checkLoginRateLimit($ip, $email)) {
            return ['success' => false, 'errors' => ['Too many login attempts. Please try again later.']];
        }
        
        // Get user from database
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND account_status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !$this->security->verifyPassword($password, $user['password_hash'])) {
            $this->security->recordLoginAttempt($ip, $email, false);
            return ['success' => false, 'errors' => ['Invalid email or password']];
        }
        
        // Check if email is verified
        if (!$user['email_verified']) {
            return ['success' => false, 'errors' => ['Please verify your email before logging in']];
        }
        
        // Check 2FA if enabled
        if ($user['two_factor_enabled']) {
            if (!$twoFactorToken) {
                return ['success' => false, 'requires_2fa' => true];
            }
            
            if (!$this->security->verify2FAToken($user['two_factor_secret'], $twoFactorToken)) {
                $this->security->recordLoginAttempt($ip, $email, false);
                return ['success' => false, 'errors' => ['Invalid 2FA token']];
            }
        }
        
        // Create session
        $this->security->createUserSession($user['id'], 'user');
        
        // Update last login
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Clear failed login attempts
        $this->security->recordLoginAttempt($ip, $email, true);
        
        // Log successful login
        $this->security->logSecurityEvent('user_login', ['user_id' => $user['id']]);
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * Admin login
     */
    public function adminLogin($username, $password, $twoFactorToken = null) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Check rate limiting
        if (!$this->security->checkLoginRateLimit($ip, $username)) {
            return ['success' => false, 'errors' => ['Too many login attempts. Please try again later.']];
        }
        
        // Get admin from database
        $stmt = $this->db->prepare("SELECT * FROM admin_users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();
        
        if (!$admin || !$this->security->verifyPassword($password, $admin['password_hash'])) {
            $this->security->recordLoginAttempt($ip, $username, false);
            return ['success' => false, 'errors' => ['Invalid username or password']];
        }
        
        // Check 2FA if enabled
        if ($admin['two_factor_enabled']) {
            if (!$twoFactorToken) {
                return ['success' => false, 'requires_2fa' => true];
            }
            
            if (!$this->security->verify2FAToken($admin['two_factor_secret'], $twoFactorToken)) {
                $this->security->recordLoginAttempt($ip, $username, false);
                return ['success' => false, 'errors' => ['Invalid 2FA token']];
            }
        }
        
        // Create session
        $this->security->createUserSession($admin['id'], 'admin');
        
        // Update last login
        $stmt = $this->db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$admin['id']]);
        
        // Clear failed login attempts
        $this->security->recordLoginAttempt($ip, $username, true);
        
        // Log successful login
        $this->security->logSecurityEvent('admin_login', ['admin_id' => $admin['id']]);
        
        return ['success' => true, 'admin' => $admin];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        $userId = $this->security->getUserId();
        
        if ($userId) {
            $this->security->logSecurityEvent('user_logout');
        }
        
        $this->security->destroyUserSession();
        
        return ['success' => true];
    }
    
    /**
     * Verify email
     */
    public function verifyEmail($token) {
        $stmt = $this->db->prepare("
            SELECT id, email, first_name 
            FROM users 
            WHERE email_verification_token = ? AND email_verified = FALSE
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'errors' => ['Invalid or expired verification token']];
        }
        
        // Mark email as verified
        $stmt = $this->db->prepare("
            UPDATE users 
            SET email_verified = TRUE, email_verification_token = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        
        // Log email verification
        $this->security->logSecurityEvent('email_verified', ['user_id' => $user['id']]);
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * Request password reset
     */
    public function requestPasswordReset($email) {
        $stmt = $this->db->prepare("SELECT id, first_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Don't reveal if email exists or not
            return ['success' => true, 'message' => 'If the email exists, a reset link has been sent'];
        }
        
        // Generate reset token
        $resetToken = $this->security->generateSecureToken();
        
        // Store reset token (expires in 1 hour)
        $stmt = $this->db->prepare("
            INSERT INTO password_resets (user_id, token, expires_at) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
            ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)
        ");
        
        // First, let's add the password_resets table to our schema
        $this->createPasswordResetTable();
        
        $stmt->execute([$user['id'], $resetToken]);
        
        // Send reset email
        $this->email->sendPasswordResetEmail($email, $user['first_name'], $resetToken);
        
        return ['success' => true, 'message' => 'If the email exists, a reset link has been sent'];
    }
    
    /**
     * Reset password
     */
    public function resetPassword($token, $newPassword, $confirmPassword) {
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'errors' => ['Passwords do not match']];
        }
        
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'errors' => ['Password must be at least 8 characters long']];
        }
        
        // Check if token is valid
        $stmt = $this->db->prepare("
            SELECT pr.user_id, u.email 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = ? AND pr.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        
        if (!$reset) {
            return ['success' => false, 'errors' => ['Invalid or expired reset token']];
        }
        
        // Update password
        $passwordHash = $this->security->hashPassword($newPassword);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $reset['user_id']]);
        
        // Delete reset token
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->execute([$reset['user_id']]);
        
        // Log password reset
        $this->security->logSecurityEvent('password_reset', ['user_id' => $reset['user_id']]);
        
        return ['success' => true];
    }
    
    /**
     * Get user profile
     */
    public function getUserProfile($userId) {
        $stmt = $this->db->prepare("
            SELECT id, username, email, first_name, last_name, phone, country, 
                   date_of_birth, profile_image, kyc_status, email_verified, 
                   two_factor_enabled, created_at, last_login 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetch();
    }
    
    /**
     * Update user profile
     */
    public function updateProfile($userId, $profileData) {
        $allowedFields = ['first_name', 'last_name', 'phone', 'country', 'date_of_birth'];
        $updateFields = [];
        $values = [];
        
        foreach ($allowedFields as $field) {
            if (isset($profileData[$field])) {
                $updateFields[] = "{$field} = ?";
                $values[] = $profileData[$field];
            }
        }
        
        if (empty($updateFields)) {
            return ['success' => false, 'errors' => ['No fields to update']];
        }
        
        $values[] = $userId;
        
        $stmt = $this->db->prepare("UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?");
        $result = $stmt->execute($values);
        
        if ($result) {
            $this->security->logSecurityEvent('profile_updated', ['user_id' => $userId]);
            return ['success' => true];
        }
        
        return ['success' => false, 'errors' => ['Failed to update profile']];
    }
    
    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword, $confirmPassword) {
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'errors' => ['Passwords do not match']];
        }
        
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'errors' => ['Password must be at least 8 characters long']];
        }
        
        // Verify current password
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || !$this->security->verifyPassword($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'errors' => ['Current password is incorrect']];
        }
        
        // Update password
        $passwordHash = $this->security->hashPassword($newPassword);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $result = $stmt->execute([$passwordHash, $userId]);
        
        if ($result) {
            $this->security->logSecurityEvent('password_changed', ['user_id' => $userId]);
            return ['success' => true];
        }
        
        return ['success' => false, 'errors' => ['Failed to change password']];
    }
    
    /**
     * Enable/disable 2FA
     */
    public function toggle2FA($userId, $enable, $token = null) {
        if ($enable) {
            // Generate new secret
            $secret = $this->security->generate2FASecret();
            
            // Verify token if provided
            if ($token && !$this->security->verify2FAToken($secret, $token)) {
                return ['success' => false, 'errors' => ['Invalid verification token']];
            }
            
            $stmt = $this->db->prepare("
                UPDATE users 
                SET two_factor_enabled = TRUE, two_factor_secret = ? 
                WHERE id = ?
            ");
            $stmt->execute([$secret, $userId]);
            
            $this->security->logSecurityEvent('2fa_enabled', ['user_id' => $userId]);
            
            return ['success' => true, 'secret' => $secret];
        } else {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET two_factor_enabled = FALSE, two_factor_secret = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            
            $this->security->logSecurityEvent('2fa_disabled', ['user_id' => $userId]);
            
            return ['success' => true];
        }
    }
    
    /**
     * Create default wallets for new user
     */
    private function createDefaultWallets($userId) {
        $defaultCurrencies = [
            ['type' => 'fiat', 'code' => 'USD'],
            ['type' => 'crypto', 'code' => 'BTC'],
            ['type' => 'crypto', 'code' => 'ETH'],
            ['type' => 'crypto', 'code' => 'USDT']
        ];
        
        $stmt = $this->db->prepare("
            INSERT INTO user_wallets (user_id, currency_type, currency_code) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($defaultCurrencies as $currency) {
            $stmt->execute([$userId, $currency['type'], $currency['code']]);
        }
    }
    
    /**
     * Create password reset table if it doesn't exist
     */
    private function createPasswordResetTable() {
        $stmt = $this->db->prepare("
            CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user (user_id),
                INDEX idx_token (token),
                INDEX idx_expires (expires_at)
            )
        ");
        $stmt->execute();
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return $this->security->isLoggedIn();
    }
    
    /**
     * Get current user ID
     */
    public function getCurrentUserId() {
        return $this->security->getUserId();
    }
    
    /**
     * Get current user type
     */
    public function getCurrentUserType() {
        return $this->security->getUserType();
    }
    
    /**
     * Require login (redirect if not logged in)
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }
    
    /**
     * Require admin login
     */
    public function requireAdmin() {
        if (!$this->isLoggedIn() || $this->getCurrentUserType() !== 'admin') {
            header('Location: /admin/login.php');
            exit;
        }
    }
}
?>