<?php
/**
 * City SEO landing page — e.g. /cities/beirut, /cities/jounieh, /cities/dbayeh
 * Shows cinemas, movies today, and upcoming releases in that city.
 */
require_once __DIR__ . '/../config.php';

$db = getDbConnection();

$citySlug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'] ?? ''));
if (!$citySlug) {
    http_response_code(404);
    exit('City not found');
}

// Derive city name from slug
$cityName = ucwords(str_replace('-', ' ', $citySlug));

// Verify city exists
$stmt = $db->prepare("SELECT DISTINCT city FROM cinemas WHERE is_active = 1 AND LOWER(REPLACE(city, ' ', '-')) = ?");
$stmt->execute([$citySlug]);
$cityRow = $stmt->fetch();

if (!$cityRow) {
    http_response_code(404);
    exit('City not found');
}
$cityName = $cityRow['city'];

// Cinemas in this city
$stmt = $db->prepare("
    SELECT c.*, ch.name AS chain_name, ch.color_hex,
           COUNT(DISTINCT s.movie_id) AS movies_today
    FROM cinemas c
    JOIN chains ch ON c.chain_id = ch.id
    LEFT JOIN showtimes s ON s.cinema_id = c.id AND s.show_date = CURDATE()
    WHERE c.is_active = 1 AND c.city = ?
    GROUP BY c.id
    ORDER BY ch.name, c.name
");
$stmt->execute([$cityName]);
$cinemas = $stmt->fetchAll() ?? [];

// Movies showing today in this city
$stmt = $db->prepare("
    SELECT DISTINCT m.id, m.title, m.slug, COALESCE(m.poster_path, m.poster_url) AS poster_url, m.rating, m.genres, m.duration_min,
           MIN(s.show_time) AS first_showtime,
           COUNT(DISTINCT s.id) AS times_today,
           GROUP_CONCAT(DISTINCT ch.name) AS chains_available
    FROM movies m
    JOIN showtimes s ON s.movie_id = m.id AND s.show_date = CURDATE()
    JOIN cinemas c ON s.cinema_id = c.id
    JOIN chains ch ON c.chain_id = ch.id
    WHERE c.city = ? AND c.is_active = 1
    GROUP BY m.id
    ORDER BY times_today DESC
    LIMIT 12
");
$stmt->execute([$cityName]);
$movies = array_map('mapMovieRow', $stmt->fetchAll() ?? []);

// SEO
$siteUrl = rtrim(SITE_URL, '/');
$canonical = '/cities/' . rawurlencode($citySlug);
$pageTitle = 'Cinemas in ' . htmlspecialchars($cityName) . ' — Movie Showtimes & Tickets — ' . SITE_NAME;
$pageDescription = 'Find movie showtimes at cinemas in ' . htmlspecialchars($cityName) . ', Lebanon. Browse ' . count($movies) . ' movies playing today at ' . count($cinemas) . ' cinemas. Book tickets online.';
$pageImage = '';
$breadcrumbs = [
    ['pos' => 2, 'name' => 'Cities', 'url' => '/cinemas'],
    ['pos' => 3, 'name' => $cityName, 'url' => $canonical],
];

$cinemaList = array_map(fn($c) => $c['name'], $cinemas);
$movieList = array_map(fn($m) => $m['title'], $movies);

$jsonLd = [
    [
        '@type' => 'City',
        'name' => $cityName,
        'url' => $siteUrl . $canonical,
        'inLanguage' => 'en',
        'containedIn' => ['@type' => 'Country', 'name' => 'Lebanon'],
    ],
    [
        '@type' => 'FAQPage',
        'inLanguage' => 'en',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => 'What movies are showing in ' . $cityName . ' today?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => count($movies) . ' movies are showing today in ' . $cityName . ' including ' . implode(', ', array_slice($movieList, 0, 5)) . '. Browse full showtimes at ' . implode(', ', array_slice($cinemaList, 0, 4)) . '.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Which cinemas are in ' . $cityName . '?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $cityName . ' has ' . count($cinemas) . ' cinemas: ' . implode(', ', $cinemaList) . '. Check showtimes and book tickets online.',
                ],
            ],
        ],
    ],
];

require_once __DIR__ . '/includes/ad.php';
include __DIR__ . '/includes/header.php';
?>
<div class="page-enter">

<!-- Header -->
<div style="padding:24px 24px 0;">
    <h1 style="font-size:2rem;margin-bottom:8px;">Cinemas in <?= htmlspecialchars($cityName) ?></h1>
    <p style="color:var(--text-muted);margin-bottom:4px;">
        <?= count($cinemas) ?> cinema<?= count($cinemas) !== 1 ? 's' : '' ?> · 
        <?= count($movies) ?> movie<?= count($movies) !== 1 ? 's' : '' ?> showing today
    </p>
</div>

