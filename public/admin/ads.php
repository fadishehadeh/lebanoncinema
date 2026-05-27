<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ad.php';

requireAuth();

$message = '';
$definitions = getAdPlacementDefinitions();
$configFile = getAdConfigFilePath();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ads'])) {
    $settings = [
        'adsense_enabled' => isset($_POST['adsense_enabled']) ? 1 : 0,
        'adsense_client' => trim((string) ($_POST['adsense_client'] ?? '')),
        'debug_labels' => isset($_POST['debug_labels']) ? 1 : 0,
        'auto_ads_enabled' => isset($_POST['auto_ads_enabled']) ? 1 : 0,
        'placements' => [],
    ];

    foreach ($definitions as $key => $definition) {
        $settings['placements'][$key] = [
            'enabled' => isset($_POST['placement_enabled'][$key]) ? 1 : 0,
            'slot' => trim((string) ($_POST['placement_slot'][$key] ?? '')),
            'desktop' => isset($_POST['placement_desktop'][$key]) ? 1 : 0,
            'mobile' => isset($_POST['placement_mobile'][$key]) ? 1 : 0,
            'sticky' => isset($_POST['placement_sticky'][$key]) ? 1 : 0,
        ];
    }

    file_put_contents($configFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $message = 'Ad settings saved';
}

