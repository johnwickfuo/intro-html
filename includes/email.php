<?php
class Email {
    private $db;
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $fromEmail;
    private $fromName;
    
    public function __construct($database) {
        $this->db = $database;
        $this->loadSMTPSettings();
    }
    
    /**
     * Load SMTP settings from database
     */
    private function loadSMTPSettings() {
        $stmt = $this->db->prepare("
            SELECT setting_key, setting_value 
            FROM site_settings 
            WHERE setting_key IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'site_email', 'site_name')
        ");
        $stmt->execute();
        $settings = $stmt->fetchAll();
        
        foreach ($settings as $setting) {
            switch ($setting['setting_key']) {
                case 'smtp_host':
                    $this->smtpHost = $setting['setting_value'];
                    break;
                case 'smtp_port':
                    $this->smtpPort = $setting['setting_value'];
                    break;
                case 'smtp_username':
                    $this->smtpUsername = $setting['setting_value'];
                    break;
                case 'smtp_password':
                    $this->smtpPassword = $setting['setting_value'];
                    break;
                case 'site_email':
                    $this->fromEmail = $setting['setting_value'];
                    break;
                case 'site_name':
                    $this->fromName = $setting['setting_value'];
                    break;
            }
        }
        
        // Set defaults if not configured
        $this->smtpHost = $this->smtpHost ?: 'smtp.gmail.com';
        $this->smtpPort = $this->smtpPort ?: 587;
        $this->fromEmail = $this->fromEmail ?: 'noreply@cryptotrade.com';
        $this->fromName = $this->fromName ?: 'CryptoTrade Pro';
    }
    
