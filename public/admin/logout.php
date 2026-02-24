<?php
/**
 * Admin Logout
 */

require_once __DIR__ . '/../lib/auth.php';

Auth::logout();
header('Location: /admin/login.php');
exit;
