<?php
require_once __DIR__ . '/../config.php';

$db = getDbConnection();

$citySlug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'] ?? ''));
if ($citySlug === '') {
    http_response_code(404);
    exit('City not found');
}

$stmt = $db->prepare("SELECT DISTINCT city FROM cinemas WHERE is_active = 1 AND LOWER(REPLACE(city, ' ', '-')) = ? LIMIT 1");
$stmt->execute([$citySlug]);
$cityName = $stmt->fetchColumn();
if (!$cityName) {
    http_response_code(404);
    exit('City not found');
}

$stmt = $db->prepare("
    SELECT
        m.id, m.title, m.slug, COALESCE(m.poster_path, m.poster_url) AS poster_url, m.rating, m.genres, m.duration_min,
        MIN(s.show_time) AS first_showtime,
        COUNT(DISTINCT s.id) AS times_today,
        COUNT(DISTINCT c.id) AS cinema_count,
        GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS cinema_names
    FROM showtimes s
    JOIN movies m ON m.id = s.movie_id
    JOIN cinemas c ON c.id = s.cinema_id
    WHERE s.show_date = CURDATE()
      AND c.is_active = 1
      AND c.city = ?
    GROUP BY m.id
    ORDER BY times_today DESC, first_showtime ASC, m.title ASC
");
$stmt->execute([$cityName]);
$movies = array_map('mapMovieRow', $stmt->fetchAll() ?: []);

$stmt = $db->prepare("
    SELECT c.id, c.name, c.slug, ch.name AS chain_name, ch.color_hex, COUNT(DISTINCT s.movie_id) AS movies_today
    FROM cinemas c
    JOIN chains ch ON ch.id = c.chain_id
    LEFT JOIN showtimes s ON s.cinema_id = c.id AND s.show_date = CURDATE()
    WHERE c.is_active = 1 AND c.city = ?
    GROUP BY c.id
    ORDER BY movies_today DESC, c.name ASC
");
$stmt->execute([$cityName]);
$cinemas = $stmt->fetchAll() ?: [];

$updatedAt = null;
$updatedStmt = $db->prepare("
    SELECT MAX(s.created_at) AS updated_at
    FROM showtimes s
    JOIN cinemas c ON c.id = s.cinema_id
    WHERE s.show_date = CURDATE() AND c.city = ?
");
$updatedStmt->execute([$cityName]);
$updatedAt = $updatedStmt->fetchColumn() ?: null;

$movieTitles = array_map(static fn(array $movie) => $movie['title'], array_slice($movies, 0, 5));
$cinemaNames = array_map(static fn(array $cinema) => $cinema['name'], $cinemas);
$answerSummary = count($movies) > 0
    ? human_implode($movieTitles)
    : 'no published sessions yet';

$canonical = '/' . rawurlencode($citySlug) . '/movies-showing-today';
$pageTitle = 'Movies Showing in ' . $cityName . ' Today | ' . SITE_NAME;
$pageDescription = 'See movies showing today in ' . $cityName . ', Lebanon. Compare cinema showtimes across ' . count($cinemas) . ' cinemas and open trailers, ratings, and booking links from one page.';
$pageUpdatedAt = $updatedAt;
$breadcrumbs = [
    ['pos' => 2, 'name' => 'Cities', 'url' => '/cinemas'],
    ['pos' => 3, 'name' => $cityName, 'url' => '/cities/' . rawurlencode($citySlug)],
    ['pos' => 4, 'name' => 'Movies Showing Today', 'url' => $canonical],
];

$jsonLd = [[
    '@type' => 'CollectionPage',
    '@id' => url($canonical) . '#page',
    'name' => $pageTitle,
    'description' => $pageDescription,
    'isPartOf' => ['@id' => rtrim(SITE_URL, '/') . '/#website'],
    'about' => [
        '@type' => 'City',
        'name' => $cityName,
        'containedInPlace' => ['@type' => 'Country', 'name' => 'Lebanon'],
    ],
], [
    '@type' => 'FAQPage',
    'mainEntity' => [
        [
            '@type' => 'Question',
            'name' => 'What movies are showing in ' . $cityName . ' today?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => count($movies) > 0
                    ? count($movies) . ' movies are showing in ' . $cityName . ' today, including ' . $answerSummary . '.'
                    : 'There are no published showtimes in ' . $cityName . ' yet today.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'Which cinemas in ' . $cityName . ' have showtimes today?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => count($cinemas) > 0
                    ? 'Today\'s showtimes in ' . $cityName . ' are listed for ' . human_implode($cinemaNames) . '.'
                    : 'No active cinemas with published sessions were found in ' . $cityName . ' today.',
            ],
        ],
    ],
]];

require_once __DIR__ . '/includes/ad.php';
include __DIR__ . '/includes/header.php';
?>
<div class="page-enter">
    <section class="section" style="padding-top:24px;">
        <div class="section-header" style="margin-bottom:12px;">
            <h1 class="section-title" style="font-size:2rem;">Movies Showing in <?= htmlspecialchars($cityName) ?> Today</h1>
        </div>
        <div class="seo-summary" style="padding:0 24px 8px;">
            <p style="color:var(--text-muted);max-width:920px;line-height:1.7;">
                <?= count($movies) > 0
                    ? htmlspecialchars($cityName) . ' has ' . count($movies) . ' movies playing today across ' . count($cinemas) . ' cinemas. '
                    : 'No movie sessions are currently published for ' . htmlspecialchars($cityName) . ' today. ' ?>
                Compare times, open cinema pages, and jump into movie details from one page. <?= htmlspecialchars(seo_updated_label($updatedAt)) ?>.
            </p>
            <p style="color:var(--text-muted);font-size:0.9rem;margin-top:10px;">
                What you'll find on this page: today's movies in <?= htmlspecialchars($cityName) ?>, the cinemas showing them, and direct links to full movie and cinema schedules.
            </p>
        </div>
    </section>

    <?php renderAd('leaderboard', ['placement' => 'city_movies_today_top']); ?>

    <?php if (!empty($cinemas)): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">Cinemas With Sessions Today</h2>
            <a href="<?= e_link('/showtimes/' . rawurlencode($citySlug)) ?>" class="section-link">Full city showtimes</a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;padding:0 24px;">
            <?php foreach ($cinemas as $cinema): ?>
            <a href="<?= e_link('/cinemas/' . rawurlencode($cinema['slug']) . '/movies-showing-today') ?>" class="cinema-card" style="width:100%;">
                <div class="cinema-card-top">
                    <div class="cinema-dot" style="background:<?= htmlspecialchars($cinema['color_hex']) ?>"></div>
                    <div class="cinema-card-name"><?= htmlspecialchars($cinema['name']) ?></div>
                    <div style="font-size:0.8rem;font-weight:700;color:var(--accent);"><?= (int) $cinema['movies_today'] ?></div>
                </div>
                <div class="cinema-card-detail"><?= htmlspecialchars($cinema['chain_name']) ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="section">
        <div class="section-header">
            <h2 class="section-title">Today's Movies</h2>
            <a href="<?= e_link('/cities/' . rawurlencode($citySlug)) ?>" class="section-link">City guide</a>
        </div>
        <?php if (empty($movies)): ?>
            <div class="empty-state"><p>No movies are published for <?= htmlspecialchars($cityName) ?> today.</p></div>
        <?php else: ?>
            <div class="movie-grid stagger">
                <?php foreach ($movies as $movie): ?>
                <a href="<?= e_link('/movies/' . rawurlencode($movie['slug']) . '/showtimes-in-' . rawurlencode($citySlug)) ?>" class="poster-card">
                    <?php if (!empty($movie['poster_url'])): ?>
                        <img class="poster-card-img" src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="<?= htmlspecialchars($movie['title']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="poster-card-img" style="background:var(--card);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:2.5rem;"><?= htmlspecialchars(substr($movie['title'], 0, 1)) ?></div>
                    <?php endif; ?>
                    <div class="default-overlay">
                        <div class="poster-card-title"><?= htmlspecialchars($movie['title']) ?></div>
                        <div class="poster-card-meta">
                            <span><?= (int) $movie['times_today'] ?> showtimes today</span>
                            <?php if (!empty($movie['genres'])): ?><span> · <?= htmlspecialchars(substr($movie['genres'], 0, 24)) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($movie['rating'])): ?>
                        <span class="poster-card-badge accent"><?= htmlspecialchars($movie['rating']) ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="section" style="padding:0 24px;">
        <div style="background:var(--card);border-radius:var(--radius-lg);padding:24px;border:1px solid var(--border);">
            <h2 style="font-size:1.1rem;margin-bottom:14px;">Quick Facts for <?= htmlspecialchars($cityName) ?></h2>
            <p style="color:var(--text-muted);line-height:1.7;margin-bottom:10px;">
                <?= htmlspecialchars($cityName) ?> movie traffic is best captured by pages that answer the simple query directly: what is showing today, where it is playing, and how many sessions are available. This page keeps that answer crawlable in plain HTML.
            </p>
            <p style="color:var(--text-muted);line-height:1.7;">
                Related pages: <a href="<?= e_link('/showtimes/' . rawurlencode($citySlug)) ?>" style="color:var(--accent);">showtimes in <?= htmlspecialchars($cityName) ?></a>,
                <a href="<?= e_link('/cities/' . rawurlencode($citySlug)) ?>" style="color:var(--accent);">cinemas in <?= htmlspecialchars($cityName) ?></a>,
                and <a href="<?= e_link('/movies') ?>" style="color:var(--accent);">all movies in Lebanon</a>.
            </p>
        </div>
    </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
