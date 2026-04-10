<?php
/**
 * Logout - CoinUp Dashboard
 */

require_once dirname(__DIR__) . '/config/auth.php';

$auth = Auth::getInstance();
$auth->logout();

header('Location: /main/public/login.php');
exit;
