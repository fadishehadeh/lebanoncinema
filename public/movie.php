<?php
require_once __DIR__ . '/../config.php';

$db = getDbConnection();

$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'] ?? ''));
if (!$slug) {
    http_response_code(404);
    exit('Movie not found');
}

$stmt = $db->prepare("SELECT * FROM movies WHERE slug = ? LIMIT 1");
$stmt->execute([$slug]);
$movie = $stmt->fetch();

if (!$movie) {
    http_response_code(404);
    exit('Movie not found');
}

// Map new TMDb columns to old template variables for backward compatibility
$movie['poster_url']  = $movie['poster_path'] ?? $movie['poster_url'] ?? '';
$movie['backdrop']    = $movie['backdrop_path'] ?? $movie['poster_path'] ?? '';
$movie['synopsis']    = $movie['overview'] ?? $movie['synopsis'] ?? '';
$movie['rating']      = $movie['vote_average'] ? number_format((float)$movie['vote_average'], 1) : ($movie['rating'] ?? '');
$movie['trailer_url'] = $movie['trailer_key'] ? 'https://www.youtube.com/watch?v=' . $movie['trailer_key'] : ($movie['trailer_url'] ?? '');
$movie['genres']      = $movie['genres'] ?? '';
if (!empty($movie['genres_json'])) {
    $genreData = json_decode($movie['genres_json'], true);
    if (!empty($genreData) && is_array($genreData)) {
        $movie['genres'] = implode(', ', array_column($genreData, 'name'));
    }
}
// Cast is already handled via $movie['cast_json'] in the template

// Fetch showtimes for next 7 days
$stmt = $db->prepare("
    SELECT
        s.show_date,
        c.id AS cinema_id, c.name AS cinema_name, c.slug AS cinema_slug,
        ch.name AS chain_name, ch.color_hex,
        GROUP_CONCAT(s.show_time ORDER BY s.show_time SEPARATOR ',') AS times,
        s.format, s.booking_url
    FROM showtimes s
    JOIN cinemas c  ON s.cinema_id = c.id
    JOIN chains  ch ON c.chain_id  = ch.id
    WHERE s.movie_id = ? AND s.show_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)
    GROUP BY s.show_date, c.id, s.format
    ORDER BY s.show_date, ch.name, c.name
");
$stmt->execute([$movie['id']]);
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

$videoId = null;
if ($movie['trailer_url']) {
    parse_str(parse_url($movie['trailer_url'], PHP_URL_QUERY), $qs);
    $videoId = $qs['v'] ?? null;
}

function getMinutesUntil(string $timeStr): int {
    $now = time();
    $ts = strtotime(date('Y-m-d') . ' ' . $timeStr);
    return (int)(($ts - $now) / 60);
}

function urgencyLabel(int $mins): ?array {
    if ($mins < 0) return null;
    if ($mins <= 30) return ['class' => 'accent', 'label' => "Starting in {$mins} min"];
    if ($mins <= 90) return ['class' => 'orange', 'label' => 'Starting in ' . ceil($mins/60) . 'h'];
    return null;
}

// Nearest showtime for urgency bar
$urgencyBarData = null;
if (isset($byDate[date('Y-m-d')])) {
    foreach ($byDate[date('Y-m-d')] as $st) {
        foreach (explode(',', $st['times']) as $time) {
            $mins = getMinutesUntil($time);
            if ($mins >= 0) {
                $urgencyBarData = [
                    'mins' => $mins, 'time' => $time,
                    'cinema_name' => $st['cinema_name'], 'cinema_slug' => $st['cinema_slug'],
                    'booking_url' => $st['booking_url'],
                    'urgency' => urgencyLabel($mins),
                ];
                break 2;
            }
        }
    }
}

