<?php
require_once __DIR__ . '/../config.php';

$db = getDbConnection();
$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    exit('Cinema not found');
}

$stmt = $db->prepare("
    SELECT c.*, ch.name AS chain_name, ch.color_hex
    FROM cinemas c
    JOIN chains ch ON ch.id = c.chain_id
    WHERE c.slug = ? AND c.is_active = 1
    LIMIT 1
");
$stmt->execute([$slug]);
$cinema = $stmt->fetch();
if (!$cinema) {
    http_response_code(404);
    exit('Cinema not found');
}

$stmt = $db->prepare("
    SELECT
        m.id, m.title, m.slug, COALESCE(m.poster_path, m.poster_url) AS poster_url, m.rating, m.genres, m.duration_min,
        MIN(s.show_time) AS first_showtime,
        COUNT(DISTINCT s.id) AS times_today
    FROM showtimes s
    JOIN movies m ON m.id = s.movie_id
    WHERE s.cinema_id = ? AND s.show_date = CURDATE()
    GROUP BY m.id
    ORDER BY first_showtime ASC, m.title ASC
");
$stmt->execute([$cinema['id']]);
$movies = array_map('mapMovieRow', $stmt->fetchAll() ?: []);

$stmt = $db->prepare("SELECT MAX(created_at) FROM showtimes WHERE cinema_id = ? AND show_date = CURDATE()");
$stmt->execute([$cinema['id']]);
$updatedAt = $stmt->fetchColumn() ?: null;

$movieTitles = array_map(static fn(array $movie) => $movie['title'], array_slice($movies, 0, 6));
$canonical = '/cinemas/' . rawurlencode($slug) . '/movies-showing-today';
$pageTitle = $cinema['name'] . ' Showtimes and Movies Today | ' . SITE_NAME;
$pageDescription = 'Browse movies showing today at ' . $cinema['name'] . ' in ' . $cinema['city'] . ', Lebanon. Check session counts, jump to the full cinema schedule, and open each movie page.';
$pageUpdatedAt = $updatedAt;
$breadcrumbs = [
    ['pos' => 2, 'name' => 'Cinemas', 'url' => '/cinemas'],
    ['pos' => 3, 'name' => $cinema['name'], 'url' => '/cinemas/' . rawurlencode($slug)],
    ['pos' => 4, 'name' => 'Movies Showing Today', 'url' => $canonical],
];

$jsonLd = [[
    '@type' => 'CollectionPage',
    '@id' => url($canonical) . '#page',
    'name' => $pageTitle,
    'description' => $pageDescription,
    'about' => [
        '@type' => 'MovieTheater',
        'name' => $cinema['name'],
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => $cinema['city'],
            'addressCountry' => 'LB',
        ],
    ],
], [
    '@type' => 'FAQPage',
    'mainEntity' => [
        [
            '@type' => 'Question',
            'name' => 'What movies are showing today at ' . $cinema['name'] . '?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => count($movies) > 0
                    ? $cinema['name'] . ' is showing ' . count($movies) . ' movies today, including ' . human_implode($movieTitles) . '.'
                    : 'There are no published movie sessions for ' . $cinema['name'] . ' today.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'Where is ' . $cinema['name'] . '?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $cinema['name'] . ' is in ' . $cinema['city'] . ($cinema['area'] ? ', ' . $cinema['area'] : '') . ', Lebanon.',
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
            <h1 class="section-title" style="font-size:2rem;"><?= htmlspecialchars($cinema['name']) ?> Movies Showing Today</h1>
        </div>
        <div class="seo-summary" style="padding:0 24px 8px;">
            <p style="color:var(--text-muted);max-width:920px;line-height:1.7;">
                <?= htmlspecialchars($cinema['name']) ?> in <?= htmlspecialchars($cinema['city']) ?> currently has <?= count($movies) ?> movie<?= count($movies) === 1 ? '' : 's' ?> with published sessions today.
                This page is the quick answer view: today's titles, session density, and direct paths to the full movie and cinema schedule. <?= htmlspecialchars(seo_updated_label($updatedAt)) ?>.
            </p>
            <p style="color:var(--text-muted);font-size:0.9rem;margin-top:10px;">
                What you'll find on this page: today's movies at <?= htmlspecialchars($cinema['name']) ?>, links to each movie's showtimes, and routes to the full cinema and city pages.
            </p>
        </div>
    </section>

    <section class="section">
        <div class="section-header">
            <h2 class="section-title">Movie Lineup</h2>
            <a href="<?= e_link('/cinemas/' . rawurlencode($slug)) ?>" class="section-link">Full cinema page</a>
        </div>
        <?php if (empty($movies)): ?>
            <div class="empty-state"><p>No movies are published for <?= htmlspecialchars($cinema['name']) ?> today.</p></div>
        <?php else: ?>
            <div class="movie-grid stagger">
                <?php foreach ($movies as $movie): ?>
                <a href="<?= e_link('/movies/' . rawurlencode($movie['slug'])) ?>" class="poster-card">
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
            <h2 style="font-size:1.1rem;margin-bottom:14px;">About This Cinema Page</h2>
            <p style="color:var(--text-muted);line-height:1.7;margin-bottom:10px;">
                This landing page exists for the direct intent query: "<?= htmlspecialchars($cinema['name']) ?> movies showing today". It focuses on current movie availability instead of the full seven-day schedule.
            </p>
            <p style="color:var(--text-muted);line-height:1.7;">
                Related pages: <a href="<?= e_link('/cinemas/' . rawurlencode($slug)) ?>" style="color:var(--accent);"><?= htmlspecialchars($cinema['name']) ?> showtimes</a>,
                <a href="<?= e_link('/showtimes/' . rawurlencode(city_slug($cinema['city']))) ?>" style="color:var(--accent);">showtimes in <?= htmlspecialchars($cinema['city']) ?></a>,
                and <a href="<?= e_link('/movies') ?>" style="color:var(--accent);">all movies in Lebanon</a>.
            </p>
        </div>
    </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
