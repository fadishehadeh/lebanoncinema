<?php
require_once __DIR__ . '/../config.php';

$db = getDbConnection();

$pageTitle = 'Cinemas — ' . SITE_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <h1 class="h2 mb-4">🎭 Cinemas - Click to View</h1>

    <p class="text-muted mb-4">Click any cinema below to see its showtimes:</p>

    <?php
    $stmt = $db->query("
        SELECT c.id, c.name, c.slug, c.city, c.area, ch.name AS chain_name, ch.color_hex
        FROM cinemas c
        JOIN chains ch ON c.chain_id = ch.id
        WHERE c.is_active = 1
        ORDER BY ch.name, c.name
    ");
    $cinemas = $stmt->fetchAll();

    foreach ($cinemas as $cinema) {
        $cinemaTwoLinks = "
            <strong>Direct Link:</strong><br>
            <a href=\"cinema.php?slug={$cinema['slug']}\" target=\"_blank\">
                cinema.php?slug={$cinema['slug']}
            </a><br><br>

            <strong>Clean URL (if .htaccess works):</strong><br>
            <a href=\"/cinemas/{$cinema['slug']}\" target=\"_blank\">
                /cinemas/{$cinema['slug']}
            </a>
        ";
        ?>
        <div class="card mb-3" style="border-left: 4px solid <?= $cinema['color_hex'] ?>">
            <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($cinema['name']) ?></h5>
                <p class="card-text text-muted">
                    <?= htmlspecialchars($cinema['city']) ?>
                    <?= $cinema['area'] ? ' • ' . htmlspecialchars($cinema['area']) : '' ?>
                    <br>
                    <small><?= htmlspecialchars($cinema['chain_name']) ?></small>
                </p>
                <a href="cinema.php?slug=<?= htmlspecialchars($cinema['slug']) ?>" class="btn btn-sm btn-primary">
                    View Showtimes
                </a>
                <a href="<?= e_link('/cinemas/' . rawurlencode($cinema['slug'])) ?>" class="btn btn-sm btn-outline-secondary" style="display:none;">
                    (Clean URL)
                </a>
            </div>
        </div>
        <?php
    }
    ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
