<?php
// auth_check.php — NO session_start() here, pages call it themselves

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (!isset($_SESSION['user_id'])) {
    $root = str_contains($_SERVER['PHP_SELF'], '/pages/') ? '../' : '';
    header("Location: {$root}login.php");
    exit;
}