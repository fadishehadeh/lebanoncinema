<?php
require_once __DIR__ . '/../config.php';

$db = getDbConnection();

$genreFilter = $_GET['genre'] ?? null;
if ($genreFilter) {
    $genreFilter = htmlspecialchars(preg_replace('/[^a-z0-9\s-]/i', '', $genreFilter));
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

// Fetch all distinct genres
$stmt = $db->prepare("SELECT DISTINCT genres FROM movies WHERE status = 'now_showing' AND genres IS NOT NULL");
$stmt->execute();
$genreRows = $stmt->fetchAll();

$allGenres = [];
foreach ($genreRows as $row) {
    if ($row['genres']) {
        $parts = array_map('trim', explode(',', $row['genres']));
        $allGenres = array_merge($allGenres, $parts);
    }
}
$allGenres = array_unique($allGenres);
sort($allGenres);

// Main query
$sql = "
    SELECT m.id, m.title, m.slug, COALESCE(m.poster_path, m.poster_url) AS poster_url, m.rating, m.genres, m.duration_min, m.language,
        COUNT(DISTINCT s.show_date) AS showtime_days,
        SUM(CASE WHEN s.show_date = CURDATE() THEN 1 ELSE 0 END) AS times_today,
        MIN(CASE WHEN s.show_date = CURDATE() THEN s.show_time ELSE NULL END) AS first_today,
        MAX(CASE WHEN c.has_imax = 1 AND s.show_date = CURDATE() THEN 1 ELSE 0 END) AS has_imax,
        MAX(CASE WHEN c.has_vip = 1 AND s.show_date = CURDATE() THEN 1 ELSE 0 END) AS has_vip
    FROM movies m
    LEFT JOIN showtimes s ON s.movie_id = m.id AND s.show_date >= CURDATE()
    LEFT JOIN cinemas c ON s.cinema_id = c.id
    WHERE m.status = 'now_showing'
";

if ($genreFilter) {
    $sql .= " AND m.genres LIKE ?";
}

$sql .= " GROUP BY m.id ORDER BY times_today DESC, m.title ASC";

$stmt = $db->prepare($sql);
if ($genreFilter) {
    $stmt->execute(["%{$genreFilter}%"]);
} else {
    $stmt->execute();
}
$movies = array_map('mapMovieRow', $stmt->fetchAll());

// SEO
$siteUrl = rtrim(SITE_URL, '/');
$canonical = '/movies' . ($genreFilter ? '?genre=' . urlencode($genreFilter) : '');
$pageTitle = ($genreFilter ? htmlspecialchars($genreFilter) . ' Movies ' : 'Movies ') . 'Showing Now in Lebanon — ' . SITE_NAME;
$pageDescription = 'Browse ' . ($genreFilter ? htmlspecialchars($genreFilter) . ' ' : '') . 'movies showing now at cinemas across Lebanon. Find showtimes, watch trailers, and book tickets for ' . ($genreFilter ? htmlspecialchars($genreFilter) . ' ' : '') . 'films at VOX, Grand, Empire and more.';
$breadcrumbs = $genreFilter ? [
    ['pos' => 2, 'name' => 'Movies', 'url' => '/movies'],
    ['pos' => 3, 'name' => $genreFilter, 'url' => $canonical],
] : [
    ['pos' => 2, 'name' => 'Movies', 'url' => '/movies'],
];

$jsonLd = [[
    '@type' => 'CollectionPage',
    '@id' => $siteUrl . $canonical . '#page',
    'name' => $pageTitle,
    'description' => $pageDescription,
    'isPartOf' => ['@id' => $siteUrl . '/#website'],
]];

require_once __DIR__ . '/includes/ad.php';
include __DIR__ . '/includes/header.php';
?>

<div class="page-enter">

<div class="section-header" style="margin-bottom:8px;">
    <h2 class="section-title" style="font-size:1.5rem;">Now Showing</h2>
    <?php if (!empty($movies)): ?>
        <span class="section-link"><?= count($movies) ?> movies</span>
    <?php endif; ?>
</div>

<!-- Genre Filter -->
<?php if (!empty($allGenres)): ?>
<div class="genre-strip">
    <a href="/movies" class="genre-pill <?= !$genreFilter ? 'active' : '' ?>">All</a>
    <?php foreach ($allGenres as $g): ?>
        <a href="/movies?genre=<?= urlencode($g) ?>"
           class="genre-pill <?= ($genreFilter === $g) ? 'active' : '' ?>">
            <?= htmlspecialchars($g) ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Movie Grid -->
<?php if (empty($movies)): ?>
    <div class="empty-state"><p>No movies found.</p></div>
<?php else: ?>
    <div class="movie-grid stagger">
        <?php foreach ($movies as $i => $m):
            $urgency = $m['first_today'] ? urgencyLabel(getMinutesUntil($m['first_today'])) : null;
            $formats = [];
            if ($m['has_imax']) $formats[] = 'imax';
            if ($m['has_vip']) $formats[] = 'vip';
            // Mid-grid ad after every 8 items
            if ($i > 0 && $i % 8 === 0):
        ?>
            <div style="grid-column:1/-1;padding:8px 0;"><?php renderAd('rectangle'); ?></div>
        <?php endif; ?>
        <a href="<?= e_link('/movies/' . rawurlencode($m['slug'])) ?>" class="poster-card"
           data-movie-item
           data-genres="<?= htmlspecialchars(strtolower($m['genres'] ?? '')) ?>"
           data-formats="<?= implode(',', $formats) ?>"
           data-urgency="<?= $m['first_today'] ? getMinutesUntil($m['first_today']) : -1 ?>">
            <?php if ($m['poster_url']): ?>
                <img class="poster-card-img" src="<?= htmlspecialchars($m['poster_url']) ?>" alt="<?= htmlspecialchars($m['title']) ?>" loading="lazy">
            <?php else: ?>
                <div class="poster-card-img" style="background:var(--card);display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text-muted);font-size:2.5rem;">
                    <?= htmlspecialchars(substr($m['title'], 0, 1)) ?>
                    <span style="font-size:0.5rem;letter-spacing:0.1em;margin-top:4px;">NO POSTER</span>
                </div>
            <?php endif; ?>

            <!-- Always-visible bottom overlay -->
            <div class="default-overlay">
                <?php if ($urgency): ?>
                    <span class="poster-card-badge <?= $urgency['class'] ?>" style="position:relative;top:auto;right:auto;display:inline-block;width:fit-content;margin-bottom:6px;"><?= htmlspecialchars($urgency['label']) ?></span>
                <?php endif; ?>
                <div class="poster-card-title"><?= htmlspecialchars($m['title']) ?></div>
                <div class="poster-card-meta">
                    <?php if ($m['genres']): ?><span><?= htmlspecialchars(substr($m['genres'], 0, 30)) ?></span><?php endif; ?>
                </div>
            </div>

            <!-- Hover overlay -->
            <div class="poster-card-overlay">
                <div class="poster-card-title"><?= htmlspecialchars($m['title']) ?></div>
                <div class="poster-card-meta">
                    <?php if ($m['duration_min']): ?><?= (int)$m['duration_min'] ?> min · <?php endif; ?>
                    <?php if ($m['times_today']): ?><?= (int)$m['times_today'] ?> showtimes today<?php endif; ?>
                </div>
            </div>

            <?php if ($m['rating']): ?>
                <span class="poster-card-badge accent"><?= htmlspecialchars($m['rating']) ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</div><!-- end page-enter -->

<?php include __DIR__ . '/includes/footer.php'; ?>
