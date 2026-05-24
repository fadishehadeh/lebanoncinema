<?php
/**
 * Showtimes by city — /showtimes/{city}
 * Lists all showtimes today in a specific city.
 */
require_once __DIR__ . '/../config.php';

$db = getDbConnection();

$citySlug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'] ?? ''));
if (!$citySlug) {
    http_response_code(404);
    exit('City not found');
}

$cityName = ucwords(str_replace('-', ' ', $citySlug));
$stmt = $db->prepare("SELECT DISTINCT city FROM cinemas WHERE is_active = 1 AND LOWER(REPLACE(city, ' ', '-')) = ?");
$stmt->execute([$citySlug]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    exit('City not found');
}
$cityName = $row['city'] ?: $cityName;

// All movies showing today in this city, grouped by cinema
$stmt = $db->prepare("
    SELECT s.show_time, s.format, s.booking_url,
        m.id AS movie_id, m.title AS movie_title, m.slug AS movie_slug, COALESCE(m.poster_path, m.poster_url) AS poster_url, m.rating, m.genres, m.duration_min,
        c.id AS cinema_id, c.name AS cinema_name, c.slug AS cinema_slug,
        ch.name AS chain_name, ch.color_hex
    FROM showtimes s
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    JOIN chains ch ON c.chain_id = ch.id
    WHERE s.show_date = CURDATE() AND c.city = ? AND c.is_active = 1
    ORDER BY ch.name, c.name, s.show_time
");
$stmt->execute([$cityName]);
$rows = $stmt->fetchAll() ?? [];

// Group by cinema
$byCinema = [];
$movieIds = [];
foreach ($rows as $r) {
    $cid = $r['cinema_id'];
    if (!isset($byCinema[$cid])) {
        $byCinema[$cid] = [
            'name' => $r['cinema_name'],
            'slug' => $r['cinema_slug'],
            'chain' => $r['chain_name'],
            'color' => $r['color_hex'],
            'showtimes' => [],
        ];
    }
    $byCinema[$cid]['showtimes'][] = $r;
    $movieIds[$r['movie_id']] = true;
}

$totalMovies = count($movieIds);
$totalCinemas = count($byCinema);

// SEO
$siteUrl = rtrim(SITE_URL, '/');
$canonical = '/showtimes/' . rawurlencode($citySlug);
$pageTitle = 'Movie Showtimes in ' . htmlspecialchars($cityName) . ' Today — ' . SITE_NAME;
$pageDescription = 'Find movie showtimes in ' . htmlspecialchars($cityName) . ', Lebanon today. ' . $totalMovies . ' movies playing at ' . $totalCinemas . ' cinemas. Check times, watch trailers, and book tickets online.';
$breadcrumbs = [
    ['pos' => 2, 'name' => 'Showtimes', 'url' => '/movies'],
    ['pos' => 3, 'name' => $cityName, 'url' => $canonical],
];

$jsonLd = [[
    '@type' => 'FAQPage',
    'mainEntity' => [
        [
            '@type' => 'Question',
            'name' => 'What movies are showing in ' . $cityName . ' today?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $totalMovies . ' movies are showing today in ' . $cityName . ' at ' . $totalCinemas . ' cinemas. Browse full showtimes on LebanonCinema.'],
        ],
        [
            '@type' => 'Question',
            'name' => 'Which cinemas are in ' . $cityName . '?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Cinemas in ' . $cityName . ' include ' . implode(', ', array_map(fn($c) => $c['name'] . ' (' . $c['chain'] . ')', $byCinema)) . '. View showtimes on LebanonCinema.'],
        ],
    ],
]];

require_once __DIR__ . '/includes/ad.php';
include __DIR__ . '/includes/header.php';
?>
<div class="page-enter">

<div style="padding:24px 24px 0;">
    <h1 style="font-size:2rem;margin-bottom:4px;">Showtimes in <?= htmlspecialchars($cityName) ?></h1>
    <p style="color:var(--text-muted);margin-bottom:4px;">
        <?= $totalMovies ?> movies · <?= $totalCinemas ?> cinemas · <?= date('l, F j') ?>
    </p>
</div>

<?php if (empty($byCinema)): ?>
    <div class="empty-state"><p>No showtimes available for <?= htmlspecialchars($cityName) ?> today.</p></div>
