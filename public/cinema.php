<?php
require_once __DIR__ . '/../config.php';

$db = getDbConnection();

$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'] ?? ''));
if (!$slug) {
    http_response_code(404);
    exit('Cinema not found');
}

$stmt = $db->prepare("
    SELECT c.*, ch.name AS chain_name, ch.slug AS chain_slug, ch.color_hex, ch.website_url
    FROM cinemas c
    JOIN chains ch ON c.chain_id = ch.id
    WHERE c.slug = ?
    LIMIT 1
");
$stmt->execute([$slug]);
$cinema = $stmt->fetch();

if (!$cinema) {
    http_response_code(404);
    exit('Cinema not found');
}

// Showtimes for next 7 days
$stmt = $db->prepare("
    SELECT s.show_date,
        m.id AS movie_id, m.title, m.slug AS movie_slug,
        COALESCE(m.poster_path, m.poster_url) AS poster_url, m.duration_min, m.rating, m.genres,
        GROUP_CONCAT(s.show_time ORDER BY s.show_time SEPARATOR ',') AS times,
        s.format, s.booking_url
    FROM showtimes s
    JOIN movies m ON s.movie_id = m.id
    WHERE s.cinema_id = ? AND s.show_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)
    GROUP BY s.show_date, m.id, s.format
    ORDER BY s.show_date, m.title
");
$stmt->execute([$cinema['id']]);
$showtimeRows = $stmt->fetchAll();

$byDate = [];
foreach ($showtimeRows as $row) {
    if (!isset($byDate[$row['show_date']])) {
        $byDate[$row['show_date']] = [];
    }
    $byDate[$row['show_date']][] = $row;
}

$dates = [];
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime("+$i days"));
    $dates[] = ['value' => $d, 'day' => date('D', strtotime($d)), 'num' => date('d', strtotime($d))];
}

$selDate = $_GET['date'] ?? array_key_first($byDate) ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selDate)) {
    $selDate = date('Y-m-d');
}

// NEARBY CINEMAS: same city, excluding current
$stmtNearby = $db->prepare("
    SELECT c.id, c.name, c.slug, c.city, c.area,
           ch.name AS chain_name, ch.color_hex,
           COUNT(DISTINCT s.movie_id) AS movies_today
    FROM cinemas c
    JOIN chains ch ON c.chain_id = ch.id
    LEFT JOIN showtimes s ON s.cinema_id = c.id AND s.show_date = CURDATE()
    WHERE c.is_active = 1 AND c.city = ? AND c.id != ?
    GROUP BY c.id
    ORDER BY movies_today DESC
    LIMIT 4
");
$stmtNearby->execute([$cinema['city'], $cinema['id']]);
$nearbyCinemas = $stmtNearby->fetchAll() ?? [];

// SEO metadata
$siteUrl = rtrim(SITE_URL, '/');
$canonical = '/cinemas/' . rawurlencode($slug);
$pageTitle = htmlspecialchars($cinema['name']) . ' — Movie Showtimes & Tickets — ' . SITE_NAME;
$pageDescription = 'Movie showtimes at ' . htmlspecialchars($cinema['name']) . ' in ' . htmlspecialchars($cinema['city']) . ', Lebanon. ' . ($cinema['has_imax'] ? 'IMAX screenings available. ' : '') . ($cinema['has_vip'] ? 'VIP seating. ' : '') . 'Browse today\\\'s movies, check schedules, and book cinema tickets online.';
$pageImage = '';
$breadcrumbs = [
    ['pos' => 2, 'name' => 'Cinemas', 'url' => '/cinemas'],
    ['pos' => 3, 'name' => $cinema['name'], 'url' => $canonical],
];

$jsonLd = [
    [
        '@type' => 'MovieTheater',
        '@id' => $siteUrl . '/cinemas/' . rawurlencode($slug) . '#theater',
        'name' => $cinema['name'],
        'url' => $siteUrl . '/cinemas/' . rawurlencode($slug),
        'description' => $cinema['chain_name'] . ' cinema in ' . $cinema['city'] . ($cinema['area'] ? ', ' . $cinema['area'] : '') . '. ' . ($cinema['has_imax'] ? 'Features IMAX. ' : '') . ($cinema['has_vip'] ? 'VIP seating available. ' : '') . 'View movie showtimes and book tickets.',
        'telephone' => '',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => $cinema['city'],
            'addressRegion' => $cinema['area'] ?? '',
            'addressCountry' => 'LB',
        ],
        'containedInPlace' => [
            '@type' => 'City',
            'name' => $cinema['city'],
        ],
        'areaServed' => [
            '@type' => 'City',
            'name' => $cinema['city'],
        ],
        'amenityFeature' => array_merge(
            $cinema['has_imax'] ? [['@type' => 'LocationFeatureSpecification', 'name' => 'IMAX']] : [],
            $cinema['has_vip'] ? [['@type' => 'LocationFeatureSpecification', 'name' => 'VIP']] : [],
            $cinema['has_4dx'] ? [['@type' => 'LocationFeatureSpecification', 'name' => '4DX']] : []
        ),
    ],
    [
        '@type' => 'FAQPage',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => 'What movies are playing at ' . $cinema['name'] . ' today?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Check ' . $cinema['name'] . ' showtimes on LebanonCinema for today\'s movies, schedules, and ticket booking.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Where is ' . $cinema['name'] . ' located?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $cinema['name'] . ' is located in ' . $cinema['city'] . ($cinema['area'] ? ', ' . $cinema['area'] : '') . ', Lebanon.',
                ],
            ],
        ],
    ],
    [
        '@type' => 'SpeakableSpecification',
        'cssSelector' => ['.seo-summary', '.cinema-detail-location'],
    ],
];
require_once __DIR__ . '/includes/ad.php';
include __DIR__ . '/includes/header.php';
?>
<div class="page-enter">

