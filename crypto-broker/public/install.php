<?php
/**
 * Crypto Broker - Automated Installation Script
 * 
 * This script helps install the Crypto Broker application on shared hosting
 * Compatible with cPanel and most shared hosting environments
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security check - disable after installation
if (file_exists('../.installed')) {
    die('Application is already installed. Delete .installed file to reinstall.');
}

class CryptoBrokerInstaller
{
    private $steps = [
        'welcome' => 'Welcome',
        'requirements' => 'System Requirements',
        'database' => 'Database Configuration',
        'admin' => 'Admin Account',
        'settings' => 'Application Settings',
        'installation' => 'Installation',
        'complete' => 'Complete'
    ];
    
    private $currentStep;
    private $errors = [];
    private $success = [];
    
    public function __construct()
    {
        $this->currentStep = $_GET['step'] ?? 'welcome';
    }
    
    public function run()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processStep();
        }
        
        $this->renderPage();
    }
    
    private function processStep()
    {
        switch ($this->currentStep) {
            case 'requirements':
                $this->processRequirements();
                break;
            case 'database':
                $this->processDatabase();
                break;
            case 'admin':
                $this->processAdmin();
                break;
            case 'settings':
                $this->processSettings();
                break;
            case 'installation':
                $this->processInstallation();
                break;
        }
    }
    
    private function processRequirements()
    {
        $this->redirect('database');
    }
    
    private function processDatabase()
    {
        $host = trim($_POST['db_host'] ?? '');
        $database = trim($_POST['db_database'] ?? '');
        $username = trim($_POST['db_username'] ?? '');
        $password = $_POST['db_password'] ?? '';
        
        if (empty($host) || empty($database) || empty($username)) {
            $this->errors[] = 'All database fields are required.';
            return;
        }
        
        // Test database connection
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $_SESSION['db_config'] = [
                'host' => $host,
                'database' => $database,
                'username' => $username,
                'password' => $password
            ];
            
            $this->success[] = 'Database connection successful!';
            $this->redirect('admin');
            
        } catch (PDOException $e) {
            $this->errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    }
    
    private function processAdmin()
    {
        $firstName = trim($_POST['admin_first_name'] ?? '');
        $lastName = trim($_POST['admin_last_name'] ?? '');
        $email = trim($_POST['admin_email'] ?? '');
        $password = $_POST['admin_password'] ?? '';
        $confirmPassword = $_POST['admin_password_confirmation'] ?? '';
        
        if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
            $this->errors[] = 'All admin fields are required.';
            return;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = 'Please enter a valid email address.';
            return;
        }
        
        if (strlen($password) < 8) {
            $this->errors[] = 'Password must be at least 8 characters long.';
            return;
        }
        
        if ($password !== $confirmPassword) {
            $this->errors[] = 'Passwords do not match.';
            return;
        }
        
        $_SESSION['admin_config'] = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => $password
        ];
        
        $this->success[] = 'Admin account configured!';
        $this->redirect('settings');
    }
    
    private function processSettings()
    {
        $appName = trim($_POST['app_name'] ?? '');
        $appUrl = trim($_POST['app_url'] ?? '');
        $mailHost = trim($_POST['mail_host'] ?? '');
        $mailUsername = trim($_POST['mail_username'] ?? '');
        $mailPassword = $_POST['mail_password'] ?? '';
        
        if (empty($appName) || empty($appUrl)) {
            $this->errors[] = 'App name and URL are required.';
            return;
        }
        
        $_SESSION['app_config'] = [
            'name' => $appName,
            'url' => $appUrl,
            'mail_host' => $mailHost,
            'mail_username' => $mailUsername,
            'mail_password' => $mailPassword
        ];
        
        $this->success[] = 'Application settings configured!';
        $this->redirect('installation');
    }
    
    private function processInstallation()
    {
        try {
            // Create .env file
            $this->createEnvironmentFile();
            
            // Create database tables
            $this->createDatabaseTables();
            
            // Create admin user
            $this->createAdminUser();
            
            // Set permissions
            $this->setPermissions();
            
            // Create installation marker
            file_put_contents('../.installed', date('Y-m-d H:i:s'));
            
            $this->success[] = 'Installation completed successfully!';
            $this->redirect('complete');
            
        } catch (Exception $e) {
            $this->errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
    
    private function createEnvironmentFile()
    {
        $dbConfig = $_SESSION['db_config'];
        $appConfig = $_SESSION['app_config'];
        
        $envContent = "APP_NAME=\"{$appConfig['name']}\"
APP_ENV=production
APP_KEY=" . base64_encode(random_bytes(32)) . "
APP_DEBUG=false
APP_URL={$appConfig['url']}

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST={$dbConfig['host']}
DB_PORT=3306
DB_DATABASE={$dbConfig['database']}
DB_USERNAME={$dbConfig['username']}
DB_PASSWORD={$dbConfig['password']}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST={$appConfig['mail_host']}
MAIL_PORT=587
MAIL_USERNAME={$appConfig['mail_username']}
MAIL_PASSWORD={$appConfig['mail_password']}
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=\"noreply@{$this->getDomainFromUrl($appConfig['url'])}\"
MAIL_FROM_NAME=\"\${APP_NAME}\"

JWT_SECRET=" . bin2hex(random_bytes(32)) . "
JWT_TTL=60
JWT_REFRESH_TTL=20160

ENCRYPTION_KEY=" . bin2hex(random_bytes(32)) . "
GOOGLE_2FA_ENCRYPTION_KEY=" . bin2hex(random_bytes(32)) . "

TRADING_FEE_PERCENTAGE=0.1
BINARY_TRADING_MIN_AMOUNT=10
BINARY_TRADING_MAX_AMOUNT=10000
FUTURES_MAX_LEVERAGE=100
INVESTMENT_MIN_AMOUNT=100
COPY_TRADING_FEE=10

RATE_LIMIT_PER_MINUTE=60
MAX_LOGIN_ATTEMPTS=5

KYC_REQUIRED=true
KYC_AUTO_APPROVE=false

DEMO_MODE_ENABLED=true
DEMO_INITIAL_BALANCE=10000

SEED_PHRASE_BONUS_AMOUNT=50

ADMIN_EMAIL={$_SESSION['admin_config']['email']}
ADMIN_2FA_REQUIRED=false

MAINTENANCE_MODE=false
MAINTENANCE_MESSAGE=\"We are currently performing scheduled maintenance.\"
";
        
        if (!file_put_contents('../.env', $envContent)) {
            throw new Exception('Could not create .env file. Check permissions.');
        }
    }
    
    private function createDatabaseTables()
    {
        $dbConfig = $_SESSION['db_config'];
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']}", 
            $dbConfig['username'], 
            $dbConfig['password']
        );
        
        // Read and execute SQL file
        $sqlFile = '../database/crypto_broker.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
        } else {
            // Create basic tables if SQL file doesn't exist
            $this->createBasicTables($pdo);
        }
    }
    
    private function createBasicTables($pdo)
    {
        $tables = [
            "CREATE TABLE IF NOT EXISTS users (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                first_name varchar(255) NOT NULL,
                last_name varchar(255) NOT NULL,
                email varchar(255) NOT NULL UNIQUE,
                phone varchar(20) DEFAULT NULL,
                email_verified_at timestamp NULL DEFAULT NULL,
                password varchar(255) NOT NULL,
                country varchar(100) DEFAULT NULL,
                date_of_birth date DEFAULT NULL,
                gender enum('male','female','other') DEFAULT NULL,
                kyc_status enum('pending','approved','rejected','not_submitted') DEFAULT 'not_submitted',
                status enum('active','suspended','banned') DEFAULT 'active',
                referral_code varchar(8) NOT NULL UNIQUE,
                referred_by bigint unsigned DEFAULT NULL,
                created_at timestamp NULL DEFAULT NULL,
                updated_at timestamp NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY users_referred_by_foreign (referred_by),
                CONSTRAINT users_referred_by_foreign FOREIGN KEY (referred_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            "CREATE TABLE IF NOT EXISTS cryptocurrencies (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                symbol varchar(10) NOT NULL UNIQUE,
                name varchar(255) NOT NULL,
                logo_url varchar(255) DEFAULT NULL,
                current_price decimal(20,8) DEFAULT '0.00000000',
                is_active tinyint(1) DEFAULT '1',
                spot_trading_enabled tinyint(1) DEFAULT '1',
                futures_trading_enabled tinyint(1) DEFAULT '1',
                binary_trading_enabled tinyint(1) DEFAULT '1',
                deposits_enabled tinyint(1) DEFAULT '1',
                withdrawals_enabled tinyint(1) DEFAULT '1',
                min_trade_amount decimal(15,8) DEFAULT '0.00001000',
                max_trade_amount decimal(15,8) DEFAULT '1000000.00000000',
                withdrawal_fee decimal(15,8) DEFAULT '0.00000000',
                created_at timestamp NULL DEFAULT NULL,
                updated_at timestamp NULL DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];
        
        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }
    }
    
    private function createAdminUser()
    {
        $dbConfig = $_SESSION['db_config'];
        $adminConfig = $_SESSION['admin_config'];
        
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']}", 
            $dbConfig['username'], 
            $dbConfig['password']
        );
        
        $hashedPassword = password_hash($adminConfig['password'], PASSWORD_BCRYPT);
        $referralCode = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, country, date_of_birth, email_verified_at, kyc_status, status, referral_code, created_at, updated_at) VALUES (?, ?, ?, ?, 'United States', '1990-01-01', NOW(), 'approved', 'active', ?, NOW(), NOW())");
        
        $stmt->execute([
            $adminConfig['first_name'],
            $adminConfig['last_name'],
            $adminConfig['email'],
            $hashedPassword,
            $referralCode
        ]);
    }
    
    private function setPermissions()
    {
        $directories = [
            '../storage',
            '../storage/app',
            '../storage/framework',
            '../storage/logs',
            '../bootstrap/cache'
        ];
        
        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                @chmod($dir, 0755);
            }
        }
        
        @chmod('../.env', 0644);
    }
    
    private function getDomainFromUrl($url)
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? 'localhost';
    }
    
    private function redirect($step)
    {
        if (empty($this->errors)) {
            header("Location: install.php?step=$step");
            exit;
        }
    }
    
    private function checkRequirements()
    {
        $requirements = [
            'PHP Version' => [
                'required' => '8.1.0',
                'current' => PHP_VERSION,
                'passed' => version_compare(PHP_VERSION, '8.1.0', '>=')
            ],
            'PDO Extension' => [
                'required' => 'Required',
                'current' => extension_loaded('pdo') ? 'Installed' : 'Missing',
                'passed' => extension_loaded('pdo')
            ],
            'PDO MySQL' => [
                'required' => 'Required',
                'current' => extension_loaded('pdo_mysql') ? 'Installed' : 'Missing',
                'passed' => extension_loaded('pdo_mysql')
            ],
            'cURL Extension' => [
                'required' => 'Required',
                'current' => extension_loaded('curl') ? 'Installed' : 'Missing',
                'passed' => extension_loaded('curl')
            ],
            'OpenSSL Extension' => [
                'required' => 'Required',
                'current' => extension_loaded('openssl') ? 'Installed' : 'Missing',
                'passed' => extension_loaded('openssl')
            ],
            'JSON Extension' => [
                'required' => 'Required',
                'current' => extension_loaded('json') ? 'Installed' : 'Missing',
                'passed' => extension_loaded('json')
            ],
            'BCMath Extension' => [
                'required' => 'Required',
                'current' => extension_loaded('bcmath') ? 'Installed' : 'Missing',
                'passed' => extension_loaded('bcmath')
            ],
            'Storage Writable' => [
                'required' => 'Writable',
                'current' => is_writable('../storage') ? 'Writable' : 'Not Writable',
                'passed' => is_writable('../storage')
            ],
            'Root Directory Writable' => [
                'required' => 'Writable',
                'current' => is_writable('../') ? 'Writable' : 'Not Writable',
                'passed' => is_writable('../')
            ]
        ];
        
        return $requirements;
    }
    
    private function renderPage()
    {
        $stepIndex = array_search($this->currentStep, array_keys($this->steps));
        $progress = (($stepIndex + 1) / count($this->steps)) * 100;
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Crypto Broker Installation</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
                .container { max-width: 800px; margin: 0 auto; padding: 20px; }
                .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
                .header { background: #2563eb; color: white; padding: 30px; text-align: center; }
                .header h1 { font-size: 2rem; margin-bottom: 10px; }
                .progress-bar { width: 100%; height: 4px; background: rgba(255,255,255,0.3); margin-top: 20px; }
                .progress-fill { height: 100%; background: #10b981; transition: width 0.3s; }
                .content { padding: 30px; }
                .step-nav { display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 14px; }
                .step { padding: 8px 12px; border-radius: 4px; background: #e5e7eb; color: #6b7280; }
                .step.active { background: #2563eb; color: white; }
                .step.completed { background: #10b981; color: white; }
                .form-group { margin-bottom: 20px; }
                .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #374151; }
                .form-control { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 16px; }
                .form-control:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
                .btn { padding: 12px 24px; border: none; border-radius: 4px; font-size: 16px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; }
                .btn-primary { background: #2563eb; color: white; }
                .btn-primary:hover { background: #1d4ed8; }
                .btn-secondary { background: #6b7280; color: white; }
                .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
                .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
                .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
                .requirements-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .requirements-table th, .requirements-table td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
                .requirements-table th { background: #f9fafb; font-weight: 600; }
                .status-pass { color: #10b981; font-weight: 600; }
                .status-fail { color: #ef4444; font-weight: 600; }
                .text-center { text-align: center; }
                .mt-4 { margin-top: 1.5rem; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="card">
                    <div class="header">
                        <h1>Crypto Broker Installation</h1>
                        <p>Setting up your cryptocurrency trading platform</p>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="content">
                        <div class="step-nav">
                            <?php foreach ($this->steps as $key => $title): ?>
                                <?php
                                $class = 'step';
                                if ($key === $this->currentStep) $class .= ' active';
                                elseif (array_search($key, array_keys($this->steps)) < $stepIndex) $class .= ' completed';
                                ?>
                                <div class="<?= $class ?>"><?= $title ?></div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (!empty($this->errors)): ?>
                            <div class="alert alert-error">
                                <?php foreach ($this->errors as $error): ?>
                                    <div><?= htmlspecialchars($error) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($this->success)): ?>
                            <div class="alert alert-success">
                                <?php foreach ($this->success as $message): ?>
                                    <div><?= htmlspecialchars($message) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php $this->renderStep(); ?>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
    
    private function renderStep()
    {
        switch ($this->currentStep) {
            case 'welcome':
                $this->renderWelcome();
                break;
            case 'requirements':
                $this->renderRequirements();
                break;
            case 'database':
                $this->renderDatabase();
                break;
            case 'admin':
                $this->renderAdmin();
                break;
            case 'settings':
                $this->renderSettings();
                break;
            case 'installation':
                $this->renderInstallation();
                break;
            case 'complete':
                $this->renderComplete();
                break;
        }
    }
    
    private function renderWelcome()
    {
        ?>
        <h2>Welcome to Crypto Broker</h2>
        <p>This installation wizard will help you set up your cryptocurrency trading platform. Make sure you have the following information ready:</p>
        <ul style="margin: 20px 0; padding-left: 20px;">
            <li>Database connection details (host, database name, username, password)</li>
            <li>Admin account information</li>
            <li>Email configuration (SMTP settings)</li>
            <li>Domain/URL where the application will be hosted</li>
        </ul>
        <p><strong>Note:</strong> This installer is designed for shared hosting with cPanel. Make sure your hosting meets the minimum requirements.</p>
        <div class="text-center mt-4">
            <a href="install.php?step=requirements" class="btn btn-primary">Start Installation</a>
        </div>
        <?php
    }
    
    private function renderRequirements()
    {
        $requirements = $this->checkRequirements();
        $allPassed = array_reduce($requirements, function($carry, $req) { return $carry && $req['passed']; }, true);
        ?>
        <h2>System Requirements</h2>
        <p>Please ensure your server meets the following requirements:</p>
        
        <table class="requirements-table">
            <thead>
                <tr>
                    <th>Requirement</th>
                    <th>Required</th>
                    <th>Current</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requirements as $name => $req): ?>
                <tr>
                    <td><?= $name ?></td>
                    <td><?= $req['required'] ?></td>
                    <td><?= $req['current'] ?></td>
                    <td class="<?= $req['passed'] ? 'status-pass' : 'status-fail' ?>">
                        <?= $req['passed'] ? '✓ Pass' : '✗ Fail' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (!$allPassed): ?>
            <div class="alert alert-error">
                Some requirements are not met. Please contact your hosting provider to install missing extensions or fix permission issues.
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="text-center">
                <?php if ($allPassed): ?>
                    <button type="submit" class="btn btn-primary">Continue</button>
                <?php else: ?>
                    <a href="install.php?step=requirements" class="btn btn-secondary">Recheck Requirements</a>
                <?php endif; ?>
            </div>
        </form>
        <?php
    }
    
    private function renderDatabase()
    {
        ?>
        <h2>Database Configuration</h2>
        <p>Enter your database connection details. You can find these in your cPanel or hosting control panel.</p>
        
        <form method="POST">
            <div class="form-group">
                <label for="db_host">Database Host</label>
                <input type="text" id="db_host" name="db_host" class="form-control" 
                       value="<?= $_POST['db_host'] ?? 'localhost' ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_database">Database Name</label>
                <input type="text" id="db_database" name="db_database" class="form-control" 
                       value="<?= $_POST['db_database'] ?? '' ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_username">Database Username</label>
                <input type="text" id="db_username" name="db_username" class="form-control" 
                       value="<?= $_POST['db_username'] ?? '' ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_password">Database Password</label>
                <input type="password" id="db_password" name="db_password" class="form-control" required>
            </div>
            
            <div class="text-center">
                <button type="submit" class="btn btn-primary">Test Connection</button>
            </div>
        </form>
        <?php
    }
    
    private function renderAdmin()
    {
        ?>
        <h2>Admin Account</h2>
        <p>Create your administrator account. This will be used to access the admin panel.</p>
        
        <form method="POST">
            <div class="form-group">
                <label for="admin_first_name">First Name</label>
                <input type="text" id="admin_first_name" name="admin_first_name" class="form-control" 
                       value="<?= $_POST['admin_first_name'] ?? '' ?>" required>
            </div>
            
            <div class="form-group">
                <label for="admin_last_name">Last Name</label>
                <input type="text" id="admin_last_name" name="admin_last_name" class="form-control" 
                       value="<?= $_POST['admin_last_name'] ?? '' ?>" required>
            </div>
            
            <div class="form-group">
                <label for="admin_email">Email Address</label>
                <input type="email" id="admin_email" name="admin_email" class="form-control" 
                       value="<?= $_POST['admin_email'] ?? '' ?>" required>
            </div>
            
            <div class="form-group">
                <label for="admin_password">Password</label>
                <input type="password" id="admin_password" name="admin_password" class="form-control" 
                       minlength="8" required>
            </div>
            
            <div class="form-group">
                <label for="admin_password_confirmation">Confirm Password</label>
                <input type="password" id="admin_password_confirmation" name="admin_password_confirmation" 
                       class="form-control" minlength="8" required>
            </div>
            
            <div class="text-center">
                <button type="submit" class="btn btn-primary">Continue</button>
            </div>
        </form>
        <?php
    }
    
    private function renderSettings()
    {
        $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                     "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
        ?>
        <h2>Application Settings</h2>
        <p>Configure basic application settings and email configuration.</p>
        
        <form method="POST">
            <div class="form-group">
                <label for="app_name">Application Name</label>
                <input type="text" id="app_name" name="app_name" class="form-control" 
                       value="<?= $_POST['app_name'] ?? 'Crypto Broker' ?>" required>
            </div>
            
            <div class="form-group">
                <label for="app_url">Application URL</label>
                <input type="url" id="app_url" name="app_url" class="form-control" 
                       value="<?= $_POST['app_url'] ?? $currentUrl ?>" required>
            </div>
            
            <h3 style="margin: 30px 0 20px 0;">Email Configuration (Optional)</h3>
            <p>Configure SMTP settings for sending emails. You can skip this and configure later.</p>
            
            <div class="form-group">
                <label for="mail_host">SMTP Host</label>
                <input type="text" id="mail_host" name="mail_host" class="form-control" 
                       value="<?= $_POST['mail_host'] ?? '' ?>" placeholder="mail.yourdomain.com">
            </div>
            
            <div class="form-group">
                <label for="mail_username">SMTP Username</label>
                <input type="text" id="mail_username" name="mail_username" class="form-control" 
                       value="<?= $_POST['mail_username'] ?? '' ?>" placeholder="noreply@yourdomain.com">
            </div>
            
            <div class="form-group">
                <label for="mail_password">SMTP Password</label>
                <input type="password" id="mail_password" name="mail_password" class="form-control">
            </div>
            
            <div class="text-center">
                <button type="submit" class="btn btn-primary">Continue</button>
            </div>
        </form>
        <?php
    }
    
    private function renderInstallation()
    {
        ?>
        <h2>Ready to Install</h2>
        <p>All configuration is complete. Click the button below to install the application.</p>
        
        <div class="alert alert-success">
            <strong>Configuration Summary:</strong><br>
            Database: <?= $_SESSION['db_config']['database'] ?><br>
            Admin: <?= $_SESSION['admin_config']['email'] ?><br>
            App URL: <?= $_SESSION['app_config']['url'] ?>
        </div>
        
        <form method="POST">
            <div class="text-center">
                <button type="submit" class="btn btn-primary">Install Application</button>
            </div>
        </form>
        <?php
    }
    
    private function renderComplete()
    {
        $adminEmail = $_SESSION['admin_config']['email'];
        $appUrl = $_SESSION['app_config']['url'];
        
        // Clear session
        session_destroy();
        ?>
        <h2>Installation Complete!</h2>
        <div class="alert alert-success">
            <strong>Congratulations!</strong> Crypto Broker has been successfully installed.
        </div>
        
        <h3>Next Steps:</h3>
        <ol style="margin: 20px 0; padding-left: 20px;">
            <li><strong>Delete this installer:</strong> For security, delete the <code>install.php</code> file</li>
            <li><strong>Access your application:</strong> <a href="<?= $appUrl ?>" target="_blank"><?= $appUrl ?></a></li>
            <li><strong>Admin login:</strong> Use email <code><?= $adminEmail ?></code> with the password you set</li>
            <li><strong>Configure cron jobs:</strong> Set up automated tasks in cPanel</li>
            <li><strong>Review documentation:</strong> Read DOCUMENTATION.md for detailed configuration</li>
        </ol>
        
        <h3>Important Security Notes:</h3>
        <ul style="margin: 20px 0; padding-left: 20px;">
            <li>Change default admin password immediately</li>
            <li>Configure SSL/HTTPS for production</li>
            <li>Set up regular backups</li>
            <li>Review security settings in admin panel</li>
        </ul>
        
        <div class="text-center mt-4">
            <a href="<?= $appUrl ?>" class="btn btn-primary">Access Your Application</a>
        </div>
        <?php
    }
}

// Run the installer
$installer = new CryptoBrokerInstaller();
$installer->run();
?>