// RELATED MOVIES: same genre, excluding current
$related = [];
if ($movie['genres']) {
    $genreArr = array_map('trim', explode(',', $movie['genres']));
    $likeClauses = [];
    $params = [$movie['id']];
    foreach ($genreArr as $g) {
        $likeClauses[] = 'm.genres LIKE ?';
        $params[] = '%' . $g . '%';
    }
    $likeSql = implode(' OR ', $likeClauses);
    $stmt = $db->prepare("
        SELECT m.id, m.title, m.slug, COALESCE(m.poster_path, m.poster_url) AS poster_url, m.rating, m.genres
        FROM movies m
        WHERE m.id != ? AND m.status = 'now_showing' AND ($likeSql)
        GROUP BY m.id
        ORDER BY RAND()
        LIMIT 6
    ");
    $stmt->execute($params);
    $related = $stmt->fetchAll() ?? [];
}

// If not enough related by genre, fill with any now_showing
if (count($related) < 6) {
    $existingIds = array_merge([$movie['id']], array_column($related, 'id'));
    $placeholders = implode(',', array_fill(0, count($existingIds), '?'));
    $stmt = $db->prepare("
        SELECT m.id, m.title, m.slug, COALESCE(m.poster_path, m.poster_url) AS poster_url, m.rating, m.genres
        FROM movies m
        WHERE m.id NOT IN ($placeholders) AND m.status = 'now_showing'
        ORDER BY RAND()
        LIMIT ?
    ");
    $params = array_merge($existingIds, [6 - count($related)]);
    $stmt->execute($params);
    $more = $stmt->fetchAll() ?? [];
    $related = array_merge($related, $more);
}

// SEO metadata
$siteUrl = rtrim(SITE_URL, '/');
$canonicalUrl = $siteUrl . '/movies/' . rawurlencode($slug);
$pageTitle = htmlspecialchars($movie['title']) . ' Showtimes in Lebanon — ' . SITE_NAME;
$pageDescription = 'Find showtimes for "' . htmlspecialchars($movie['title']) . '" at cinemas across Lebanon. ' . ($movie['synopsis'] ? htmlspecialchars(substr($movie['synopsis'], 0, 150)) : 'Watch trailer, read synopsis, and book tickets online.');
$pageImage = $movie['poster_url'] ?? '';
$canonical = '/movies/' . rawurlencode($slug);
$breadcrumbs = [
    ['pos' => 2, 'name' => 'Movies', 'url' => '/movies'],
    ['pos' => 3, 'name' => $movie['title'], 'url' => $canonical],
];

// Build ScreeningEvent for each cinema today
$screenings = [];
if (isset($byDate[date('Y-m-d')])) {
    foreach ($byDate[date('Y-m-d')] as $st) {
        $times = explode(',', $st['times']);
        foreach ($times as $t) {
            $screenings[] = [
                '@type' => 'ScreeningEvent',
                'name' => $movie['title'] . ' at ' . $st['cinema_name'],
                'startDate' => date('Y-m-d') . 'T' . $t,
                'workPresented' => ['@type' => 'Movie', 'name' => $movie['title'], '@id' => $canonicalUrl . '#movie'],
                'location' => [
                    '@type' => 'MovieTheater',
                    'name' => $st['cinema_name'],
                    'url' => $siteUrl . '/cinemas/' . rawurlencode($st['cinema_slug']),
                ],
            ];
            if (count($screenings) >= 10) break 2;
        }
    }
}

// Build cinema names list for FAQ
$cinemaNamesList = !empty($showtimeRows) ? implode(', ', array_unique(array_map(fn($s) => $s['cinema_name'], $showtimeRows))) : 'select cinemas';

$jsonLd = [
    [
        '@type' => 'Movie',
        '@id' => $canonicalUrl . '#movie',
        'name' => $movie['title'],
        'url' => $canonicalUrl,
        'image' => $movie['poster_url'] ?? '',
        'description' => $movie['synopsis'] ?? '',
        'datePublished' => $movie['release_date'] ?? '',
        'duration' => $movie['duration_min'] ? 'PT' . (int)$movie['duration_min'] . 'M' : '',
        'genre' => $movie['genres'] ? array_map('trim', explode(',', $movie['genres'])) : [],
        'trailer' => $movie['trailer_url'] ? ['@type' => 'VideoObject', 'name' => $movie['title'] . ' Trailer', 'contentUrl' => $movie['trailer_url']] : null,
    ],
    ...$screenings,
    [
        '@type' => 'FAQPage',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => 'Where can I watch ' . $movie['title'] . ' in Lebanon?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $movie['title'] . ' is currently showing at ' . $cinemaNamesList . '. Find showtimes, watch the trailer, and book tickets on LebanonCinema.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'What time is ' . $movie['title'] . ' showing today in Beirut?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $movie['title'] . ' showtimes vary by cinema location. Check the full schedule at VOX, Grand, Empire, CinemaCity and other cinemas in Lebanon on LebanonCinema.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Is ' . $movie['title'] . ' now showing in Lebanese cinemas?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => !empty($showtimeRows) ? 'Yes, ' . $movie['title'] . ' is currently showing at ' . $cinemaNamesList . '. Browse showtimes and book your tickets online.' : 'Check LebanonCinema for the latest showtime availability.',
                ],
            ],
        ],
    ],
    [
        '@type' => 'SpeakableSpecification',
        'cssSelector' => ['.hero-tagline', '.detail-synopsis', '.seo-summary'],
    ],
];

