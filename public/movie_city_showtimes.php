<?php
require_once __DIR__ . '/../config.php';

$db = getDbConnection();
$movieSlug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['movie_slug'] ?? ''));
$citySlug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['city_slug'] ?? ''));
if ($movieSlug === '' || $citySlug === '') {
    http_response_code(404);
    exit('Page not found');
}

$stmt = $db->prepare("SELECT * FROM movies WHERE slug = ? LIMIT 1");
$stmt->execute([$movieSlug]);
$movie = $stmt->fetch();
if (!$movie) {
    http_response_code(404);
    exit('Movie not found');
}
$movie = mapMovieRow($movie);

$stmt = $db->prepare("SELECT DISTINCT city FROM cinemas WHERE is_active = 1 AND LOWER(REPLACE(city, ' ', '-')) = ? LIMIT 1");
$stmt->execute([$citySlug]);
$cityName = $stmt->fetchColumn();
if (!$cityName) {
    http_response_code(404);
    exit('City not found');
}

$stmt = $db->prepare("
    SELECT
        s.show_date, s.format, s.booking_url,
        c.name AS cinema_name, c.slug AS cinema_slug,
        ch.name AS chain_name, ch.color_hex,
        GROUP_CONCAT(s.show_time ORDER BY s.show_time SEPARATOR ',') AS times
    FROM showtimes s
    JOIN cinemas c ON c.id = s.cinema_id
    JOIN chains ch ON ch.id = c.chain_id
    WHERE s.movie_id = ?
      AND c.city = ?
      AND c.is_active = 1
      AND s.show_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)
    GROUP BY s.show_date, c.id, s.format
    ORDER BY s.show_date ASC, c.name ASC, s.format ASC
");
$stmt->execute([$movie['id'], $cityName]);
$rows = $stmt->fetchAll() ?: [];

$byDate = [];
$cinemaNames = [];
foreach ($rows as $row) {
    $byDate[$row['show_date']][] = $row;
    $cinemaNames[$row['cinema_slug']] = $row['cinema_name'];
}

