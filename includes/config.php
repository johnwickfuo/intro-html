<?php
// Prevent direct access
if (!defined('CRYPTO_TRADE_INIT')) {
    define('CRYPTO_TRADE_INIT', true);
}

// Error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Force HTTPS in production
if (!isset($_SERVER['HTTPS']) && $_SERVER['SERVER_NAME'] !== 'localhost') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit();
}

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/crypto_api.php';
require_once __DIR__ . '/trading.php';
require_once __DIR__ . '/wallet.php';
require_once __DIR__ . '/notifications.php';

// Initialize database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Initialize core classes
$security = new Security($db);
$auth = new Auth($db);
$cryptoAPI = new CryptoAPI($db);

// Check for maintenance mode
$stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'maintenance_mode'");
$stmt->execute();
$maintenanceMode = $stmt->fetch();

if ($maintenanceMode && $maintenanceMode['setting_value'] === 'true') {
    // Allow admin access during maintenance
    if (!$auth->isLoggedIn() || $auth->getCurrentUserType() !== 'admin') {
        include __DIR__ . '/../maintenance.php';
        exit();
    }
}

// Clean expired sessions and cache periodically
if (rand(1, 100) === 1) {
    $security->cleanExpiredSessions();
    $cryptoAPI->cleanExpiredCache();
}

// Site settings
$stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings");
$stmt->execute();
$settings = $stmt->fetchAll();

$siteConfig = [];
foreach ($settings as $setting) {
    $siteConfig[$setting['setting_key']] = $setting['setting_value'];
}

// Helper functions
function getSiteSetting($key, $default = null) {
    global $siteConfig;
    return $siteConfig[$key] ?? $default;
}

function formatCurrency($amount, $currency = 'USD') {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'BTC' => '₿',
        'ETH' => 'Ξ'
    ];
    
    $symbol = $symbols[$currency] ?? $currency;
    return $symbol . number_format($amount, 2);
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

function generateCSRFToken() {
    global $security;
    return $security->generateCSRFToken();
}

function verifyCSRFToken($token) {
    global $security;
    return $security->verifyCSRFToken($token);
}

function sanitizeInput($data) {
    global $security;
    return $security->sanitizeInput($data);
}

function isLoggedIn() {
    global $auth;
    return $auth->isLoggedIn();
}

function getCurrentUser() {
    global $auth, $db;
    
    if (!$auth->isLoggedIn()) {
        return null;
    }
    
    $userId = $auth->getCurrentUserId();
    return $auth->getUserProfile($userId);
}

function requireLogin() {
    global $auth;
    $auth->requireLogin();
}

function requireAdmin() {
    global $auth;
    $auth->requireAdmin();
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function showError($message) {
    return "<div class='alert alert-danger'>$message</div>";
}

function showSuccess($message) {
    return "<div class='alert alert-success'>$message</div>";
}

function showWarning($message) {
    return "<div class='alert alert-warning'>$message</div>";
}

function showInfo($message) {
    return "<div class='alert alert-info'>$message</div>";
}

// Page meta data
$pageTitle = getSiteSetting('site_name', 'CryptoTrade Pro');
$pageDescription = getSiteSetting('site_description', 'Professional Crypto Trading Platform');
$pageKeywords = 'crypto, trading, bitcoin, ethereum, investment, binary options, copy trading';

// Current user data
$currentUser = getCurrentUser();

// Common page data
$commonData = [
    'siteName' => getSiteSetting('site_name'),
    'siteDescription' => getSiteSetting('site_description'),
    'siteEmail' => getSiteSetting('site_email'),
    'currentUser' => $currentUser,
    'isLoggedIn' => isLoggedIn(),
    'csrfToken' => generateCSRFToken()
];

// JSON response helper
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// AJAX request helper
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// File upload helper
function handleFileUpload($file, $allowedTypes = [], $maxSize = 5242880, $uploadDir = 'uploads/') {
    global $security;
    
    if (!$security->validateFileUpload($file, $allowedTypes, $maxSize)) {
        return false;
    }
    
    $fileName = uniqid() . '_' . basename($file['name']);
    $uploadPath = $uploadDir . $fileName;
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return $fileName;
    }
    
    return false;
}

// Pagination helper
function paginate($currentPage, $totalPages, $baseUrl) {
    $html = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage - 1) . '">Previous</a></li>';
    }
    
    // Page numbers
    for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++) {
        $active = $i == $currentPage ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage + 1) . '">Next</a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