<?php else: ?>
    <?php foreach ($byCinema as $cid => $cinema): ?>
    <section class="section">
        <div class="section-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($cinema['color']) ?>;flex-shrink:0;"></div>
                <h2 class="section-title" style="font-size:1.1rem;">
                    <a href="<?= e_link('/cinemas/' . rawurlencode($cinema['slug'])) ?>"" style="color:var(--text);">
                        <?= htmlspecialchars($cinema['name']) ?>
                    </a>
                </h2>
            </div>
            <span style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($cinema['chain']) ?></span>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;padding:0 24px;">
            <?php
            $movieGroups = [];
            foreach ($cinema['showtimes'] as $st) {
                $mid = $st['movie_id'];
                if (!isset($movieGroups[$mid])) {
                    $movieGroups[$mid] = ['movie' => $st, 'times' => []];
                }
                $movieGroups[$mid]['times'][] = $st;
            }
            ?>
            <?php foreach ($movieGroups as $mg): $m = $mg['movie']; ?>
            <div class="showtime-group fade-up">
                <div class="showtime-cinema">
                    <div style="width:32px;height:48px;border-radius:var(--radius-sm);overflow:hidden;flex-shrink:0;background:var(--card);">
                        <?php if ($m['poster_url']): ?>
                            <img src="<?= htmlspecialchars($m['poster_url']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                        <?php endif; ?>
                    </div>
                    <a href="<?= e_link('/movies/' . rawurlencode($m['movie_slug'])) ?>" class="name" style="font-size:0.9rem;">
                        <?= htmlspecialchars($m['movie_title']) ?>
                    </a>
                    <?php if ($m['rating']): ?>
                        <span class="mini-badge" style="background:var(--accent);color:#000;font-weight:700;"><?= htmlspecialchars($m['rating']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="times-grid">
                    <?php foreach ($mg['times'] as $st): ?>
                        <a href="<?= htmlspecialchars($st['booking_url'] ?? '#') ?>"
                           target="<?= $st['booking_url'] ? '_blank' : '' ?>"
                           class="time-btn <?= $st['booking_url'] ? 'bookable' : '' ?>">
                            <?= date('g:i a', strtotime($st['show_time'])) ?>
                            <?php if ($st['format'] && $st['format'] !== 'Standard'): ?>
                                <span style="font-size:0.6rem;opacity:0.7;margin-left:2px;"><?= htmlspecialchars($st['format']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php renderAd('leaderboard', 'ad-mt-2 ad-mb-4'); ?>

<!-- SEO Content Block + Internal links -->
<section class="section" style="padding:0 24px;">
    <div style="background:var(--card);border-radius:var(--radius-lg);padding:24px;border:1px solid var(--border);">
        <h2 style="font-size:1.2rem;margin-bottom:16px;">Movies in <?= htmlspecialchars($cityName) ?> Today</h2>
        <p style="color:var(--text-muted);line-height:1.7;margin-bottom:12px;">
            Looking for movie showtimes in <?= htmlspecialchars($cityName) ?>? There are <?= $totalMovies ?> movies playing today at <?= $totalCinemas ?> cinemas.
            Find the latest Hollywood blockbusters, family films, and更多 at <?= htmlspecialchars($cityName) ?>'s top cinemas including
            <?= implode(', ', array_map(fn($c) => '<a href="' . _link('/cinemas/' . rawurlencode($c['slug'])) . '" style="color:var(--accent);">' . htmlspecialchars($c['name']) . '</a>', $byCinema)) ?>.
        </p>
        <p style="color:var(--text-muted);line-height:1.7;">
            Browse all <?= $totalMovies ?> movies below, check showtimes, watch trailers, read synopses, and book your tickets online.
            Showtimes are updated daily at 6am.
        </p>
    </div>
</section>

<!-- Related cities -->
<?php
$stmt = $db->prepare("SELECT DISTINCT city FROM cinemas WHERE is_active = 1 AND city != ? ORDER BY city ASC LIMIT 5");
$stmt->execute([$cityName]);
$otherCities = $stmt->fetchAll() ?? [];
if (!empty($otherCities)):
?>
<section class="section" style="padding:0 24px;">
    <h2 class="section-title" style="margin-bottom:16px;">Showtimes in Other Cities</h2>
    <div class="genre-strip">
        <?php foreach ($otherCities as $oc): ?>
        <a href="/showtimes/<?= urlencode(strtolower(str_replace(' ', '-', $oc['city']))) ?>" class="genre-pill"><?= htmlspecialchars($oc['city']) ?></a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

</div><!-- end page-enter -->
<?php include __DIR__ . '/includes/footer.php'; ?>
