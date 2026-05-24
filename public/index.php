<?php
require_once __DIR__ . '/../config.php';

$db = getDbConnection();

// Helper: minutes until a time
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

// HERO: Most popular movie today
$stmt = $db->prepare("
    SELECT m.*, COUNT(s.id) AS showtime_count,
           GROUP_CONCAT(DISTINCT s.show_time ORDER BY s.show_time SEPARATOR ',') AS hero_times
    FROM movies m
    JOIN showtimes s ON s.movie_id = m.id AND s.show_date = CURDATE()
    GROUP BY m.id
    ORDER BY showtime_count DESC
    LIMIT 1
");
$stmt->execute();
$heroMovie = $stmt->fetch();
if ($heroMovie) $heroMovie = mapMovieRow($heroMovie);

// TRENDING TONIGHT
$stmt = $db->prepare("
    SELECT m.id, m.title, m.slug, COALESCE(m.poster_path, m.poster_url) AS poster_url, m.rating, m.genres, m.duration_min,
           COUNT(s.id) AS showtime_count,
           MIN(s.show_time) AS first_showtime,
           MAX(CASE WHEN c.has_imax = 1 THEN 1 ELSE 0 END) AS has_imax,
           MAX(CASE WHEN c.has_vip = 1 THEN 1 ELSE 0 END) AS has_vip
    FROM movies m
    JOIN showtimes s ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    WHERE s.show_date = CURDATE()
    GROUP BY m.id
    ORDER BY showtime_count DESC
    LIMIT 12
");
$stmt->execute();
$trending = array_map('mapMovieRow', $stmt->fetchAll() ?? []);

// STARTING SOON
$stmt = $db->prepare("
    SELECT DISTINCT m.id, m.title, m.slug, COALESCE(m.poster_path, m.poster_url) AS poster_url, m.rating, m.genres, m.duration_min,
           MIN(s.show_time) AS next_showtime,
           c.name AS cinema_name, c.slug AS cinema_slug,
           c.has_imax, c.has_vip,
           ch.color_hex, s.format, s.booking_url
    FROM showtimes s
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    JOIN chains ch ON c.chain_id = ch.id
    WHERE s.show_date = CURDATE()
      AND s.show_time BETWEEN CURTIME() AND ADDTIME(CURTIME(), '02:30:00')
    GROUP BY m.id, c.id, s.format
    ORDER BY next_showtime ASC
    LIMIT 12
");
$stmt->execute();
$startingSoon = array_map('mapMovieRow', $stmt->fetchAll() ?? []);

// CINEMAS NEAR YOU (all active cinemas)
$stmt = $db->prepare("
    SELECT c.id, c.name, c.slug, c.city, c.area,
           c.has_imax, c.has_vip, c.has_4dx,
           ch.name AS chain_name, ch.color_hex,
           COUNT(DISTINCT s.movie_id) AS movies_today,
           MIN(s.show_time) AS next_showtime
    FROM cinemas c
    JOIN chains ch ON c.chain_id = ch.id
    LEFT JOIN showtimes s ON s.cinema_id = c.id AND s.show_date = CURDATE()
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY movies_today DESC
    LIMIT 8
");
$stmt->execute();
$cinemas = $stmt->fetchAll() ?? [];

// UPCOMING RELEASES
$stmt = $db->prepare("
    SELECT * FROM movies
    WHERE status = 'coming_soon'
    ORDER BY release_date DESC
    LIMIT 12
");
$stmt->execute();
$upcoming = array_map('mapMovieRow', $stmt->fetchAll() ?? []);

$pageTitle = 'Movies Showing Today in Lebanon — ' . SITE_NAME;
$pageDescription = 'Discover movies playing today at cinemas across Lebanon. Browse showtimes for VOX, Grand, Empire, CinemaCity and more. Book tickets online.';
$showSkeleton = true;
$breadcrumbs = [];
include __DIR__ . '/includes/header.php';
?>

<div class="page-enter">

<?php require_once __DIR__ . '/includes/ad.php'; ?>

<?php if ($heroMovie): ?>
<!-- ════════════════════ HERO ════════════════════ -->
<section class="hero">
    <div class="hero-backdrop">
        <?php if ($heroMovie['poster_url']): ?>
            <img src="<?= htmlspecialchars($heroMovie['poster_url']) ?>" alt="">
        <?php endif; ?>
    </div>
    <div class="hero-content fade-up">
        <div class="hero-poster">
            <?php if ($heroMovie['poster_url']): ?>
                <img src="<?= htmlspecialchars($heroMovie['poster_url']) ?>" alt="<?= htmlspecialchars($heroMovie['title']) ?>">
            <?php else: ?>
                <div style="width:100%;height:100%;background:var(--card);display:flex;align-items:center;justify-content:center;font-size:3rem;color:var(--text-muted);">
                    <?= htmlspecialchars(substr($heroMovie['title'], 0, 1)) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="hero-info">
            <?php if ($heroMovie['showtime_count'] > 0): ?>
                <div class="hero-badge">
                    <i data-lucide="trending-up" style="width:14px;height:14px;"></i>
                    Most booked tonight
                </div>
            <?php endif; ?>
            <h1 class="hero-title"><?= htmlspecialchars($heroMovie['title']) ?></h1>
            <?php if ($heroMovie['synopsis']): ?>
                <p class="hero-tagline"><?= htmlspecialchars(substr($heroMovie['synopsis'], 0, 200)) ?></p>
            <?php endif; ?>
            <div class="hero-meta">
                <?php if ($heroMovie['release_date']): ?>
                    <span><?= date('Y', strtotime($heroMovie['release_date'])) ?></span>
                    <span class="dot"></span>
                <?php endif; ?>
                <?php if ($heroMovie['duration_min']): ?>
                    <span><?= (int)$heroMovie['duration_min'] ?> min</span>
                    <span class="dot"></span>
                <?php endif; ?>
                <?php if ($heroMovie['rating']): ?>
                    <span>⭐ <?= htmlspecialchars($heroMovie['rating']) ?></span>
                <?php endif; ?>
            </div>
            <div class="hero-ctas">
                <a href="<?= e_link('/movies/' . rawurlencode($heroMovie['slug'])) ?>" class="btn btn-primary">
                    <i data-lucide="ticket"></i>
                    Book Now
                </a>
                <?php if ($heroMovie['trailer_url']): ?>
                    <a href="<?= e_link('/movies/' . rawurlencode($heroMovie['slug'])) ?>" class="btn btn-outline">
                        <i data-lucide="play"></i>
                        Watch Trailer
                    </a>
                <?php endif; ?>
                <button class="btn btn-outline watchlist-btn" data-slug="<?= htmlspecialchars($heroMovie['slug']) ?>" style="padding:12px 16px;">
                    <i data-lucide="heart"></i>
                </button>
            </div>
            <?php if (!empty($heroMovie['hero_times'])): ?>
                <div class="hero-quick-times">
                    <span class="label">Showtimes</span>
                    <?php
                    $times = explode(',', $heroMovie['hero_times']);
                    $shown = 0;
                    foreach ($times as $t):
                        if ($shown >= 4) break;
                        $mins = getMinutesUntil($t);
                        if ($mins < -60) continue;
                        $shown++;
                    ?>
                        <span class="hero-time-chip"><?= date('g:i a', strtotime($t)) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php else: ?>
<!-- ════════════════════ HERO (fallback) ════════════════════ -->
<section class="hero" style="min-height:40vh;">
    <div class="hero-content" style="justify-content:center;">
        <div class="hero-search-fallback">
            <h1>What are you watching tonight?</h1>
            <p>Discover movies playing at cinemas across Lebanon.</p>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- AD PLACEMENT 1: Below Hero -->
<?php renderAd('leaderboard', 'ad-mt-4 ad-mb-6'); ?>

<!-- ════════════════════ SEARCH ════════════════════ -->
<div class="search-section">
    <div class="search-wrap">
        <input class="search-input" id="hero-search" placeholder="Search movies, cinemas, genres..." autocomplete="off">
        <div class="search-icon"><i data-lucide="search"></i></div>
        <div class="search-dropdown" id="search-dropdown"></div>
    </div>
</div>

<!-- ════════════════════ DISCOVERY CHIPS ════════════════════ -->
<div class="chips-row">
    <button class="chip active" data-filter="all">All</button>
    <button class="chip" data-filter="soon">Starting Soon</button>
    <button class="chip" data-filter="vip">VIP & IMAX</button>
    <button class="chip" data-filter="action">Action</button>
    <button class="chip" data-filter="comedy">Comedy</button>
    <button class="chip" data-filter="horror">Horror</button>
    <button class="chip" data-filter="family">Family</button>
</div>

<!-- ════════════════════ TRENDING TONIGHT ════════════════════ -->
<?php if (!empty($trending)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Trending Tonight</h2>
        <a href="/movies" class="section-link">See all</a>
    </div>
    <div class="carousel stagger">
        <?php foreach ($trending as $i => $m):
            $urgency = $m['first_showtime'] ? urgencyLabel(getMinutesUntil($m['first_showtime'])) : null;
            // AD PLACEMENT 3: Inline in carousel after every 8th card
            if ($i > 0 && $i % 8 === 0):
        ?>
            <?php renderAd('card', 'ad-inline'); ?>
        <?php endif; ?>
        <a href="<?= e_link('/movies/' . rawurlencode($m['slug'])) ?>" class="poster-card"
           data-movie-item
           data-genres="<?= htmlspecialchars(strtolower($m['genres'] ?? '')) ?>"
           data-formats="<?= ($m['has_imax'] ? 'imax,' : '') . ($m['has_vip'] ? 'vip,' : '') ?>"
           data-urgency="<?= $m['first_showtime'] ? getMinutesUntil($m['first_showtime']) : -1 ?>">
            <?php if ($m['poster_url']): ?>
                <img class="poster-card-img" src="<?= htmlspecialchars($m['poster_url']) ?>" alt="<?= htmlspecialchars($m['title']) ?>" loading="lazy">
            <?php else: ?>
                <div class="poster-card-img" style="background:var(--card);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:2.5rem;">
                    <?= htmlspecialchars(substr($m['title'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div class="poster-card-overlay">
                <div class="poster-card-title"><?= htmlspecialchars($m['title']) ?></div>
                <div class="poster-card-meta">
                    <?php if ($m['genres']): ?><?= htmlspecialchars(substr($m['genres'], 0, 30)) ?><?php endif; ?>
                </div>
            </div>
            <?php if ($m['rating']): ?>
                <span class="poster-card-badge accent"><?= htmlspecialchars($m['rating']) ?></span>
            <?php endif; ?>
            <?php if ($urgency): ?>
                <span class="poster-card-badge <?= $urgency['class'] ?>"><?= htmlspecialchars($urgency['label']) ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ════════════════════ STARTING SOON ════════════════════ -->
<?php if (!empty($startingSoon)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Starting Soon</h2>
    </div>
    <div class="carousel stagger">
        <?php foreach ($startingSoon as $m):
            $mins = getMinutesUntil($m['next_showtime']);
            $urgency = urgencyLabel($mins);
        ?>
        <a href="<?= e_link('/movies/' . rawurlencode($m['slug'])) ?>" class="poster-card"
           data-movie-item
           data-genres="<?= htmlspecialchars(strtolower($m['genres'] ?? '')) ?>"
           data-formats="<?= ($m['has_imax'] ? 'imax,' : '') . ($m['has_vip'] ? 'vip,' : '') ?>"
           data-urgency="<?= $mins ?>">
            <?php if ($m['poster_url']): ?>
                <img class="poster-card-img" src="<?= htmlspecialchars($m['poster_url']) ?>" alt="<?= htmlspecialchars($m['title']) ?>" loading="lazy">
            <?php else: ?>
                <div class="poster-card-img" style="background:var(--card);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:2.5rem;">
                    <?= htmlspecialchars(substr($m['title'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <?php if ($urgency): ?>
                <span class="poster-card-badge <?= $urgency['class'] ?>"><?= htmlspecialchars($urgency['label']) ?></span>
            <?php endif; ?>
            <div class="poster-card-overlay">
                <div class="poster-card-title"><?= htmlspecialchars($m['title']) ?></div>
                <div class="poster-card-meta"><?= htmlspecialchars($m['cinema_name']) ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- AD PLACEMENT 2: Mid-content (between Trending and Cinemas) -->
<?php renderAd('rectangle', 'ad-mt-2 ad-mb-6'); ?>

<!-- ════════════════════ CINEMAS NEAR YOU ════════════════════ -->
<?php if (!empty($cinemas)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Cinemas Near You</h2>
        <a href="/cinemas" class="section-link">See all</a>
    </div>
    <div class="cinema-scroll stagger">
        <?php foreach ($cinemas as $c): ?>
        <a href="<?= e_link('/cinemas/' . rawurlencode($m['slug'])) ?>" class="cinema-card">
            <div class="cinema-card-top">
                <div class="cinema-dot" style="background:<?= htmlspecialchars($c['color_hex']) ?>"></div>
                <div class="cinema-card-name"><?= htmlspecialchars($c['name']) ?></div>
            </div>
            <div class="cinema-card-detail">
                <?= htmlspecialchars($c['chain_name']) ?>
                <?php if ($c['movies_today']): ?> · <?= (int)$c['movies_today'] ?> movies today<?php endif; ?>
            </div>
            <div class="cinema-card-footer">
                <div class="cinema-card-badges">
                    <?php if ($c['has_imax']): ?><span class="mini-badge">IMAX</span><?php endif; ?>
                    <?php if ($c['has_vip']): ?><span class="mini-badge">VIP</span><?php endif; ?>
                    <?php if ($c['has_4dx']): ?><span class="mini-badge">4DX</span><?php endif; ?>
                </div>
                <?php if ($c['next_showtime']): ?>
                    <span class="cinema-card-next"><?= date('g:i a', strtotime($c['next_showtime'])) ?></span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ════════════════════ UPCOMING RELEASES ════════════════════ -->
<?php if (!empty($upcoming)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Coming Soon</h2>
    </div>
    <div class="poster-wall stagger">
        <?php foreach ($upcoming as $m): ?>
        <a href="<?= e_link('/movies/' . rawurlencode($m['slug'])) ?>" class="wall-card">
            <?php if ($m['poster_url']): ?>
                <img src="<?= htmlspecialchars($m['poster_url']) ?>" alt="<?= htmlspecialchars($m['title']) ?>" loading="lazy">
            <?php else: ?>
                <div style="width:100%;aspect-ratio:2/3;background:var(--card);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:2rem;">
                    <?= htmlspecialchars(substr($m['title'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div class="wall-card-info">
                <div class="wall-card-title"><?= htmlspecialchars($m['title']) ?></div>
                <?php if ($m['release_date']): ?>
                    <div class="wall-card-meta"><?= date('M j', strtotime($m['release_date'])) ?></div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- AD PLACEMENT 4: Footer leaderboard -->
<?php renderAd('leaderboard', 'ad-mt-6 ad-mb-2'); ?>

</div><!-- end page-enter -->

<?php include __DIR__ . '/includes/footer.php'; ?>