// Logging helper
function logActivity($action, $details = null) {
    global $db, $auth;
    
    $userId = $auth->getCurrentUserId();
    
    $stmt = $db->prepare("
        INSERT INTO audit_logs (user_id, action, new_values, ip_address, user_agent, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $userId,
        $action,
        $details ? json_encode($details) : null,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

// Cookie consent helper
function needsCookieConsent() {
    return !isset($_COOKIE['cookie_consent']);
}

function setCookieConsent() {
    setcookie('cookie_consent', '1', time() + (365 * 24 * 60 * 60), '/');
}

// Theme helper
function getTheme() {
    return $_COOKIE['theme'] ?? 'light';
}

function setTheme($theme) {
    setcookie('theme', $theme, time() + (365 * 24 * 60 * 60), '/');
}

// Rate limiting helper
function checkRateLimit($action, $limit = 10, $window = 3600) {
    global $db;
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = $action . '_' . $ip;
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM rate_limits 
        WHERE rate_key = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([$key, $window]);
    $result = $stmt->fetch();
    
    if ($result['count'] >= $limit) {
        return false;
    }
    
    // Create rate_limits table if it doesn't exist
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rate_key VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_key_time (rate_key, created_at)
        )
    ");
    $stmt->execute();
    
    // Record this request
    $stmt = $db->prepare("INSERT INTO rate_limits (rate_key, ip_address) VALUES (?, ?)");
    $stmt->execute([$key, $ip]);
    
    return true;
}

// Clean old rate limit entries
if (rand(1, 100) === 1) {
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute();
}

// Country list helper
function getCountryList() {
    return [
        'US' => 'United States',
        'CA' => 'Canada',
        'GB' => 'United Kingdom',
        'AU' => 'Australia',
        'DE' => 'Germany',
        'FR' => 'France',
        'IT' => 'Italy',
        'ES' => 'Spain',
        'NL' => 'Netherlands',
        'BE' => 'Belgium',
        'CH' => 'Switzerland',
        'AT' => 'Austria',
        'SE' => 'Sweden',
        'NO' => 'Norway',
        'DK' => 'Denmark',
        'FI' => 'Finland',
        'IE' => 'Ireland',
        'PT' => 'Portugal',
        'GR' => 'Greece',
        'PL' => 'Poland',
        'CZ' => 'Czech Republic',
        'HU' => 'Hungary',
        'RO' => 'Romania',
        'BG' => 'Bulgaria',
        'HR' => 'Croatia',
        'SI' => 'Slovenia',
        'SK' => 'Slovakia',
        'EE' => 'Estonia',
        'LV' => 'Latvia',
        'LT' => 'Lithuania',
        'JP' => 'Japan',
        'KR' => 'South Korea',
        'CN' => 'China',
        'IN' => 'India',
        'SG' => 'Singapore',
        'HK' => 'Hong Kong',
        'TW' => 'Taiwan',
        'TH' => 'Thailand',
        'MY' => 'Malaysia',
        'ID' => 'Indonesia',
        'PH' => 'Philippines',
        'VN' => 'Vietnam',
        'BR' => 'Brazil',
        'AR' => 'Argentina',
        'MX' => 'Mexico',
        'CL' => 'Chile',
        'CO' => 'Colombia',
        'PE' => 'Peru',
        'ZA' => 'South Africa',
        'NG' => 'Nigeria',
        'EG' => 'Egypt',
        'MA' => 'Morocco',
        'IL' => 'Israel',
        'AE' => 'United Arab Emirates',
        'SA' => 'Saudi Arabia',
        'TR' => 'Turkey',
        'RU' => 'Russia',
        'UA' => 'Ukraine',
        'BY' => 'Belarus',
        'KZ' => 'Kazakhstan',
        'NZ' => 'New Zealand'
    ];
}

// Timezone helper
function getUserTimezone() {
    return $_SESSION['timezone'] ?? 'UTC';
}

function convertToUserTimezone($datetime, $timezone = null) {
    if (!$timezone) {
        $timezone = getUserTimezone();
    }
    
    $date = new DateTime($datetime, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone($timezone));
    return $date->format('Y-m-d H:i:s');
}

// Language helper (for future internationalization)
function __($key, $default = null) {
    // Simple translation system - can be expanded
    $translations = [
        'welcome' => 'Welcome',
        'login' => 'Login',
        'register' => 'Register',
        'dashboard' => 'Dashboard',
        'trading' => 'Trading',
        'wallet' => 'Wallet',
        'profile' => 'Profile',
        'logout' => 'Logout',
        'error' => 'Error',
        'success' => 'Success',
        'warning' => 'Warning',
        'info' => 'Information'
    ];
    
    return $translations[$key] ?? $default ?? $key;
}

// Initialize session flash messages
if (!isset($_SESSION['flash_messages'])) {
    $_SESSION['flash_messages'] = [];
}

// Flash message helpers
function setFlashMessage($type, $message) {
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

function getFlashMessages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    $_SESSION['flash_messages'] = [];
    return $messages;
}

function hasFlashMessages() {
    return !empty($_SESSION['flash_messages']);
}

// Application is now initialized
define('APP_INITIALIZED', true);
?>