    /**
     * Send email verification
     */
    public function sendVerificationEmail($email, $firstName, $token) {
        $subject = 'Verify Your Email Address';
        $verifyUrl = $this->getBaseUrl() . '/verify-email.php?token=' . $token;
        
        $body = $this->getEmailTemplate('email_verification', [
            'first_name' => $firstName,
            'verify_url' => $verifyUrl,
            'site_name' => $this->fromName
        ]);
        
        return $this->sendEmail($email, $subject, $body);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($email, $firstName, $token) {
        $subject = 'Reset Your Password';
        $resetUrl = $this->getBaseUrl() . '/reset-password.php?token=' . $token;
        
        $body = $this->getEmailTemplate('password_reset', [
            'first_name' => $firstName,
            'reset_url' => $resetUrl,
            'site_name' => $this->fromName
        ]);
        
        return $this->sendEmail($email, $subject, $body);
    }
    
    /**
     * Send transaction notification email
     */
    public function sendTransactionNotification($email, $firstName, $transactionData) {
        $subject = 'Transaction Notification - ' . ucfirst($transactionData['type']);
        
        $body = $this->getEmailTemplate('transaction_notification', [
            'first_name' => $firstName,
            'transaction_type' => ucfirst($transactionData['type']),
            'amount' => $transactionData['amount'],
            'currency' => $transactionData['currency'],
            'status' => ucfirst($transactionData['status']),
            'date' => $transactionData['date'],
            'site_name' => $this->fromName
        ]);
        
        return $this->sendEmail($email, $subject, $body);
    }
    
    /**
     * Send KYC status update email
     */
    public function sendKYCStatusEmail($email, $firstName, $status) {
        $subject = 'KYC Verification Status Update';
        
        $body = $this->getEmailTemplate('kyc_status', [
            'first_name' => $firstName,
            'status' => ucfirst($status),
            'site_name' => $this->fromName
        ]);
        
        return $this->sendEmail($email, $subject, $body);
    }
    
    /**
     * Send welcome email
     */
    public function sendWelcomeEmail($email, $firstName) {
        $subject = 'Welcome to ' . $this->fromName;
        
        $body = $this->getEmailTemplate('welcome', [
            'first_name' => $firstName,
            'site_name' => $this->fromName,
            'dashboard_url' => $this->getBaseUrl() . '/dashboard.php'
        ]);
        
        return $this->sendEmail($email, $subject, $body);
    }
    
    /**
     * Send email using queue system
     */
    public function sendEmail($to, $subject, $body) {
        // Add to email queue
        $stmt = $this->db->prepare("
            INSERT INTO email_queue (to_email, subject, body, status) 
            VALUES (?, ?, ?, 'pending')
        ");
        
        $result = $stmt->execute([$to, $subject, $body]);
        
        if ($result) {
            // Try to send immediately
            $this->processPendingEmails(1);
            return true;
        }
        
        return false;
    }
    
    /**
     * Process pending emails in queue
     */
    public function processPendingEmails($limit = 10) {
        $stmt = $this->db->prepare("
            SELECT id, to_email, subject, body, attempts 
            FROM email_queue 
            WHERE status = 'pending' AND attempts < max_attempts 
            ORDER BY created_at ASC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $emails = $stmt->fetchAll();
        
        foreach ($emails as $email) {
            $sent = $this->sendEmailDirectly($email['to_email'], $email['subject'], $email['body']);
            
            if ($sent) {
                $this->updateEmailStatus($email['id'], 'sent');
            } else {
                $this->updateEmailAttempts($email['id'], $email['attempts'] + 1);
            }
        }
    }
    
    /**
     * Send email directly using SMTP
     */
    private function sendEmailDirectly($to, $subject, $body) {
        if (!$this->smtpUsername || !$this->smtpPassword) {
            error_log("SMTP credentials not configured");
            return false;
        }
        
        $headers = [
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . $this->fromEmail,
            'Content-Type: text/html; charset=UTF-8',
            'MIME-Version: 1.0'
        ];
        
        // Use PHP's mail function with proper headers
        $success = mail($to, $subject, $body, implode("\r\n", $headers));
        
        if (!$success) {
            error_log("Failed to send email to: $to");
        }
        
        return $success;
    }
    
    /**
     * Update email status in queue
     */
    private function updateEmailStatus($emailId, $status) {
        $stmt = $this->db->prepare("
            UPDATE email_queue 
            SET status = ?, sent_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$status, $emailId]);
    }
    
    /**
     * Update email attempts
     */
    private function updateEmailAttempts($emailId, $attempts) {
        $stmt = $this->db->prepare("
            UPDATE email_queue 
            SET attempts = ?, status = CASE WHEN attempts >= max_attempts THEN 'failed' ELSE 'pending' END 
            WHERE id = ?
        ");
        $stmt->execute([$attempts, $emailId]);
    }
    
    /**
     * Get email template
     */
    private function getEmailTemplate($templateName, $variables = []) {
        $templates = [
            'email_verification' => '
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
                        .container { max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .logo { font-size: 24px; font-weight: bold; color: #2c3e50; }
                        .content { line-height: 1.6; color: #333; }
                        .button { display: inline-block; padding: 12px 30px; background-color: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <div class="logo">{{site_name}}</div>
                        </div>
                        <div class="content">
                            <h2>Email Verification Required</h2>
                            <p>Hello {{first_name}},</p>
                            <p>Thank you for signing up! Please click the button below to verify your email address:</p>
                            <p><a href="{{verify_url}}" class="button">Verify Email Address</a></p>
                            <p>If the button doesn\'t work, copy and paste this link into your browser:</p>
                            <p><a href="{{verify_url}}">{{verify_url}}</a></p>
                            <p>This link will expire in 24 hours for security reasons.</p>
                        </div>
                        <div class="footer">
                            <p>If you didn\'t create an account, please ignore this email.</p>
                            <p>&copy; {{site_name}}. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>',
                
            'password_reset' => '
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
                        .container { max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .logo { font-size: 24px; font-weight: bold; color: #2c3e50; }
                        .content { line-height: 1.6; color: #333; }
                        .button { display: inline-block; padding: 12px 30px; background-color: #e74c3c; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <div class="logo">{{site_name}}</div>
                        </div>
                        <div class="content">
                            <h2>Password Reset Request</h2>
                            <p>Hello {{first_name}},</p>
                            <p>We received a request to reset your password. Click the button below to reset it:</p>
                            <p><a href="{{reset_url}}" class="button">Reset Password</a></p>
                            <p>If the button doesn\'t work, copy and paste this link into your browser:</p>
                            <p><a href="{{reset_url}}">{{reset_url}}</a></p>
                            <p>This link will expire in 1 hour for security reasons.</p>
                        </div>
                        <div class="footer">
                            <p>If you didn\'t request a password reset, please ignore this email.</p>
                            <p>&copy; {{site_name}}. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>',
                
            'transaction_notification' => '
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
                        .container { max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .logo { font-size: 24px; font-weight: bold; color: #2c3e50; }
                        .content { line-height: 1.6; color: #333; }
                        .transaction-details { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
                        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <div class="logo">{{site_name}}</div>
                        </div>
                        <div class="content">
                            <h2>Transaction Notification</h2>
                            <p>Hello {{first_name}},</p>
                            <p>This is to notify you about a recent transaction on your account:</p>
                            <div class="transaction-details">
                                <p><strong>Transaction Type:</strong> {{transaction_type}}</p>
                                <p><strong>Amount:</strong> {{amount}} {{currency}}</p>
                                <p><strong>Status:</strong> {{status}}</p>
                                <p><strong>Date:</strong> {{date}}</p>
                            </div>
                            <p>If you have any questions about this transaction, please contact our support team.</p>
                        </div>
                        <div class="footer">
                            <p>&copy; {{site_name}}. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>',
                
            'kyc_status' => '
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
                        .container { max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .logo { font-size: 24px; font-weight: bold; color: #2c3e50; }
                        .content { line-height: 1.6; color: #333; }
                        .status-approved { color: #27ae60; font-weight: bold; }
                        .status-rejected { color: #e74c3c; font-weight: bold; }
                        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <div class="logo">{{site_name}}</div>
                        </div>
                        <div class="content">
                            <h2>KYC Verification Status Update</h2>
                            <p>Hello {{first_name}},</p>
                            <p>Your KYC verification status has been updated to: <span class="status-{{status}}">{{status}}</span></p>
                            <p>Please log in to your account to view more details and take any necessary actions.</p>
                        </div>
                        <div class="footer">
                            <p>&copy; {{site_name}}. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>',
                
            'welcome' => '
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
                        .container { max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .logo { font-size: 24px; font-weight: bold; color: #2c3e50; }
                        .content { line-height: 1.6; color: #333; }
                        .button { display: inline-block; padding: 12px 30px; background-color: #27ae60; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <div class="logo">{{site_name}}</div>
                        </div>
                        <div class="content">
                            <h2>Welcome to {{site_name}}!</h2>
                            <p>Hello {{first_name}},</p>
                            <p>Welcome to the future of crypto trading! Your account has been successfully verified and you can now access all features.</p>
                            <p><a href="{{dashboard_url}}" class="button">Go to Dashboard</a></p>
                            <p>Here are some things you can do:</p>
                            <ul>
                                <li>Make your first crypto investment</li>
                                <li>Try binary options trading</li>
                                <li>Follow top traders with copy trading</li>
                                <li>Complete your KYC verification for higher limits</li>
                            </ul>
                        </div>
                        <div class="footer">
                            <p>&copy; {{site_name}}. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>'
        ];
        
        $template = $templates[$templateName] ?? '';
        
        // Replace variables in template
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Get base URL
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
    
    /**
     * Clean old emails from queue
     */
    public function cleanOldEmails() {
        $stmt = $this->db->prepare("
            DELETE FROM email_queue 
            WHERE status = 'sent' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute();
        
        $stmt = $this->db->prepare("
            DELETE FROM email_queue 
            WHERE status = 'failed' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
    }
}
?>