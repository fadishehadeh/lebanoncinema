<?php
/**
 * AdSense management panel.
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$db = getDbConnection();
$message = '';

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ads'])) {
    $settings = [
        'adsense_enabled' => isset($_POST['adsense_enabled']) ? 1 : 0,
        'adsense_client'  => $_POST['adsense_client'] ?? '',
    ];
    foreach (['leaderboard','mobile-leaderboard','rectangle','large-rectangle','skyscraper','in-card'] as $type) {
        $settings["slot_$type"] = $_POST["slot_$type"] ?? '';
    }
    // Store in a simple JSON file (DB-less approach for ad config)
    file_put_contents(__DIR__ . '/ads_config.json', json_encode($settings, JSON_PRETTY_PRINT));
    $message = 'Ad settings saved';
}

// Load settings
$settings = [];
$configFile = __DIR__ . '/ads_config.json';
if (file_exists($configFile)) {
    $settings = json_decode(file_get_contents($configFile), true) ?: [];
}

$enabled   = $settings['adsense_enabled'] ?? 1;
$client    = $settings['adsense_client'] ?? (defined('ADSENSE_CLIENT') ? ADSENSE_CLIENT : '');
$placements = ['leaderboard','mobile-leaderboard','rectangle','large-rectangle','skyscraper','in-card'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ad Settings — <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#0B0B0F; color:#f0f0f0; font-size:14px; }
        .wrap { max-width:800px; margin:0 auto; padding:24px; }
        h1 { font-size:1.4rem; font-weight:700; margin-bottom:20px; color:#fff; }
        h2 { font-size:1.1rem; font-weight:600; margin:24px 0 12px; color:#FF3D71; }
        .card { background:#15151B; border:1px solid rgba(255,255,255,0.06); border-radius:12px; padding:20px; margin-bottom:16px; }
        .nav { display:flex; gap:4px; margin-bottom:24px; background:#15151B; padding:8px; border-radius:12px; }
        .nav a { padding:8px 16px; border-radius:8px; color:#888; text-decoration:none; font-weight:500; }
        .nav a:hover { color:#fff; background:#1B1B22; }
        .nav a.active { background:#FF3D71; color:#000; }
        label { display:block; font-size:0.7rem; font-weight:600; color:#888; margin-bottom:4px; text-transform:uppercase; }
        input { width:100%; padding:10px 14px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:#1B1B22; color:#f0f0f0; font-family:inherit; font-size:0.9rem; margin-bottom:12px; outline:none; }
        input:focus { border-color:#FF3D71; }
        .btn { display:inline-flex; align-items:center; padding:10px 20px; border-radius:8px; font-weight:600; font-size:0.85rem; cursor:pointer; border:none; font-family:inherit; transition:opacity .15s; background:#FF3D71; color:#000; }
        .btn:hover { opacity:.85; }
        .msg { padding:12px 16px; border-radius:8px; margin-bottom:16px; background:rgba(0,200,83,0.12); color:#00C853; border:1px solid rgba(0,200,83,0.2); font-weight:500; }
        .toggle { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
        .toggle input[type=checkbox] { width:auto; margin:0; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .slot-name { font-size:.8rem; color:var(--text-muted); margin-bottom:2px; }
        .preview-box { margin-top:16px; padding:12px; border-radius:8px; background:#1B1B22; font-size:.75rem; color:#888; font-family:monospace; }
        .flex { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        @media (max-width:768px) { .grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="nav">
        <a href="<?= _link('/admin') ?>">Dashboard</a>
        <a href="<?= _link('/admin/ads.php') ?>" class="active">AdSense</a>
        <a href="<?= _link('/admin/logout.php') ?>" style="color:#FF3D71;">Logout</a>
    </div>

    <h1>Google AdSense Settings</h1>
    <?php if ($message): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <form method="post">
        <div class="card">
            <h2>Global Settings</h2>
            <div class="toggle">
                <input type="checkbox" name="adsense_enabled" value="1" id="ae" <?= $enabled ? 'checked' : '' ?>>
                <label for="ae" style="margin:0;cursor:pointer;">AdSense Enabled</label>
            </div>
            <label>AdSense Publisher ID (ca-pub-...)</label>
            <input name="adsense_client" value="<?= htmlspecialchars($client) ?>" placeholder="ca-pub-xxxxxxxxxxxxxx">
        </div>

        <div class="card">
            <h2>Ad Slot IDs</h2>
            <p style="font-size:.8rem;color:#888;margin-bottom:12px;">Enter the ad slot IDs from your AdSense account for each placement.</p>
            <div class="grid">
                <?php foreach ($placements as $p): ?>
                <div>
                    <div class="slot-name"><?= str_replace('-', ' ', ucfirst($p)) ?></div>
                    <input name="slot_<?= $p ?>" value="<?= htmlspecialchars($settings["slot_$p"] ?? '') ?>" placeholder="Slot ID">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" name="save_ads" class="btn">Save Settings</button>
    </form>

    <div class="card">
        <h2>Ad Placement Preview</h2>
        <p style="font-size:.8rem;color:#888;margin-bottom:12px;">This shows where each ad type appears on the site.</p>
        <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
            <tr><th style="text-align:left;padding:6px;color:#888;">Page</th><th style="text-align:left;padding:6px;color:#888;">Placement</th><th style="text-align:left;padding:6px;color:#888;">Type</th></tr>
            <tr><td style="padding:6px;border-top:1px solid rgba(255,255,255,0.04);">Homepage</td><td>Below hero / above content</td><td>Leaderboard</td></tr>
            <tr><td style="padding:6px;border-top:1px solid rgba(255,255,255,0.04);">Homepage</td><td>Between Trending &amp; Cinemas</td><td>Rectangle</td></tr>
            <tr><td style="padding:6px;border-top:1px solid rgba(255,255,255,0.04);">Homepage</td><td>In carousel after 8th card</td><td>In-card</td></tr>
            <tr><td style="padding:6px;border-top:1px solid rgba(255,255,255,0.04);">Homepage</td><td>Above footer</td><td>Leaderboard</td></tr>
            <tr><td style="padding:6px;border-top:1px solid rgba(255,255,255,0.04);">Movie Page</td><td>Below showtimes</td><td>Rectangle</td></tr>
            <tr><td style="padding:6px;border-top:1px solid rgba(255,255,255,0.04);">Cinema Page</td><td>Between listings &amp; nearby</td><td>Rectangle</td></tr>
            <tr><td style="padding:6px;border-top:1px solid rgba(255,255,255,0.04);">Movies List</td><td>Inline every 8 cards</td><td>Rectangle</td></tr>
            <tr><td style="padding:6px;border-top:1px solid rgba(255,255,255,0.04);">Cinemas List</td><td>After every 2 groups</td><td>Rectangle</td></tr>
            <tr><td style="padding:6px;border-top:1px solid rgba(255,255,255,0.04);">Search</td><td>Between results groups</td><td>Rectangle</td></tr>
        </table>
    </div>

    <div class="preview-box">
        AdSense script is loaded in the &lt;head&gt; via header.php.<br>
        Ad container script: <code>&lt;script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-5198102919338219" crossorigin="anonymous"&gt;&lt;/script&gt;</code>
    </div>
</div>
</body>
</html>