require_once __DIR__ . '/includes/ad.php';
include __DIR__ . '/includes/header.php';
?>
<div class="page-enter">

<!-- Cinematic backdrop -->
<?php $bgUrl = $movie['backdrop'] ?: $movie['poster_url']; ?>
<div class="movie-backdrop" style="background-image: url('<?= htmlspecialchars($bgUrl) ?>')"></div>

<?php if ($urgencyBarData && $urgencyBarData['urgency']): ?>
<div style="max-width:1200px;margin:0 auto;padding:0 24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;background:var(--card);border:1px solid var(--border-strong);border-radius:var(--radius-lg);padding:16px 20px;margin-bottom:24px;animation:fadeUp 0.5s var(--ease);">
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="padding:4px 12px;border-radius:20px;font-size:0.7rem;font-weight:700;background:var(--accent);color:#000;">
                <?= htmlspecialchars($urgencyBarData['urgency']['label']) ?>
            </span>
            <span style="font-size:0.85rem;color:var(--text-muted);">at <?= htmlspecialchars($urgencyBarData['cinema_name']) ?></span>
        </div>
        <a href="<?= htmlspecialchars($urgencyBarData['booking_url'] ?? '#') ?>"
           target="<?= $urgencyBarData['booking_url'] ? '_blank' : '' ?>"
           class="btn btn-primary" style="padding:10px 20px;font-size:0.85rem;">
            Book Now
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Detail Hero -->
<div class="detail-hero fade-up">
    <div class="detail-poster">
        <?php if ($movie['poster_url']): ?>
            <img src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="<?= htmlspecialchars($movie['title']) ?>">
        <?php else: ?>
            <div style="width:100%;height:100%;background:var(--card);display:flex;align-items:center;justify-content:center;font-size:4rem;color:var(--text-muted);">
                <?= htmlspecialchars(substr($movie['title'], 0, 1)) ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="detail-info">
        <h1 class="detail-title"><?= htmlspecialchars($movie['title']) ?></h1>

        <div class="detail-meta">
            <?php if ($movie['release_date']): ?>
                <span><?= date('Y', strtotime($movie['release_date'])) ?></span>
            <?php endif; ?>
            <?php if ($movie['duration_min']): ?>
                <span><?= (int)$movie['duration_min'] ?> min</span>
            <?php endif; ?>
            <?php if ($movie['language']): ?>
                <span><?= htmlspecialchars($movie['language']) ?></span>
            <?php endif; ?>
        </div>

        <div class="detail-tags">
            <?php if ($movie['rating']): ?>
                <span class="detail-tag rating"><?= htmlspecialchars($movie['rating']) ?></span>
            <?php endif; ?>
            <?php if ($movie['genres']): ?>
                <?php foreach (array_map('trim', explode(',', $movie['genres'])) as $g): ?>
                    <a href="/genres/<?= urlencode(strtolower(str_replace(' ', '-', $g))) ?>" class="detail-tag genre"><?= htmlspecialchars($g) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($movie['synopsis']): ?>
            <p class="detail-synopsis"><?= htmlspecialchars($movie['synopsis']) ?></p>
        <?php endif; ?>

        <?php if (!empty($movie['cast_json'])): $cast = json_decode($movie['cast_json'], true); ?>
        <?php if (!empty($cast)): ?>
        <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:20px;">
            <?php foreach ($cast as $actor): ?>
            <div style="text-align:center;width:70px;">
                <?php if ($actor['profile']): ?>
                    <img src="<?= htmlspecialchars($actor['profile']) ?>" alt="<?= htmlspecialchars($actor['name']) ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover;margin-bottom:4px;border:2px solid var(--border);">
                <?php else: ?>
                    <div style="width:50px;height:50px;border-radius:50%;background:var(--card);display:flex;align-items:center;justify-content:center;margin:0 auto 4px;font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars(substr($actor['name'], 0, 2)) ?></div>
                <?php endif; ?>
                <div style="font-size:0.65rem;font-weight:600;line-height:1.2;"><?= htmlspecialchars($actor['name']) ?></div>
                <div style="font-size:0.55rem;color:var(--text-muted);line-height:1.2;"><?= htmlspecialchars($actor['character']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="detail-ctas">
            <?php if ($videoId): ?>
                <a href="#trailer" class="btn btn-outline">
                    <i data-lucide="play"></i>
                    Watch Trailer
                </a>
            <?php endif; ?>
            <button class="btn btn-outline" id="watchlist-btn" data-slug="<?= htmlspecialchars($slug) ?>" style="padding:12px 16px;">
                <i data-lucide="heart"></i>
            </button>
            <button class="btn btn-outline" id="share-btn" style="padding:12px 16px;">
                <i data-lucide="share-2"></i>
            </button>
        </div>
    </div>
</div>

<!-- Trailer -->
<?php if ($videoId): ?>
<div class="showtimes-wrap" id="trailer" style="margin-bottom:48px;">
    <h2 class="section-title" style="margin-bottom:16px;">Trailer</h2>
    <div style="position:relative;width:100%;max-width:800px;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:var(--radius-lg);border:1px solid var(--border);">
        <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($videoId) ?>" allowfullscreen
                style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;border-radius:var(--radius-lg);"></iframe>
    </div>
</div>
<?php endif; ?>

<!-- Showtimes -->
<?php if (!empty($byDate)): ?>
<div class="showtimes-wrap">
    <h2 class="section-title" style="margin-bottom:20px;">Showtimes</h2>

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
        <?php foreach ($byDate[$selDate] as $st): ?>
        <div class="showtime-group fade-up">
            <div class="showtime-cinema">
                <span class="dot" style="background:<?= htmlspecialchars($st['color_hex']) ?>"></span>
                <span class="name"><?= htmlspecialchars($st['cinema_name']) ?></span>
                <?php if ($st['format'] && $st['format'] !== 'Standard'): ?>
                    <span class="format"><?= htmlspecialchars($st['format']) ?></span>
                <?php endif; ?>
            </div>
            <div class="times-grid">
                <?php foreach (explode(',', $st['times']) as $time): ?>
                    <a href="<?= htmlspecialchars($st['booking_url'] ?? '#') ?>"
                       target="<?= $st['booking_url'] ? '_blank' : '' ?>"
                       class="time-btn <?= $st['booking_url'] ? 'bookable' : '' ?>">
                        <?= date('g:i a', strtotime($time)) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <p>No showtimes available for this date.</p>
        </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="showtimes-wrap">
    <div class="empty-state">
        <p>No showtimes available in the next 7 days.</p>
    </div>
</div>
<?php endif; ?>

<!-- AD PLACEMENT: Below showtimes -->
<?php renderAd('rectangle', 'ad-mt-6 ad-mb-2'); ?>

<!-- SEO Summary Block (visible, indexable, AI-friendly) -->
<section class="seo-summary" style="padding:0 24px;max-width:1200px;margin:0 auto 48px;">
    <div style="background:var(--card);border-radius:var(--radius-lg);padding:24px;border:1px solid var(--border);">
        <h2 style="font-size:1.1rem;font-weight:600;margin-bottom:12px;"><?= htmlspecialchars($movie['title']) ?> — Showtimes in Lebanon</h2>
        <p style="color:var(--text-muted);line-height:1.7;font-size:0.85rem;margin-bottom:12px;">
            Looking for <?= htmlspecialchars($movie['title']) ?> showtimes in Lebanon? 
            <?php if (!empty($showtimeRows)): ?>
            You can watch <?= htmlspecialchars($movie['title']) ?> at <?= htmlspecialchars($cinemaNamesList) ?>.
            <?php endif; ?>
            <?php if ($movie['duration_min']): ?>Runtime is <?= (int)$movie['duration_min'] ?> minutes.<?php endif; ?>
            <?php if ($movie['release_date']): ?>Released <?= date('F j, Y', strtotime($movie['release_date'])) ?>.<?php endif; ?>
            Browse showtimes, watch the trailer, and book tickets online.
        </p>
        <?php if ($movie['genres_list']): ?>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;">
            <?php foreach ($movie['genres_list'] as $g): ?>
            <a href="<?= e_link('/genres/' . strtolower(str_replace(' ', '-', $g))) ?>" style="padding:4px 10px;border-radius:4px;background:var(--surface2);color:var(--text-muted);font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;"><?= htmlspecialchars($g) ?></a>
            <?php endforeach; ?>
            <?php if ($movie['imdb_id']): ?>
            <a href="https://www.imdb.com/title/<?= htmlspecialchars($movie['imdb_id']) ?>/" target="_blank" rel="noopener" style="padding:4px 10px;border-radius:4px;background:#f5c518;color:#000;font-size:0.7rem;font-weight:700;">IMDb</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div style="font-size:0.8rem;color:var(--text-muted);">
            <strong>Available at:</strong>
            <?php if (!empty($showtimeRows)): ?>
            <?php foreach (array_unique(array_map(fn($s) => $s['cinema_name'], $showtimeRows)) as $name): ?>
            <span style="margin-right:8px;">• <?= htmlspecialchars($name) ?></span>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- RELATED MOVIES -->
<?php if (!empty($related)): ?>
<section class="section" style="margin-top:48px;">
    <div class="section-header">
        <h2 class="section-title">You Might Also Like</h2>
        <a href="/movies" class="section-link">More movies</a>
    </div>
    <div class="carousel stagger">
        <?php foreach ($related as $r): ?>
        <a href="<?= e_link('/movies/' . rawurlencode($r['slug'])) ?>" class="poster-card">
            <?php if ($r['poster_url']): ?>
                <img class="poster-card-img" src="<?= htmlspecialchars($r['poster_url']) ?>" alt="<?= htmlspecialchars($r['title']) ?>" loading="lazy">
            <?php else: ?>
                <div class="poster-card-img" style="background:var(--card);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:2.5rem;">
                    <?= htmlspecialchars(substr($r['title'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div class="poster-card-overlay">
                <div class="poster-card-title"><?= htmlspecialchars($r['title']) ?></div>
                <div class="poster-card-meta"><?= htmlspecialchars(substr($r['genres'] ?? '', 0, 30)) ?></div>
            </div>
            <?php if ($r['rating']): ?>
                <span class="poster-card-badge accent"><?= htmlspecialchars($r['rating']) ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

</div><!-- end page-enter -->

<?php include __DIR__ . '/includes/footer.php'; ?>
