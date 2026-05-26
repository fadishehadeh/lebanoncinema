<?php
require_once __DIR__ . '/../config.php';

$db = getDbConnection();

// AJAX endpoint (used by live search dropdown)
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $q = trim(htmlspecialchars(substr($_GET['q'] ?? '', 0, 100)));
    if (strlen($q) < 2) {
        echo json_encode(['movies' => [], 'cinemas' => []]);
        exit;
    }

    $searchTerm = "%{$q}%";

    $stmt = $db->prepare("
        SELECT m.title, m.slug, COALESCE(m.poster_path, m.poster_url) AS poster_url, m.genres, m.rating,
               COUNT(DISTINCT s.id) AS showtimes_today
        FROM movies m
        LEFT JOIN showtimes s ON s.movie_id = m.id AND s.show_date = CURDATE()
        WHERE m.status = 'now_showing'
          AND (m.title LIKE ? OR m.genres LIKE ?)
        GROUP BY m.id
        ORDER BY showtimes_today DESC
        LIMIT 6
    ");
    $stmt->execute([$searchTerm, $searchTerm]);
    $movies = $stmt->fetchAll() ?? [];

    $stmt = $db->prepare("
        SELECT c.name, c.slug, ch.name AS chain_name, ch.color_hex,
               COUNT(DISTINCT s.movie_id) AS movies_today
        FROM cinemas c
        JOIN chains ch ON c.chain_id = ch.id
        LEFT JOIN showtimes s ON s.cinema_id = c.id AND s.show_date = CURDATE()
        WHERE c.is_active = 1 AND (c.name LIKE ? OR c.city LIKE ? OR c.area LIKE ?)
        GROUP BY c.id
        ORDER BY movies_today DESC
        LIMIT 4
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $cinemas = $stmt->fetchAll() ?? [];

    echo json_encode(['movies' => $movies, 'cinemas' => $cinemas]);
    exit;
}

// ========== FULL SEARCH RESULTS PAGE ==========
$q = trim(htmlspecialchars(substr($_GET['q'] ?? '', 0, 100)));

$movies = [];
$cinemas = [];

if (strlen($q) >= 2) {
    $searchTerm = "%{$q}%";

    $stmt = $db->prepare("
        SELECT m.id, m.title, m.slug, m.genres, m.rating, m.duration_min, m.vote_average, m.release_date, m.status, m.tmdb_id, COALESCE(m.poster_path, m.poster_url) AS poster_url, COUNT(DISTINCT s.id) AS showtimes_today
        FROM movies m
        LEFT JOIN showtimes s ON s.movie_id = m.id AND s.show_date = CURDATE()
        WHERE m.status = 'now_showing'
          AND (m.title LIKE ? OR m.genres LIKE ?)
        GROUP BY m.id
        ORDER BY showtimes_today DESC
        LIMIT 12
    ");
    $stmt->execute([$searchTerm, $searchTerm]);
    $movies = $stmt->fetchAll() ?? [];

    $stmt = $db->prepare("
        SELECT c.*, ch.name AS chain_name, ch.color_hex,
               COUNT(DISTINCT s.movie_id) AS movies_today
        FROM cinemas c
        JOIN chains ch ON c.chain_id = ch.id
        LEFT JOIN showtimes s ON s.cinema_id = c.id AND s.show_date = CURDATE()
        WHERE c.is_active = 1 AND (c.name LIKE ? OR c.city LIKE ? OR c.area LIKE ?)
        GROUP BY c.id
        ORDER BY movies_today DESC
        LIMIT 8
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $cinemas = $stmt->fetchAll() ?? [];
}

// Trending searches
$stmt = $db->prepare("
    SELECT DISTINCT m.genres FROM movies m
    WHERE m.status = 'now_showing' AND m.genres IS NOT NULL
    ORDER BY RAND() LIMIT 6
");
$stmt->execute();
$trendingGenres = array_filter(array_map('trim', explode(',', implode(',', array_column($stmt->fetchAll() ?: [], 'genres')))));
$trendingGenres = array_slice(array_unique($trendingGenres), 0, 6);
sort($trendingGenres);

// SEO
$siteUrl = rtrim(SITE_URL, '/');
$canonical = '/search' . ($q ? '?q=' . urlencode($q) : '');
$pageTitle = ($q ? htmlspecialchars($q) . ' — ' : '') . 'Search — ' . SITE_NAME;
$pageDescription = $q ? 'Search results for "' . htmlspecialchars($q) . '" — find movies, cinemas, and showtimes across Lebanon.' : 'Search movies, cinemas, and showtimes across Lebanon. Find what\'s playing at VOX, Grand, Empire and more.';
$breadcrumbs = [['pos' => 2, 'name' => 'Search', 'url' => $canonical]];
$jsonLd = [[
    '@type' => 'SearchResultsPage',
    '@id' => $siteUrl . $canonical . '#page',
    'name' => $pageTitle,
    'description' => $pageDescription,
    'isPartOf' => ['@id' => $siteUrl . '/#website'],
]];
require_once __DIR__ . '/includes/ad.php';
include __DIR__ . '/includes/header.php';
?>
<div class="page-enter">

<!-- Search Header -->
<div class="search-section" style="margin-top:24px;">
    <div class="search-wrap">
        <input class="search-input" id="hero-search" placeholder="Search movies, cinemas, genres..."
               value="<?= htmlspecialchars($q) ?>" autocomplete="off">
        <div class="search-icon"><i data-lucide="search"></i></div>
        <div class="search-dropdown" id="search-dropdown"></div>
    </div>
</div>

<?php if (strlen($q) < 2): ?>
    <!-- Trending searches (no query yet) -->
    <div class="section-header">
        <h2 class="section-title">Trending Searches</h2>
    </div>
    <?php if (!empty($trendingGenres)): ?>
    <div class="chips-row">
        <?php foreach ($trendingGenres as $g): ?>
        <a href="<?= e_link('/search?q=' . urlencode($g)) ?>" class="chip"><?= htmlspecialchars($g) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="empty-state">
        <p style="font-size:1.1rem;">What are you looking for?</p>
        <p>Search movies, cinemas, or genres above.</p>
    </div>
<?php else: ?>
    <!-- Search Results -->
    <div class="section-header">
        <h2 class="section-title">Results for "<?= htmlspecialchars($q) ?>"</h2>
        <span class="section-link"><?= count($movies) + count($cinemas) ?> results</span>
    </div>

    <?php if (empty($movies) && empty($cinemas)): ?>
        <div class="empty-state">
            <p>No results found for "<?= htmlspecialchars($q) ?>".</p>
            <p style="margin-top:12px;">Try searching for a movie title, genre, or cinema name.</p>
        </div>
        <?php if (!empty($trendingGenres)): ?>
        <div class="section-header" style="margin-top:32px;">
            <h2 class="section-title">Try these instead</h2>
        </div>
        <div class="chips-row">
            <?php foreach ($trendingGenres as $g): ?>
            <a href="<?= e_link('/search?q=' . urlencode($g)) ?>" class="chip"><?= htmlspecialchars($g) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Movies Results -->
        <?php if (!empty($movies)): ?>
        <div class="section-header" style="margin-top:8px;">
            <h2 class="section-title">Movies</h2>
            <span class="section-link"><?= count($movies) ?> found</span>
        </div>
        <div class="movie-grid stagger" style="margin-bottom:32px;">
            <?php foreach ($movies as $m): ?>
            <a href="<?= e_link('/movies/' . rawurlencode($m['slug'])) ?>" class="poster-card">
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

        <?php renderAd('rectangle', ['placement' => 'search_results_inline']); ?>
        <?php endif; ?>

        <!-- Cinemas Results -->
        <?php if (!empty($cinemas)): ?>
        <div class="section-header">
            <h2 class="section-title">Cinemas</h2>
            <span class="section-link"><?= count($cinemas) ?> found</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;padding:0 24px 32px;">
            <?php foreach ($cinemas as $c): ?>
            <a href="<?= e_link('/cinemas/' . rawurlencode($c['slug'])) ?>" class="cinema-card" style="width:100%;">
                <div class="cinema-card-top">
                    <div class="cinema-dot" style="background:<?= htmlspecialchars($c['color_hex']) ?>"></div>
                    <div class="cinema-card-name"><?= htmlspecialchars($c['name']) ?></div>
                    <?php if ($c['movies_today']): ?>
                    <div style="font-size:0.8rem;font-weight:700;color:var(--accent);"><?= (int)$c['movies_today'] ?></div>
                    <?php endif; ?>
                </div>
                <div class="cinema-card-detail">
                    <?= htmlspecialchars($c['chain_name']) ?>
                    <?php if ($c['city']): ?> · <?= htmlspecialchars($c['city']) ?><?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if (count($cinemas) >= 4 || count($movies) >= 8): ?>
            <?php renderAd('leaderboard', ['placement' => 'search_lower_inline']); ?>
        <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

</div><!-- end page-enter -->

<?php include __DIR__ . '/includes/footer.php'; ?>