<!-- Cinemas -->
<?php if (!empty($cinemas)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Cinemas in <?= htmlspecialchars($cityName) ?></h2>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;padding:0 24px;">
        <?php foreach ($cinemas as $c): ?>
        <a href="<?= e_link('/cinemas/' . rawurlencode($c['slug'])) ?>" class="cinema-card" style="width:100%;">
            <div class="cinema-card-top">
                <div class="cinema-dot" style="background:<?= htmlspecialchars($c['color_hex']) ?>"></div>
                <div class="cinema-card-name"><?= htmlspecialchars($c['name']) ?></div>
                <div style="font-size:0.8rem;font-weight:700;color:var(--accent);"><?= (int)$c['movies_today'] ?></div>
            </div>
            <div class="cinema-card-detail"><?= htmlspecialchars($c['chain_name']) ?></div>
            <div class="cinema-card-footer">
                <div class="cinema-card-badges">
                    <?php if ($c['has_imax']): ?><span class="mini-badge">IMAX</span><?php endif; ?>
                    <?php if ($c['has_vip']): ?><span class="mini-badge">VIP</span><?php endif; ?>
                    <?php if ($c['has_4dx']): ?><span class="mini-badge">4DX</span><?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<?php renderAd('leaderboard', ['placement' => 'city_top']); ?>
<?php endif; ?>

<!-- Movies playing today -->
<?php if (!empty($movies)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Movies Playing Today in <?= htmlspecialchars($cityName) ?></h2>
        <a href="<?= e_link('/' . rawurlencode($citySlug) . '/movies-showing-today') ?>" class="section-link">Movies showing today</a>
    </div>
    <div class="movie-grid stagger">
        <?php foreach ($movies as $m): ?>
        <a href="<?= e_link('/movies/' . rawurlencode($m['slug']) . '/showtimes-in-' . rawurlencode($citySlug)) ?>" class="poster-card">
            <?php if ($m['poster_url']): ?>
                <img class="poster-card-img" src="<?= htmlspecialchars($m['poster_url']) ?>" alt="<?= htmlspecialchars($m['title']) ?>" loading="lazy">
            <?php else: ?>
                <div class="poster-card-img" style="background:var(--card);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:2.5rem;">
                    <?= htmlspecialchars(substr($m['title'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div class="default-overlay">
                <div class="poster-card-title"><?= htmlspecialchars($m['title']) ?></div>
                <div class="poster-card-meta"><?= htmlspecialchars(substr($m['genres'] ?? '', 0, 30)) ?></div>
            </div>
            <?php if ($m['rating']): ?>
                <span class="poster-card-badge accent"><?= htmlspecialchars($m['rating']) ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- SEO Content Block -->
<section class="section" style="padding:0 24px;">
    <div class="seo-summary" style="background:var(--card);border-radius:var(--radius-lg);padding:24px;border:1px solid var(--border);">
        <h2 style="font-size:1.2rem;margin-bottom:16px;">Movie Showtimes in <?= htmlspecialchars($cityName) ?></h2>
        <p style="color:var(--text-muted);line-height:1.7;margin-bottom:12px;">
            Looking for movie showtimes in <?= htmlspecialchars($cityName) ?>? LebanonCinema lists all <?= htmlspecialchars($cityName) ?> cinemas including 
            <?= implode(', ', array_map(fn($c) => htmlspecialchars($c['name']), $cinemas)) ?>.
            Browse current movies, check showtimes, watch trailers, and book tickets online.
        </p>
        <p style="color:var(--text-muted);line-height:1.7;">
            Whether you're looking for the latest Hollywood blockbusters, family films, or independent cinema,
            <?= htmlspecialchars($cityName) ?> has <?= count($cinemas) ?> cinema<?= count($cinemas) !== 1 ? 's' : '' ?> 
            showing <?= count($movies) ?> movie<?= count($movies) !== 1 ? 's' : '' ?> today.
            Find IMAX, VIP, and standard screenings near you.
        </p>
    </div>
</section>

<!-- FAQ -->
<section class="section" style="padding:0 24px;">
    <div style="background:var(--card);border-radius:var(--radius-lg);padding:24px;border:1px solid var(--border);">
        <h2 style="font-size:1.2rem;margin-bottom:16px;">Frequently Asked Questions</h2>
        <div style="margin-bottom:16px;">
            <h3 style="font-size:0.95rem;font-weight:600;margin-bottom:4px;">What movies are playing in <?= htmlspecialchars($cityName) ?> today?</h3>
            <p style="color:var(--text-muted);font-size:0.85rem;"><?= count($movies) ?> movie<?= count($movies) !== 1 ? 's are' : ' is' ?> playing today in <?= htmlspecialchars($cityName) ?>, including <?= implode(', ', array_map(fn($m) => htmlspecialchars($m['title']), array_slice($movies, 0, 5))) ?>. View full showtimes on LebanonCinema.</p>
        </div>
        <div>
            <h3 style="font-size:0.95rem;font-weight:600;margin-bottom:4px;">Which cinemas are in <?= htmlspecialchars($cityName) ?>?</h3>
            <p style="color:var(--text-muted);font-size:0.85rem;"><?= htmlspecialchars($cityName) ?> has <?= count($cinemas) ?> cinema<?= count($cinemas) !== 1 ? 's' : '' ?>: <?= implode(', ', array_map(fn($c) => htmlspecialchars($c['name']), $cinemas)) ?>. Check each cinema's showtimes on LebanonCinema.</p>
        </div>
    </div>
</section>

<?php renderAd('leaderboard', ['placement' => 'city_lower']); ?>

</div><!-- end page-enter -->
<?php include __DIR__ . '/includes/footer.php'; ?>
