<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if (attemptLogin($user, $pass)) {
        $redirect = '/admin';
        if (defined('SITE_URL')) $redirect = rtrim(SITE_URL, '/') . $redirect;
        header('Location: ' . $redirect);
        exit;
    }
    $error = 'Invalid credentials';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login — <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#0B0B0F; color:#f0f0f0; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .login-box { background:#15151B; border:1px solid rgba(255,255,255,0.06); border-radius:16px; padding:40px; width:360px; max-width:90vw; }
        h1 { font-size:1.3rem; font-weight:700; margin-bottom:24px; background:linear-gradient(135deg,#FF3D71,#5E8BFF); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        label { display:block; font-size:0.7rem; font-weight:600; color:#888; margin-bottom:4px; text-transform:uppercase; }
        input { width:100%; padding:10px 14px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:#1B1B22; color:#f0f0f0; font-family:inherit; font-size:0.9rem; margin-bottom:16px; outline:none; }
        input:focus { border-color:#FF3D71; }
        .btn { width:100%; padding:12px; border-radius:8px; background:#FF3D71; color:#000; font-weight:700; font-size:0.9rem; border:none; cursor:pointer; transition:opacity 0.15s; }
        .btn:hover { opacity:0.85; }
        .error { color:#FF3D71; font-size:0.8rem; margin-bottom:16px; text-align:center; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1><?= SITE_NAME ?> Admin</h1>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <label>Username</label>
            <input name="username" autocomplete="username" required autofocus>
            <label>Password</label>
            <input type="password" name="password" autocomplete="current-password" required>
            <button type="submit" class="btn">Sign In</button>
        </form>
    </div>
</body>
</html>
