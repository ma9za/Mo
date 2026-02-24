<?php
/**
 * Helper Functions
 * Common utility functions for the application
 */

/**
 * Escape HTML output
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape HTML attributes
 */
function escapeAttr($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape JSON
 */
function escapeJson($data) {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Redirect to URL
 */
function redirect($url, $statusCode = 302) {
    header('Location: ' . $url, true, $statusCode);
    exit;
}

/**
 * Get current page URL
 */
function getCurrentUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) {
        return '-';
    }

    try {
        return date($format, strtotime($date));
    } catch (Exception $e) {
        return '-';
    }
}

/**
 * Format relative time
 */
function formatRelativeTime($date) {
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}

/**
 * Generate random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL
 */
function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Truncate string
 */
function truncate($string, $length = 100, $suffix = '...') {
    if (strlen($string) <= $length) {
        return $string;
    }

    return substr($string, 0, $length - strlen($suffix)) . $suffix;
}

/**
 * Convert array to query string
 */
function arrayToQueryString($array) {
    return http_build_query($array);
}

/**
 * Parse query string to array
 */
function queryStringToArray($queryString) {
    parse_str($queryString, $array);
    return $array;
}

/**
 * Check if request is AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get request method
 */
function getRequestMethod() {
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

/**
 * Check if request is POST
 */
function isPost() {
    return getRequestMethod() === 'POST';
}

/**
 * Get POST data
 */
function getPost($key = null, $default = null) {
    if ($key === null) {
        return $_POST;
    }

    return $_POST[$key] ?? $default;
}

/**
 * Get GET data
 */
function getGet($key = null, $default = null) {
    if ($key === null) {
        return $_GET;
    }

    return $_GET[$key] ?? $default;
}

/**
 * Get REQUEST data
 */
function getRequest($key = null, $default = null) {
    if ($key === null) {
        return $_REQUEST;
    }

    return $_REQUEST[$key] ?? $default;
}

/**
 * Set flash message
 */
function setFlash($key, $message, $type = 'info') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    $_SESSION['flash'][$key] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get flash message
 */
function getFlash($key = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($key === null) {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }

    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $flash = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $flash;
}

/**
 * Log message to file
 */
function logMessage($message, $level = 'INFO') {
    $logDir = dirname(dirname(dirname(__FILE__))) . '/private/logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}\n";

    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
