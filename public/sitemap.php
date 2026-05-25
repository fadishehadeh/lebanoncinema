<?php
/**
 * Dynamic XML Sitemap
 * Generates a comprehensive sitemap of all indexable pages.
 * Serves as sitemap.xml via .htaccess rewrite.
 */
header('Content-Type: application/xml; charset=utf-8');

require_once __DIR__ . '/../config.php';

$db = getDbConnection();
$today = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <!-- Homepage -->
    <url>
        <loc><?= url('/') ?></loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Movies -->
    <url>
        <loc><?= url('/movies') ?></loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    <!-- Cinemas -->
    <url>
        <loc><?= url('/cinemas') ?></loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>

    <!-- Trending -->
    <url>
        <loc><?= url('/trending') ?></loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.7</priority>
    </url>

    <!-- Coming Soon -->
    <url>
        <loc><?= url('/coming-soon') ?></loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>

    <!-- Search -->
    <url>
        <loc><?= url('/search') ?></loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

<?php
// Movies
$stmt = $db->prepare("SELECT slug, poster_url, updated_at FROM movies WHERE status IN ('now_showing','coming_soon') ORDER BY title ASC");
$stmt->execute();
$movies = $stmt->fetchAll();

foreach ($movies as $m):
    $lastmod = $m['updated_at'] ? date('Y-m-d', strtotime($m['updated_at'])) : $today;
?>
    <url>
        <loc><?= url() ?>/movies/<?= rawurlencode($m['slug']) ?></loc>
        <lastmod><?= $lastmod ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
        <?php if ($m['poster_url']): ?>
        <image:image>
            <image:loc><?= htmlspecialchars($m['poster_url']) ?></image:loc>
        </image:image>
        <?php endif; ?>
    </url>
<?php endforeach; ?>

<?php
// Cinemas
$stmt = $db->prepare("SELECT slug, name FROM cinemas WHERE is_active = 1 ORDER BY name ASC");
$stmt->execute();
$cinemas = $stmt->fetchAll();

foreach ($cinemas as $c):
?>
    <url>
        <loc><?= url() ?>/cinemas/<?= rawurlencode($c['slug']) ?></loc>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
<?php endforeach; ?>

<?php
// Genre pages
$stmt = $db->prepare("SELECT DISTINCT genres FROM movies WHERE status = 'now_showing' AND genres IS NOT NULL");
$stmt->execute();
$allGenres = [];
foreach ($stmt->fetchAll() as $row) {
    $parts = array_map('trim', explode(',', $row['genres']));
    $allGenres = array_merge($allGenres, $parts);
}
$allGenres = array_unique($allGenres);
sort($allGenres);
foreach ($allGenres as $g):
?>
    <url>
        <loc><?= url() ?>/movies?genre=<?= urlencode($g) ?></loc>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
<?php endforeach; ?>

<?php
// City pages (showtimes + city landing)
$stmt = $db->prepare("SELECT DISTINCT city FROM cinemas WHERE is_active = 1 AND city IS NOT NULL ORDER BY city ASC");
$stmt->execute();
$cities = $stmt->fetchAll();
foreach ($cities as $ct):
    $citySlug = strtolower(str_replace(' ', '-', $ct['city']));
?>
    <url>
        <loc><?= url() ?>/showtimes/<?= rawurlencode($citySlug) ?></loc>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= url() ?>/cities/<?= rawurlencode($citySlug) ?></loc>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
<?php endforeach; ?>
</urlset>
