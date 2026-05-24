<?php
require_once __DIR__ . '/../includes/auth.php';
logout();
header('Location: ' . (defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/admin/login.php' : '/admin/login.php'));
exit;
