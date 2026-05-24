<?php
require_once __DIR__ . '/../includes/auth.php';
logout();
header('Location: ' . _link('/admin/login.php'));
exit;