$updatedStmt = $db->prepare("
    SELECT MAX(s.created_at)
    FROM showtimes s
    JOIN cinemas c ON c.id = s.cinema_id
    WHERE s.movie_id = ? AND c.city = ? AND s.show_date >= CURDATE()
");
$updatedStmt->execute([$movie['id'], $cityName]);
$updatedAt = $updatedStmt->fetchColumn() ?: null;

$canonical = '/movies/' . rawurlencode($movieSlug) . '/showtimes-in-' . rawurlencode($citySlug);
$pageTitle = $movie['title'] . ' Showtimes in ' . $cityName . ' | ' . SITE_NAME;
$pageDescription = 'Find ' . $movie['title'] . ' showtimes in ' . $cityName . ', Lebanon. See which cinemas have sessions, compare dates, and open booking links from one page.';
$pageImage = $movie['poster_url'] ?? '';
$pageUpdatedAt = $updatedAt;
$breadcrumbs = [
    ['pos' => 2, 'name' => 'Movies', 'url' => '/movies'],
    ['pos' => 3, 'name' => $movie['title'], 'url' => '/movies/' . rawurlencode($movieSlug)],
    ['pos' => 4, 'name' => $cityName, 'url' => $canonical],
];

$jsonLd = [[
    '@type' => 'CollectionPage',
    '@id' => url($canonical) . '#page',
    'name' => $pageTitle,
    'description' => $pageDescription,
    'about' => [
        '@type' => 'Movie',
        'name' => $movie['title'],
        'genre' => $movie['genres_list'] ?? [],
    ],
], [
    '@type' => 'FAQPage',
    'mainEntity' => [
        [
            '@type' => 'Question',
            'name' => 'Where is ' . $movie['title'] . ' showing in ' . $cityName . '?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => !empty($cinemaNames)
                    ? $movie['title'] . ' is showing in ' . $cityName . ' at ' . human_implode(array_values($cinemaNames)) . '.'
                    : 'No published ' . $movie['title'] . ' sessions were found in ' . $cityName . ' in the next seven days.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'How often are ' . $movie['title'] . ' showtimes updated in ' . $cityName . '?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Showtimes are refreshed from cinema sources and the page displays the latest imported sessions for ' . $cityName . '.',
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
            <h1 class="section-title" style="font-size:2rem;"><?= htmlspecialchars($movie['title']) ?> Showtimes in <?= htmlspecialchars($cityName) ?></h1>
        </div>
        <div class="seo-summary" style="padding:0 24px 8px;">
            <p style="color:var(--text-muted);max-width:920px;line-height:1.7;">
                <?= htmlspecialchars($movie['title']) ?> <?= !empty($cinemaNames) ? 'is showing in ' . htmlspecialchars($cityName) . ' at ' . htmlspecialchars(human_implode(array_values($cinemaNames))) . '.' : 'does not currently have published sessions in ' . htmlspecialchars($cityName) . '.' ?>
                This page keeps the city-specific answer readable for both search users and search engines. <?= htmlspecialchars(seo_updated_label($updatedAt)) ?>.
            </p>
            <p style="color:var(--text-muted);font-size:0.9rem;margin-top:10px;">
                What you'll find on this page: all upcoming <?= htmlspecialchars($movie['title']) ?> sessions in <?= htmlspecialchars($cityName) ?>, grouped by date and cinema, plus direct links to the broader movie and city pages.
            </p>
        </div>
    </section>

    <?php if (empty($rows)): ?>
        <div class="showtimes-wrap">
            <div class="empty-state"><p>No <?= htmlspecialchars($movie['title']) ?> sessions are currently published in <?= htmlspecialchars($cityName) ?>.</p></div>
        </div>
    <?php else: ?>
        <div class="showtimes-wrap">
            <h2 class="section-title" style="margin-bottom:20px;">Upcoming Sessions</h2>
            <?php foreach ($byDate as $date => $dateRows): ?>
            <div style="margin-bottom:24px;">
                <h3 style="padding:0 24px 12px;font-size:1rem;"><?= htmlspecialchars(date('l, F j', strtotime($date))) ?></h3>
                <?php foreach ($dateRows as $row): ?>
                <div class="showtime-group fade-up">
                    <div class="showtime-cinema">
                        <span class="dot" style="background:<?= htmlspecialchars($row['color_hex']) ?>"></span>
                        <a href="<?= e_link('/cinemas/' . rawurlencode($row['cinema_slug'])) ?>" class="name"><?= htmlspecialchars($row['cinema_name']) ?></a>
                        <?php if ($row['format'] && $row['format'] !== 'Standard'): ?>
                            <span class="format"><?= htmlspecialchars($row['format']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="times-grid">
                        <?php foreach (explode(',', $row['times']) as $time): ?>
                        <a href="<?= htmlspecialchars($row['booking_url'] ?? '#') ?>" target="<?= !empty($row['booking_url']) ? '_blank' : '' ?>" class="time-btn <?= !empty($row['booking_url']) ? 'bookable' : '' ?>">
                            <?= date('g:i a', strtotime($time)) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="section" style="padding:0 24px;">
        <div style="background:var(--card);border-radius:var(--radius-lg);padding:24px;border:1px solid var(--border);">
            <h2 style="font-size:1.1rem;margin-bottom:14px;">Related Pages</h2>
            <p style="color:var(--text-muted);line-height:1.7;">
                Continue with the full <a href="<?= e_link('/movies/' . rawurlencode($movieSlug)) ?>" style="color:var(--accent);"><?= htmlspecialchars($movie['title']) ?> page</a>,
                browse <a href="<?= e_link('/showtimes/' . rawurlencode($citySlug)) ?>" style="color:var(--accent);">all showtimes in <?= htmlspecialchars($cityName) ?></a>,
                or see <a href="<?= e_link('/' . rawurlencode($citySlug) . '/movies-showing-today') ?>" style="color:var(--accent);">all movies showing in <?= htmlspecialchars($cityName) ?> today</a>.
            </p>
        </div>
    </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
