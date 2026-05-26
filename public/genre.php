<?php
/**
 * Genre SEO landing page — /genres/{slug}
 * Shows all movies of a specific genre with SEO-optimized content.
 */
require_once __DIR__ . '/../config.php';

$db = getDbConnection();

$genreSlug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'] ?? ''));
if (!$genreSlug) {
    http_response_code(404);
    exit('Genre not found');
}

$genreName = ucwords(str_replace('-', ' ', $genreSlug));

// Verify genre exists
$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM movies WHERE status = 'now_showing' AND genres LIKE ?");
$stmt->execute(["%{$genreName}%"]);
$count = (int)$stmt->fetch()['cnt'];

if ($count === 0) {
    http_response_code(404);
    exit('Genre not found');
}

// Movies in this genre
$stmt = $db->prepare("
    SELECT m.*,
        COUNT(DISTINCT s.id) AS times_today,
        COUNT(DISTINCT s.show_date) AS showtime_days,
        GROUP_CONCAT(DISTINCT ch.name SEPARATOR ', ') AS chains_available
    FROM movies m
    LEFT JOIN showtimes s ON s.movie_id = m.id AND s.show_date >= CURDATE()
    LEFT JOIN cinemas c ON s.cinema_id = c.id
    LEFT JOIN chains ch ON c.chain_id = ch.id
    WHERE m.status = 'now_showing' AND m.genres LIKE ?
    GROUP BY m.id
    ORDER BY times_today DESC, m.title ASC
");
$stmt->execute(["%{$genreName}%"]);
$movies = array_map('mapMovieRow', $stmt->fetchAll() ?? []);

// Cinemas showing this genre
$stmt = $db->prepare("
    SELECT DISTINCT c.id, c.name, c.slug, c.city, ch.name AS chain_name, ch.color_hex
    FROM showtimes s
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    JOIN chains ch ON c.chain_id = ch.id
    WHERE m.status = 'now_showing' AND m.genres LIKE ? AND s.show_date >= CURDATE()
    ORDER BY ch.name, c.name
    LIMIT 8
");
$stmt->execute(["%{$genreName}%"]);
$cinemas = $stmt->fetchAll() ?? [];

// SEO
$siteUrl = rtrim(SITE_URL, '/');
$canonical = '/genres/' . rawurlencode($genreSlug);
$pageTitle = $genreName . ' Movies Showing Now in Lebanon — ' . SITE_NAME;
$pageDescription = 'Browse ' . $genreName . ' movies playing at cinemas across Lebanon. Find showtimes, watch trailers, and book tickets for ' . $genreName . ' films at VOX, Grand, Empire and more.';
$pageImage = $movies[0]['poster_url'] ?? '';
$breadcrumbs = [
    ['pos' => 2, 'name' => 'Movies', 'url' => '/movies'],
    ['pos' => 3, 'name' => $genreName, 'url' => $canonical],
];

$jsonLd = [
    [
        '@type' => 'CollectionPage',
        '@id' => $siteUrl . $canonical . '#page',
        'name' => $pageTitle,
        'description' => $pageDescription,
        'isPartOf' => ['@id' => $siteUrl . '/#website'],
    ],
    [
        '@type' => 'FAQPage',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => 'What ' . $genreName . ' movies are playing in Lebanon?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => count($movies) . ' ' . $genreName . ' movies are currently showing in Lebanon, including ' . implode(', ', array_map(fn($m) => $m['title'], array_slice($movies, 0, 5))) . '. Find showtimes and book tickets on LebanonCinema.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Where can I watch ' . $genreName . ' movies in Lebanon?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $genreName . ' movies are playing at ' . implode(', ', array_map(fn($c) => $c['name'] . ' (' . $c['chain_name'] . ')', $cinemas)) . '. Check full showtimes on LebanonCinema.',
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
    <h1 style="font-size:2rem;margin-bottom:8px;"><?= htmlspecialchars($genreName) ?> Movies</h1>
    <p style="color:var(--text-muted);margin-bottom:4px;">
        <?= count($movies) ?> movie<?= count($movies) !== 1 ? 's' : '' ?> showing now in Lebanon
        <?php if (!empty($cinemas)): ?> · at <?= count($cinemas) ?> cinema<?= count($cinemas) !== 1 ? 's' : '' ?><?php endif; ?>
    </p>
</div>

<!-- Movie Grid -->
<?php if (!empty($movies)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Now Showing</h2>
        <?php if (count($movies) > 12): ?><a href="<?= e_link('/movies?genre=' . urlencode($genreName)) ?>" class="section-link">See all</a><?php endif; ?>
    </div>
    <div class="movie-grid stagger">
        <?php foreach ($movies as $m): ?>
        <a href="<?= e_link('/movies/' . rawurlencode($m['slug'])) ?>" class="poster-card">
            <?php if ($m['poster_url']): ?>
                <img class="poster-card-img" src="<?= htmlspecialchars($m['poster_url']) ?>" alt="<?= htmlspecialchars($m['title']) . ' - ' . htmlspecialchars($genreName) ?> movie" loading="lazy">
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

<?php renderAd('leaderboard', 'ad-mt-2 ad-mb-4'); ?>

<!-- Cinemas showing this genre -->
<?php if (!empty($cinemas)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Cinemas Showing <?= htmlspecialchars($genreName) ?> Movies</h2>
        <a href="<?= e_link('/cinemas') ?>" class="section-link">All cinemas</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;padding:0 24px;">
        <?php foreach ($cinemas as $c): ?>
        <a href="<?= e_link('/cinemas/' . rawurlencode($c['slug'])) ?>" class="cinema-card" style="width:100%;">
            <div class="cinema-card-top">
                <div class="cinema-dot" style="background:<?= htmlspecialchars($c['color_hex']) ?>"></div>
                <div class="cinema-card-name"><?= htmlspecialchars($c['name']) ?></div>
            </div>
            <div class="cinema-card-detail"><?= htmlspecialchars($c['chain_name']) ?> · <?= htmlspecialchars($c['city']) ?></div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- SEO Content Block -->
<section class="section" style="padding:0 24px;">
    <div style="background:var(--card);border-radius:var(--radius-lg);padding:24px;border:1px solid var(--border);">
        <h2 style="font-size:1.2rem;margin-bottom:16px;"><?= htmlspecialchars($genreName) ?> Movies in Lebanon</h2>
        <p style="color:var(--text-muted);line-height:1.7;margin-bottom:12px;">
            Looking for <?= strtolower($genreName) ?> movies in Lebanon? There are <?= count($movies) ?> <?= strtolower($genreName) ?> films currently showing at cinemas across Lebanon.
            Browse showtimes for top <?= strtolower($genreName) ?> movies at VOX Cinemas, Grand Cinemas, Empire, CinemaCity and more.
            Watch trailers, read synopses, and book your tickets online.
        </p>
        <p style="color:var(--text-muted);line-height:1.7;">
            Popular <?= strtolower($genreName) ?> movies now showing include <?= implode(', ', array_map(fn($m) => htmlspecialchars($m['title']), array_slice($movies, 0, 6))) ?>.
            Check each movie's page for showtimes, cinema locations, and booking links.
        </p>
    </div>
</section>

<!-- Related Genres -->
<?php
$otherGenres = ['Action', 'Comedy', 'Drama', 'Horror', 'Thriller', 'Romance', 'Animation', 'Family', 'Science Fiction', 'Adventure'];
$otherGenres = array_filter($otherGenres, fn($g) => strtolower($g) !== strtolower($genreName));
$otherGenres = array_slice($otherGenres, 0, 6);
?>
<section class="section" style="padding:0 24px;">
    <h2 class="section-title" style="margin-bottom:16px;">Browse Other Genres</h2>
    <div class="genre-strip">
        <?php foreach ($otherGenres as $g): ?>
        <a href="<?= e_link('/genres/' . strtolower(str_replace(' ', '-', $g))) ?>" class="genre-pill"><?= htmlspecialchars($g) ?></a>
        <?php endforeach; ?>
        <a href="<?= e_link('/movies') ?>" class="genre-pill">All Movies</a>
    </div>
</section>

<?php renderAd('leaderboard', 'ad-mt-4 ad-mb-2'); ?>

</div><!-- end page-enter -->
<?php include __DIR__ . '/includes/footer.php'; ?>
