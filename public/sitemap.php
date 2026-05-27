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
$siteUpdatedAt = $db->query("
    SELECT GREATEST(
        COALESCE((SELECT MAX(updated_at) FROM movies), '1970-01-01'),
        COALESCE((SELECT MAX(created_at) FROM showtimes), '1970-01-01')
    ) AS lastmod
")->fetchColumn() ?: $today;

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <!-- Homepage -->
    <url>
        <loc><?= url('/') ?></loc>
        <lastmod><?= date('Y-m-d', strtotime((string) $siteUpdatedAt)) ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Movies -->
    <url>
        <loc><?= url('/movies') ?></loc>
        <lastmod><?= date('Y-m-d', strtotime((string) $siteUpdatedAt)) ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    <!-- Cinemas -->
    <url>
        <loc><?= url('/cinemas') ?></loc>
        <lastmod><?= date('Y-m-d', strtotime((string) $siteUpdatedAt)) ?></lastmod>
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
        <lastmod><?= date('Y-m-d', strtotime((string) $siteUpdatedAt)) ?></lastmod>
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
$stmt = $db->prepare("
    SELECT
        m.slug,
        COALESCE(m.poster_path, m.poster_url) AS poster_url,
        GREATEST(
            COALESCE(m.updated_at, '1970-01-01'),
            COALESCE(MAX(s.created_at), '1970-01-01')
        ) AS updated_at
    FROM movies m
    LEFT JOIN showtimes s ON s.movie_id = m.id AND s.show_date >= CURDATE()
    WHERE COALESCE(m.status, '') IN ('now_showing', 'showing_now', 'coming_soon')
       OR EXISTS (
            SELECT 1
            FROM showtimes s
            WHERE s.movie_id = m.id
              AND s.show_date >= CURDATE()
       )
    GROUP BY m.id
    ORDER BY m.title ASC
");
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
$stmt = $db->prepare("
    SELECT c.slug, c.name, MAX(s.created_at) AS updated_at
    FROM cinemas c
    LEFT JOIN showtimes s ON s.cinema_id = c.id AND s.show_date >= CURDATE()
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.name ASC
");
$stmt->execute();
$cinemas = $stmt->fetchAll();

foreach ($cinemas as $c):
?>
    <url>
        <loc><?= url() ?>/cinemas/<?= rawurlencode($c['slug']) ?></loc>
        <lastmod><?= !empty($c['updated_at']) ? date('Y-m-d', strtotime($c['updated_at'])) : $today ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= url() ?>/cinemas/<?= rawurlencode($c['slug']) ?>/movies-showing-today</loc>
        <lastmod><?= !empty($c['updated_at']) ? date('Y-m-d', strtotime($c['updated_at'])) : $today ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.7</priority>
    </url>
<?php endforeach; ?>

<?php
// Genre pages
$stmt = $db->prepare("
    SELECT DISTINCT m.genres
    FROM movies m
    WHERE m.genres IS NOT NULL
      AND m.genres <> ''
      AND (
            COALESCE(m.status, '') IN ('now_showing', 'showing_now')
            OR EXISTS (
                SELECT 1
                FROM showtimes s
                WHERE s.movie_id = m.id
                  AND s.show_date >= CURDATE()
            )
      )
");
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
        <loc><?= url() ?>/genres/<?= rawurlencode(city_slug($g)) ?></loc>
        <lastmod><?= date('Y-m-d', strtotime((string) $siteUpdatedAt)) ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
<?php endforeach; ?>

<?php
// City pages
$stmt = $db->prepare("
    SELECT c.city, MAX(s.created_at) AS updated_at
    FROM cinemas c
    LEFT JOIN showtimes s ON s.cinema_id = c.id AND s.show_date >= CURDATE()
    WHERE c.is_active = 1 AND c.city IS NOT NULL
    GROUP BY c.city
    ORDER BY c.city ASC
");
$stmt->execute();
$cities = $stmt->fetchAll();
foreach ($cities as $ct):
    $citySlug = city_slug($ct['city']);
    $cityLastmod = !empty($ct['updated_at']) ? date('Y-m-d', strtotime($ct['updated_at'])) : $today;
?>
    <url>
        <loc><?= url() ?>/showtimes/<?= rawurlencode($citySlug) ?></loc>
        <lastmod><?= $cityLastmod ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= url() ?>/cities/<?= rawurlencode($citySlug) ?></loc>
        <lastmod><?= $cityLastmod ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc><?= url() ?>/<?= rawurlencode($citySlug) ?>/movies-showing-today</loc>
        <lastmod><?= $cityLastmod ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.7</priority>
    </url>
<?php endforeach; ?>

<?php foreach ($movies as $m): ?>
    <?php foreach ($cities as $ct):
        $citySlug = city_slug($ct['city']);
        $cityLastmod = !empty($ct['updated_at']) ? date('Y-m-d', strtotime($ct['updated_at'])) : $today;
    ?>
    <url>
        <loc><?= url() ?>/movies/<?= rawurlencode($m['slug']) ?>/showtimes-in-<?= rawurlencode($citySlug) ?></loc>
        <lastmod><?= $cityLastmod ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.5</priority>
    </url>
    <?php endforeach; ?>
<?php endforeach; ?>
</urlset>