<!-- Cinema Detail Hero -->
<div class="cinema-detail-hero fade-up">
    <div class="cinema-detail-info">
        <div class="cinema-detail-chain"><?= htmlspecialchars($cinema['chain_name']) ?></div>
        <h1><?= htmlspecialchars($cinema['name']) ?></h1>
        <div class="cinema-detail-location">
            <?= htmlspecialchars($cinema['city']) ?>
            <?= $cinema['area'] ? ', ' . htmlspecialchars($cinema['area']) : '' ?>
            <?php if ($cinema['address']): ?>
                <br><?= htmlspecialchars($cinema['address']) ?>
            <?php endif; ?>
        </div>
        <div class="cinema-detail-badges">
            <?php if ($cinema['has_imax']): ?><span class="mini-badge" style="background:var(--card);padding:4px 12px;font-size:0.7rem;">IMAX</span><?php endif; ?>
            <?php if ($cinema['has_vip']): ?><span class="mini-badge" style="background:var(--card);padding:4px 12px;font-size:0.7rem;">VIP</span><?php endif; ?>
            <?php if ($cinema['has_4dx']): ?><span class="mini-badge" style="background:var(--card);padding:4px 12px;font-size:0.7rem;">4DX</span><?php endif; ?>
        </div>
    </div>
    <div class="cinema-detail-stats">
        <div class="num"><?= count(array_filter($byDate, fn($d) => !empty($d))) ?></div>
        <div class="label">Days with showtimes</div>
    </div>
</div>

<div class="cinema-chain-line" style="background:<?= htmlspecialchars($cinema['color_hex']) ?>;opacity:0.3;width:calc(100% - 48px);"></div>