$settings = getAdSettings();
$placements = [];
foreach ($definitions as $key => $definition) {
    $config = getAdConfig($definition['type']);
    $placements[] = [
        'key' => $key,
        'label' => $definition['label'],
        'type' => $definition['type'],
        'templates' => $definition['templates'],
        'dimensions' => implode(' / ', array_filter([
            !empty($config['desk']) ? ($config['desk'][0] . 'x' . $config['desk'][1]) : null,
            !empty($config['mob']) ? ($config['mob'][0] . 'x' . $config['mob'][1]) . ' mobile' : null,
        ])),
        'config' => $settings['placements'][$key] ?? ['enabled' => 1, 'slot' => '', 'desktop' => $definition['desktop'], 'mobile' => $definition['mobile'], 'sticky' => $definition['sticky']],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ad Settings - <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#0B0B0F; color:#f0f0f0; font-size:14px; }
        .wrap { max-width:1200px; margin:0 auto; padding:24px; }
        h1 { font-size:1.5rem; font-weight:700; margin-bottom:20px; color:#fff; }
        h2 { font-size:1.05rem; font-weight:600; margin:0 0 14px; color:#FF3D71; }
        .card { background:#15151B; border:1px solid rgba(255,255,255,0.06); border-radius:14px; padding:20px; margin-bottom:16px; }
        .nav { display:flex; gap:4px; margin-bottom:24px; background:#15151B; padding:8px; border-radius:12px; }
        .nav a { padding:8px 16px; border-radius:8px; color:#888; text-decoration:none; font-weight:500; }
        .nav a:hover { color:#fff; background:#1B1B22; }
        .nav a.active { background:#FF3D71; color:#000; }
        label { display:block; font-size:0.7rem; font-weight:600; color:#888; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.05em; }
        input[type=text] { width:100%; padding:10px 12px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:#1B1B22; color:#f0f0f0; font-family:inherit; font-size:0.9rem; outline:none; }
        input[type=text]:focus { border-color:#FF3D71; }
        .btn { display:inline-flex; align-items:center; padding:10px 18px; border-radius:8px; font-weight:600; font-size:0.85rem; cursor:pointer; border:none; font-family:inherit; transition:opacity .15s; background:#FF3D71; color:#000; }
        .btn:hover { opacity:.85; }
        .msg { padding:12px 16px; border-radius:8px; margin-bottom:16px; background:rgba(0,200,83,0.12); color:#00C853; border:1px solid rgba(0,200,83,0.2); font-weight:500; }
        .toggle-row { display:flex; gap:18px; flex-wrap:wrap; }
        .toggle { display:flex; align-items:center; gap:10px; }
        .toggle input { width:auto; }
        .intro { color:#9c9ca7; line-height:1.6; margin-bottom:16px; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:10px 8px; border-top:1px solid rgba(255,255,255,0.05); vertical-align:top; }
        th { color:#888; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:600; }
        td small { color:#888; display:block; margin-top:4px; }
        .placement-name { font-weight:600; color:#fff; margin-bottom:4px; }
        .placement-type { color:#8c8c96; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.08em; }
        .placement-grid { display:grid; grid-template-columns:2fr 1fr 1fr 1.3fr 1fr; gap:10px; }
        .flag-row { display:flex; gap:10px; flex-wrap:wrap; }
        .flag { display:inline-flex; align-items:center; gap:6px; font-size:0.75rem; color:#b8b8c4; }
        .sticky-note { color:#888; font-size:0.78rem; margin-top:12px; }
        .path { font-family:ui-monospace,SFMono-Regular,Consolas,monospace; color:#ffb8c6; font-size:0.8rem; }
        @media (max-width: 960px) {
            .placement-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="nav">
        <a href="<?= _link('/admin') ?>">Dashboard</a>
        <a href="<?= _link('/admin/catalog.php') ?>">Catalog Review</a>
        <a href="<?= _link('/admin/ads.php') ?>" class="active">AdSense</a>
        <a href="<?= _link('/admin/logout.php') ?>" style="color:#FF3D71;">Logout</a>
    </div>

    <h1>Google AdSense Settings</h1>
    <?php if ($message): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="card">
        <p class="intro">This screen is the live runtime source for manual ad placements. Slot IDs, placement toggles, device visibility, and sticky behavior are all read by the public renderer from <span class="path"><?= htmlspecialchars($configFile) ?></span>.</p>
    </div>

    <form method="post">
        <div class="card">
            <h2>Global Runtime</h2>
            <div class="toggle-row" style="margin-bottom:14px;">
                <label class="toggle"><input type="checkbox" name="adsense_enabled" value="1" <?= !empty($settings['adsense_enabled']) ? 'checked' : '' ?>> Manual ads enabled</label>
                <label class="toggle"><input type="checkbox" name="auto_ads_enabled" value="1" <?= !empty($settings['auto_ads_enabled']) ? 'checked' : '' ?>> Auto Ads enabled on domain</label>
                <label class="toggle"><input type="checkbox" name="debug_labels" value="1" <?= !empty($settings['debug_labels']) ? 'checked' : '' ?>> Always show debug labels</label>
            </div>
            <label>AdSense Publisher ID</label>
            <input type="text" name="adsense_client" value="<?= htmlspecialchars($settings['adsense_client'] ?? '') ?>" placeholder="ca-pub-xxxxxxxxxxxxxx">
            <p class="sticky-note">Use `?ad_debug=1` while logged in if you want temporary placement labels without permanently enabling debug labels for every admin view.</p>
        </div>

        <div class="card">
            <h2>Placement Inventory</h2>
            <table>
                <thead>
                    <tr>
                        <th>Placement</th>
                        <th>Slot ID</th>
                        <th>Visibility</th>
                        <th>Behavior</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($placements as $placement): ?>
                    <tr>
                        <td>
                            <div class="placement-name"><?= htmlspecialchars($placement['label']) ?></div>
                            <div class="placement-type"><?= htmlspecialchars($placement['key']) ?> · <?= htmlspecialchars($placement['type']) ?></div>
                            <small><?= htmlspecialchars($placement['templates']) ?></small>
                            <small><?= htmlspecialchars($placement['dimensions']) ?></small>
                        </td>
                        <td>
                            <label>Slot ID</label>
                            <input type="text" name="placement_slot[<?= htmlspecialchars($placement['key']) ?>]" value="<?= htmlspecialchars($placement['config']['slot'] ?? '') ?>" placeholder="AdSense slot ID">
                        </td>
                        <td>
                            <div class="flag-row">
                                <label class="flag"><input type="checkbox" name="placement_desktop[<?= htmlspecialchars($placement['key']) ?>]" value="1" <?= !empty($placement['config']['desktop']) ? 'checked' : '' ?>> Desktop</label>
                                <label class="flag"><input type="checkbox" name="placement_mobile[<?= htmlspecialchars($placement['key']) ?>]" value="1" <?= !empty($placement['config']['mobile']) ? 'checked' : '' ?>> Mobile</label>
                            </div>
                        </td>
                        <td>
                            <div class="flag-row">
                                <label class="flag"><input type="checkbox" name="placement_sticky[<?= htmlspecialchars($placement['key']) ?>]" value="1" <?= !empty($placement['config']['sticky']) ? 'checked' : '' ?>> Sticky</label>
                            </div>
                        </td>
                        <td>
                            <div class="flag-row">
                                <label class="flag"><input type="checkbox" name="placement_enabled[<?= htmlspecialchars($placement['key']) ?>]" value="1" <?= !empty($placement['config']['enabled']) ? 'checked' : '' ?>> Enabled</label>
                            </div>
                            <small><?= !empty($placement['config']['slot']) ? 'Slot configured' : 'No slot assigned' ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button type="submit" name="save_ads" class="btn">Save Settings</button>
    </form>
</div>
</body>
</html>
