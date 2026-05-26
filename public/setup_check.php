<?php
require_once __DIR__ . '/../config.php';

$pageTitle = 'Setup Check — ' . SITE_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <h1 class="mb-4">🎬 Lebanon Cinema - Setup Check</h1>

            <?php
            $checks = [
                'Database Connected' => function() {
                    try {
                        $db = getDbConnection();
                        $stmt = $db->query("SELECT COUNT(*) FROM movies");
                        $count = $stmt->fetchColumn();
                        return ["✓", "Connected ($count movies)"];
                    } catch (Exception $e) {
                        return ["✗", "Error: " . $e->getMessage()];
                    }
                },
                'TMDB API Configured' => function() {
                    return [
                        defined('TMDB_API_KEY') && !empty(TMDB_API_KEY) ? "✓" : "⚠",
                        defined('TMDB_API_KEY') && !empty(TMDB_API_KEY) ? "Ready" : "Not configured"
                    ];
                },
                'Vhost Configuration' => function() {
                    $hostname = gethostname();
                    $serverName = $_SERVER['SERVER_NAME'] ?? 'unknown';

                    if (strpos($serverName, 'local') !== false || strpos($serverName, 'localhost') !== false) {
                        return ["✓", "Working ($serverName)"];
                    } else {
                        return ["⚠", "Using: $serverName"];
                    }
                },
                'Hosts File Entry' => function() {
                    $hosts = file_get_contents("C:\\Windows\\System32\\drivers\\etc\\hosts");
                    if (strpos($hosts, 'lebanoncinema.local') !== false) {
                        return ["✓", "Entry found"];
                    } else {
                        return ["✗", "Need to add: 127.0.0.1 lebanoncinema.local"];
                    }
                },
                'Clean URL Routing' => function() {
                    if (file_exists('.htaccess')) {
                        return ["✓", ".htaccess exists"];
                    } else {
                        return ["✗", ".htaccess missing"];
                    }
                },
            ];

            foreach ($checks as $name => $check) {
                [$status, $message] = $check();
                $badgeColor = $status === "✓" ? "success" : ($status === "⚠" ? "warning" : "danger");
                ?>
                <div class="card mb-3">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1"><?= $name ?></h6>
                            <p class="card-text text-muted mb-0"><?= $message ?></p>
                        </div>
                        <span class="badge bg-<?= $badgeColor ?> p-2"><?= $status ?></span>
                    </div>
                </div>
                <?php
            }
            ?>

            <div class="alert alert-info mt-4">
                <h6 class="alert-heading">⚠️ Next Step Required:</h6>
                <p class="mb-2">Add this line to your hosts file:</p>
                <code>127.0.0.1 lebanoncinema.local</code>
                <hr>
                <p class="mb-0 small">
                    <strong>File location:</strong> <code>C:\Windows\System32\drivers\etc\hosts</code><br>
                    <strong>Open as:</strong> Administrator (Notepad)<br>
                    <strong>Then refresh:</strong> This page
                </p>
            </div>

            <div class="mt-4">
                <h5>🚀 When ready, visit:</h5>
                <a href="<?= e_link('/') ?>" class="btn btn-primary btn-lg">Go to Homepage</a>
                <a href="<?= e_link('/movies') ?>" class="btn btn-outline-primary btn-lg">Browse Movies</a>
                <a href="<?= e_link('/cinemas') ?>" class="btn btn-outline-primary btn-lg">Browse Cinemas</a>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