<?php if (!empty($byDate)): ?>
<div class="showtimes-wrap">
    <h2 class="section-title" style="margin-bottom:20px;">What's Playing</h2>

    <div class="date-strip">
        <?php foreach ($dates as $d): ?>
            <a href="?slug=<?= htmlspecialchars($slug) ?>&date=<?= $d['value'] ?>"
               class="date-chip <?= $d['value'] === $selDate ? 'active' : '' ?>">
                <span class="day"><?= $d['day'] ?></span>
                <span class="num"><?= $d['num'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (isset($byDate[$selDate]) && !empty($byDate[$selDate])): ?>
        <?php foreach ($byDate[$selDate] as $movie): ?>
        <div class="showtime-group fade-up">
            <div class="showtime-cinema">
                <div style="width:36px;height:54px;border-radius:var(--radius-sm);overflow:hidden;flex-shrink:0;background:var(--card);">
                    <?php if ($movie['poster_url']): ?>
                        <img src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                    <?php endif; ?>
                </div>
                <a href="<?= e_link('/movies/' . rawurlencode($movie['movie_slug'])) ?>" class="name" style="font-size:0.95rem;">
                    <?= htmlspecialchars($movie['title']) ?>
                </a>
                <?php if ($movie['format'] && $movie['format'] !== 'Standard'): ?>
                    <span class="format"><?= htmlspecialchars($movie['format']) ?></span>
                <?php endif; ?>
                <?php if ($movie['rating']): ?>
                    <span class="mini-badge" style="background:var(--accent);color:#000;font-weight:700;"><?= htmlspecialchars($movie['rating']) ?></span>
                <?php endif; ?>
            </div>
            <div class="times-grid">
                <?php foreach (explode(',', $movie['times']) as $time): ?>
                    <a href="<?= htmlspecialchars($movie['booking_url'] ?? '#') ?>"
                       target="<?= $movie['booking_url'] ? '_blank' : '' ?>"
                       class="time-btn <?= $movie['booking_url'] ? 'bookable' : '' ?>">
                        <?= date('g:i a', strtotime($time)) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state"><p>No showtimes available for this date.</p></div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="showtimes-wrap">
    <div class="empty-state"><p>No showtimes available in the next 7 days.</p></div>
</div>
<?php endif; ?>

<!-- SEO Content + FAQ Block -->
<section class="section" style="padding:0 24px;">
    <div style="background:var(--card);border-radius:var(--radius-lg);padding:24px;border:1px solid var(--border);">
        <h2 style="font-size:1.2rem;margin-bottom:16px;">About <?= htmlspecialchars($cinema['name']) ?></h2>
        <p style="color:var(--text-muted);line-height:1.7;margin-bottom:12px;">
            <?= htmlspecialchars($cinema['chain_name']) ?>'s <?= htmlspecialchars($cinema['name']) ?> is located in <?= htmlspecialchars($cinema['city']) ?>
            <?= $cinema['area'] ? '(' . htmlspecialchars($cinema['area']) . ')' : '' ?>.
            Check showtimes for current movies, view available formats
            <?php if ($cinema['has_imax']): ?>(IMAX)<?php endif; ?>
            <?php if ($cinema['has_vip']): ?>(VIP)<?php endif; ?>
            <?php if ($cinema['has_4dx']): ?>(4DX)<?php endif; ?>.
        </p>
        <div style="margin-bottom:12px;">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:4px;">What movies are playing at <?= htmlspecialchars($cinema['name']) ?> today?</h3>
            <p style="color:var(--text-muted);font-size:0.85rem;">
                View movies and showtimes at <?= htmlspecialchars($cinema['name']) ?> above. Browse showtimes across all <?= htmlspecialchars($cinema['city']) ?> cinemas
                at <a href="<?= e_link('/showtimes/' . strtolower(str_replace(' ', '-', $cinema['city']))) ?>" style="color:var(--accent);">showtimes in <?= htmlspecialchars($cinema['city']) ?></a>.
            </p>
        </div>
        <div>
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:4px;">Is <?= htmlspecialchars($cinema['name']) ?> a <?= htmlspecialchars($cinema['chain_name']) ?> cinema?</h3>
            <p style="color:var(--text-muted);font-size:0.85rem;">
                Yes, <?= htmlspecialchars($cinema['name']) ?> is part of the <?= htmlspecialchars($cinema['chain_name']) ?> chain.
                View all <a href="<?= e_link('/cinemas') ?>" style="color:var(--accent);">cinemas in Lebanon</a> including <?= htmlspecialchars($cinema['chain_name']) ?> locations.
            </p>
        </div>
    </div>
</section>

<?php
// Cities with cinemas cross-link
$stmt = $db->prepare("SELECT DISTINCT city FROM cinemas WHERE is_active = 1 AND city != ? ORDER BY city ASC LIMIT 5");
$stmt->execute([$cinema['city']]);
$otherCities = $stmt->fetchAll() ?? [];
if (!empty($otherCities)):
?>
<section class="section" style="padding:0 24px;">
    <h2 class="section-title" style="margin-bottom:16px;font-size:1rem;"><?= htmlspecialchars($cinema['city']) ?> Cinemas &amp; Other Cities</h2>
    <div class="genre-strip">
        <a href="<?= e_link('/showtimes/' . strtolower(str_replace(' ', '-', $cinema['city']))) ?>" class="genre-pill">Showtimes in <?= htmlspecialchars($cinema['city']) ?></a>
        <?php foreach ($otherCities as $oc): ?>
        <a href="<?= e_link('/showtimes/' . strtolower(str_replace(' ', '-', $oc['city']))) ?>" class="genre-pill"><?= htmlspecialchars($oc['city']) ?></a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- AD PLACEMENT: Below showtimes -->
<?php renderAd('rectangle', 'ad-mt-4 ad-mb-2'); ?>

<!-- NEARBY CINEMAS -->
<?php if (!empty($nearbyCinemas)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Nearby Cinemas</h2>
        <a href="<?= e_link('/cinemas') ?>" class="section-link">See all</a>
    </div>
    <div class="cinema-scroll stagger">
        <?php foreach ($nearbyCinemas as $nc): ?>
        <a href="<?= e_link('/cinemas/' . rawurlencode($nc['slug'])) ?>" class="cinema-card">
            <div class="cinema-card-top">
                <div class="cinema-dot" style="background:<?= htmlspecialchars($nc['color_hex']) ?>"></div>
                <div class="cinema-card-name"><?= htmlspecialchars($nc['name']) ?></div>
            </div>
            <div class="cinema-card-detail">
                <?= htmlspecialchars($nc['chain_name']) ?>
                <?php if ($nc['movies_today']): ?> · <?= (int)$nc['movies_today'] ?> movies<?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- SEO Summary Block -->
<section class="seo-summary" style="padding:0 24px;max-width:1200px;margin:0 auto 48px;">
    <div style="background:var(--card);border-radius:var(--radius-lg);padding:24px;border:1px solid var(--border);">
        <h2 style="font-size:1.1rem;font-weight:600;margin-bottom:12px;"><?= htmlspecialchars($cinema['name']) ?> — Cinema in <?= htmlspecialchars($cinema['city']) ?></h2>
        <p style="color:var(--text-muted);line-height:1.7;font-size:0.85rem;margin-bottom:12px;">
            <?= htmlspecialchars($cinema['name']) ?> is a <?= htmlspecialchars($cinema['chain_name']) ?> cinema located in <?= htmlspecialchars($cinema['city']) ?><?= $cinema['area'] ? ', ' . htmlspecialchars($cinema['area']) : '' ?>, Lebanon.
            <?php if ($cinema['has_imax']): ?>Experience movies in IMAX format.<?php endif; ?>
            <?php if ($cinema['has_vip']): ?>VIP seating available for a premium experience.<?php endif; ?>
            Browse current movie showtimes, view available formats, and book tickets online.
        </p>
        <p style="color:var(--text-muted);font-size:0.85rem;">
            <strong>Facilities:</strong>
            <?php if ($cinema['has_imax']): ?><span style="margin-right:8px;">• IMAX</span><?php endif; ?>
            <?php if ($cinema['has_vip']): ?><span style="margin-right:8px;">• VIP</span><?php endif; ?>
            <?php if ($cinema['has_4dx']): ?><span style="margin-right:8px;">• 4DX</span><?php endif; ?>
            • Standard screens
        </p>
        <?php if (!empty($nearbyCinemas)): ?>
        <p style="color:var(--text-muted);font-size:0.85rem;margin-top:8px;">
            <strong>Nearby cinemas:</strong>
            <?php foreach ($nearbyCinemas as $nc): ?>
            <a href="<?= e_link('/cinemas/' . rawurlencode($nc['slug'])) ?>" style="color:var(--accent);"><?= htmlspecialchars($nc['name']) ?></a> ·
            <?php endforeach; ?>
            <a href="<?= e_link('/cinemas') ?>" style="color:var(--accent);">View all</a>
        </p>
        <?php endif; ?>
    </div>
</section>

</div><!-- end page-enter -->

<?php include __DIR__ . '/includes/footer.php'; ?>
