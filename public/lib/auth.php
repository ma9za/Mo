<?php
/**
 * Authentication Manager
 * Handles admin login, session management, and CSRF protection
 */

require_once __DIR__ . '/env.php';

class Auth {
    const SESSION_NAME = 'admin_session';
    const CSRF_TOKEN_NAME = 'csrf_token';
    const CSRF_TOKEN_LIFETIME = 3600; // 1 hour

    private static $initialized = false;

    /**
     * Initialize session
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        self::$initialized = true;
    }

    /**
     * Login user
     */
    public static function login($username, $password) {
        self::init();

        $adminUsername = env('ADMIN_USERNAME');
        $adminPasswordHash = env('ADMIN_PASSWORD_HASH');

        if ($username === $adminUsername && password_verify($password, $adminPasswordHash)) {
            $_SESSION[self::SESSION_NAME] = [
                'username' => $username,
                'login_time' => time(),
                'ip' => self::getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ];
            return true;
        }

        return false;
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        self::init();

        if (!isset($_SESSION[self::SESSION_NAME])) {
            return false;
        }

        $session = $_SESSION[self::SESSION_NAME];

        // Validate IP and User Agent
        if ($session['ip'] !== self::getClientIp() || $session['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            self::logout();
            return false;
        }

        return true;
    }

    /**
     * Logout user
     */
    public static function logout() {
        self::init();
        unset($_SESSION[self::SESSION_NAME]);
        session_destroy();
    }

    /**
     * Get current user
     */
    public static function getUser() {
        self::init();

        if (!self::isLoggedIn()) {
            return null;
        }

        return $_SESSION[self::SESSION_NAME];
    }

    /**
     * Generate CSRF token
     */
    public static function generateCsrfToken() {
        self::init();

        if (!isset($_SESSION[self::CSRF_TOKEN_NAME])) {
            $_SESSION[self::CSRF_TOKEN_NAME] = [
                'token' => bin2hex(random_bytes(32)),
                'created_at' => time()
            ];
        }

        return $_SESSION[self::CSRF_TOKEN_NAME]['token'];
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCsrfToken($token) {
        self::init();

        if (!isset($_SESSION[self::CSRF_TOKEN_NAME])) {
            return false;
        }

        $stored = $_SESSION[self::CSRF_TOKEN_NAME];

        // Check token validity
        if ($stored['token'] !== $token) {
            return false;
        }

        // Check token expiration
        if (time() - $stored['created_at'] > self::CSRF_TOKEN_LIFETIME) {
            unset($_SESSION[self::CSRF_TOKEN_NAME]);
            return false;
        }

        return true;
    }

    /**
     * Get client IP address
     */
    private static function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }

    /**
     * Require login
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: /admin/login.php');
            exit;
        }
    }

    /**
     * Hash password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }
}

// Initialize auth
Auth::init();
