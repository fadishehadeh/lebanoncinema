<?php
require_once __DIR__ . '/../config.php';

$db = getDbConnection();

function getMinutesUntil(string $timeStr): int {
    $now = time();
    $ts = strtotime(date('Y-m-d') . ' ' . $timeStr);
    return (int) (($ts - $now) / 60);
}

function urgencyLabel(int $mins): ?array {
    if ($mins < 0) {
        return null;
    }
    if ($mins <= 30) {
        return ['class' => 'accent', 'label' => "Starting in {$mins} min"];
    }
    if ($mins <= 90) {
        return ['class' => 'orange', 'label' => 'Starting in ' . ceil($mins / 60) . 'h'];
    }
    return null;
}

$stmt = $db->prepare("
    SELECT m.*, COUNT(s.id) AS showtime_count,
           GROUP_CONCAT(DISTINCT s.show_time ORDER BY s.show_time SEPARATOR ',') AS hero_times
    FROM movies m
    JOIN showtimes s ON s.movie_id = m.id AND s.show_date = CURDATE()
    WHERE m.is_showing = 1
    GROUP BY m.id
    ORDER BY showtime_count DESC
    LIMIT 5
");
$stmt->execute();
$heroMovies = array_map('mapMovieRow', $stmt->fetchAll() ?? []);

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

$stmt = $db->prepare("
    SELECT * FROM movies
    WHERE status = 'coming_soon'
    ORDER BY release_date DESC
    LIMIT 12
");
$stmt->execute();
$upcoming = array_map('mapMovieRow', $stmt->fetchAll() ?? []);

$pageTitle = 'Movies Showing Today in Lebanon - ' . SITE_NAME;
$pageDescription = 'Find movies playing today at cinemas across Lebanon. Browse showtimes for VOX, Grand, Empire, CinemaCity and more. Watch trailers, check schedules, and book cinema tickets online.';
$showSkeleton = true;
$breadcrumbs = [];
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/ad.php';
?>

<div class="page-enter">
<?php if (!empty($heroMovies)): ?>
<section class="hero" id="hero-carousel">
    <?php foreach ($heroMovies as $i => $movie): ?>
    <article class="hero-slide <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>">
        <div class="hero-backdrop">
            <?php $bg = $movie['backdrop'] ?: $movie['poster_url']; ?>
            <?php if ($bg): ?>
                <img src="<?= htmlspecialchars($bg) ?>" alt="">
            <?php endif; ?>
        </div>
        <div class="hero-content fade-up">
            <div class="hero-stage">
                <div class="hero-poster">
                    <?php if ($movie['poster_url']): ?>
                        <img src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="<?= htmlspecialchars($movie['title']) ?>">
                    <?php else: ?>
                        <div class="hero-poster-fallback"><?= htmlspecialchars(substr($movie['title'], 0, 1)) ?></div>
                    <?php endif; ?>
                </div>
                <div class="hero-info">
                    <div class="hero-badge">
                        <i data-lucide="clapperboard" style="width:14px;height:14px;"></i>
                        Featured Tonight
                    </div>
                    <h1 class="hero-title"><?= htmlspecialchars($movie['title']) ?></h1>
                    <?php if (!empty($movie['synopsis'])): ?>
                        <p class="hero-tagline"><?= htmlspecialchars(substr($movie['synopsis'], 0, 150)) ?></p>
                    <?php else: ?>
                        <p class="hero-tagline">Browse what is playing today, compare showtimes, and pick a cinema without digging through clutter.</p>
                    <?php endif; ?>
                    <div class="hero-meta">
                        <?php if (!empty($movie['release_date'])): ?><span><?= date('Y', strtotime($movie['release_date'])) ?></span><?php endif; ?>
                        <?php if (!empty($movie['duration_min'])): ?><span><?= (int) $movie['duration_min'] ?> min</span><?php endif; ?>
                        <?php if (!empty($movie['rating'])): ?><span>Rating <?= htmlspecialchars($movie['rating']) ?></span><?php endif; ?>
                        <?php if (!empty($movie['genres'])): ?><span><?= htmlspecialchars(substr($movie['genres'], 0, 24)) ?></span><?php endif; ?>
                    </div>
                    <div class="hero-ctas">
                        <a href="<?= e_link('/movies/' . rawurlencode($movie['slug'])) ?>" class="btn btn-primary">
                            <i data-lucide="ticket"></i>
                            View Showtimes
                        </a>
                        <a href="<?= e_link('/movies/' . rawurlencode($movie['slug'])) ?>" class="btn btn-outline">
                            <i data-lucide="<?= !empty($movie['trailer_url']) ? 'play' : 'film' ?>"></i>
                            <?= !empty($movie['trailer_url']) ? 'Watch Trailer' : 'Movie Details' ?>
                        </a>
                    </div>
                    <?php if (!empty($movie['hero_times'])): ?>
                    <div class="hero-quick-times">
                        <span class="label">Today</span>
                        <?php
                        $times = explode(',', $movie['hero_times']);
                        $shown = 0;
                        foreach ($times as $time):
                            if ($shown >= 4) {
                                break;
                            }
                            $mins = getMinutesUntil($time);
                            if ($mins < -60) {
                                continue;
                            }
                            $shown++;
                        ?>
                            <span class="hero-time-chip"><?= date('g:i a', strtotime($time)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (count($heroMovies) > 1): ?>
            <div class="hero-queue" aria-label="More featured movies">
                <?php foreach ($heroMovies as $queueIndex => $queueMovie): ?>
                <button class="hero-queue-item <?= $queueIndex === 0 ? 'active' : '' ?>" data-slide="<?= $queueIndex ?>" type="button">
                    <span class="hero-queue-rank"><?= str_pad((string) ($queueIndex + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <span class="hero-queue-copy">
                        <span class="hero-queue-title"><?= htmlspecialchars($queueMovie['title']) ?></span>
                        <span class="hero-queue-meta">
                            <?= !empty($queueMovie['genres']) ? htmlspecialchars(substr($queueMovie['genres'], 0, 24)) : 'Showing today' ?>
                        </span>
                    </span>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </article>
    <?php endforeach; ?>
    <div class="hero-dots">
        <?php foreach ($heroMovies as $i => $movie): ?>
        <button class="hero-dot <?= $i === 0 ? 'active' : '' ?>" data-slide="<?= $i ?>" aria-label="Slide <?= $i + 1 ?>"></button>
        <?php endforeach; ?>
    </div>
</section>
<script>
(function() {
    var slides = document.querySelectorAll('#hero-carousel .hero-slide');
    var dots = document.querySelectorAll('#hero-carousel .hero-dot');
    var queueItems = document.querySelectorAll('#hero-carousel .hero-queue-item');
    if (!slides.length) return;
    var current = 0;
    var timer;
    function showSlide(index) {
        slides.forEach(function(slide, slideIndex) { slide.classList.toggle('active', slideIndex === index); });
        dots.forEach(function(dot, dotIndex) { dot.classList.toggle('active', dotIndex === index); });
        queueItems.forEach(function(item, itemIndex) { item.classList.toggle('active', itemIndex === index); });
        current = index;
    }
    function restartTimer() {
        clearInterval(timer);
        timer = setInterval(nextSlide, 7000);
    }
    function nextSlide() {
        showSlide((current + 1) % slides.length);
    }
    dots.forEach(function(dot) {
        dot.addEventListener('click', function() {
            showSlide(parseInt(this.getAttribute('data-slide'), 10));
            restartTimer();
        });
    });
    queueItems.forEach(function(item) {
        item.addEventListener('click', function() {
            showSlide(parseInt(this.getAttribute('data-slide'), 10));
            restartTimer();
        });
    });
    restartTimer();
})();
</script>
<?php else: ?>
<section class="hero" style="min-height:40vh;">
    <div class="hero-content" style="justify-content:center;">
        <div class="hero-search-fallback">
            <h1>What are you watching tonight?</h1>
            <p>Discover movies playing at cinemas across Lebanon.</p>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="home-discovery-shell">
    <div class="search-section search-section-home">
        <div class="search-section-copy">
            <span class="section-kicker">Start Fast</span>
            <h2>Search a movie, cinema, or genre</h2>
            <p>Jump straight into tonight's lineup or narrow the page by format and mood.</p>
        </div>
        <div class="search-wrap">
            <input class="search-input" id="hero-search" placeholder="Search movies, cinemas, genres..." autocomplete="off">
            <div class="search-icon"><i data-lucide="search"></i></div>
            <div class="search-dropdown" id="search-dropdown"></div>
        </div>
        <div class="chips-row">
            <button class="chip active" data-filter="all">All Movies</button>
            <button class="chip" data-filter="vip">VIP & IMAX</button>
            <button class="chip" data-filter="action">Action</button>
            <button class="chip" data-filter="comedy">Comedy</button>
            <button class="chip" data-filter="horror">Horror</button>
            <button class="chip" data-filter="family">Family</button>
        </div>
    </div>
</section>

<?php renderAd('leaderboard', ['placement' => 'homepage_top']); ?>

<?php if (!empty($trending)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Trending Tonight</h2>
        <a href="<?= e_link('/movies') ?>" class="section-link">See all</a>
    </div>
    <div class="carousel-grid stagger">
        <?php foreach ($trending as $index => $movie):
            $urgency = !empty($movie['first_showtime']) ? urgencyLabel(getMinutesUntil($movie['first_showtime'])) : null;
        ?>
        <a href="<?= e_link('/movies/' . rawurlencode($movie['slug'])) ?>" class="poster-card"
           data-movie-item
           data-genres="<?= htmlspecialchars(strtolower($movie['genres'] ?? '')) ?>"
           data-formats="<?= ($movie['has_imax'] ? 'imax,' : '') . ($movie['has_vip'] ? 'vip,' : '') ?>"
           data-urgency="<?= !empty($movie['first_showtime']) ? getMinutesUntil($movie['first_showtime']) : -1 ?>">
            <?php if (!empty($movie['poster_url'])): ?>
                <img class="poster-card-img" src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="<?= htmlspecialchars($movie['title']) ?>" loading="lazy">
            <?php else: ?>
                <div class="poster-card-img poster-card-fallback"><?= htmlspecialchars(substr($movie['title'], 0, 1)) ?></div>
            <?php endif; ?>
            <div class="poster-card-overlay">
                <div class="poster-card-title"><?= htmlspecialchars($movie['title']) ?></div>
                <div class="poster-card-meta">
                    <?php if (!empty($movie['genres'])): ?><?= htmlspecialchars(substr($movie['genres'], 0, 30)) ?><?php endif; ?>
                    <?php if (!empty($movie['duration_min'])): ?><?= !empty($movie['genres']) ? ' • ' : '' ?><?= (int) $movie['duration_min'] ?> min<?php endif; ?>
                </div>
            </div>
            <?php if ($urgency): ?>
                <span class="poster-card-badge <?= $urgency['class'] ?>"><?= htmlspecialchars($urgency['label']) ?></span>
            <?php elseif (!empty($movie['rating'])): ?>
                <span class="poster-card-badge accent">Rated <?= htmlspecialchars($movie['rating']) ?></span>
            <?php endif; ?>
        </a>
        <?php if ($index === 5): ?>
            <?php renderAd('large-rectangle', ['placement' => 'homepage_grid_inline']); ?>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php renderAd('large-rectangle', ['placement' => 'homepage_trending_inline']); ?>

<?php renderAd('leaderboard', ['placement' => 'homepage_cinemas_break']); ?>

<?php if (!empty($cinemas)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Cinemas Near You</h2>
        <a href="<?= e_link('/cinemas') ?>" class="section-link">See all</a>
    </div>
    <div class="cinema-scroll stagger">
        <?php foreach ($cinemas as $cinema): ?>
        <a href="<?= e_link('/cinemas/' . rawurlencode($cinema['slug'])) ?>" class="cinema-card">
            <div class="cinema-card-top">
                <div>
                    <div class="cinema-card-chain">
                        <span class="cinema-dot" style="background:<?= htmlspecialchars($cinema['color_hex']) ?>"></span>
                        <?= htmlspecialchars($cinema['chain_name']) ?>
                    </div>
                    <div class="cinema-card-name"><?= htmlspecialchars($cinema['name']) ?></div>
                </div>
                <?php if (!empty($cinema['next_showtime'])): ?>
                    <span class="cinema-card-next">Next <?= date('g:i a', strtotime($cinema['next_showtime'])) ?></span>
                <?php endif; ?>
            </div>
            <div class="cinema-card-detail">
                <?= htmlspecialchars(trim(($cinema['area'] ?: '') . ($cinema['city'] ? ', ' . $cinema['city'] : ''))) ?>
            </div>
            <div class="cinema-card-stats">
                <div>
                    <span class="cinema-stat-value"><?= (int) ($cinema['movies_today'] ?? 0) ?></span>
                    <span class="cinema-stat-label">movies today</span>
                </div>
                <div class="cinema-card-badges">
                    <?php if (!empty($cinema['has_imax'])): ?><span class="mini-badge">IMAX</span><?php endif; ?>
                    <?php if (!empty($cinema['has_vip'])): ?><span class="mini-badge">VIP</span><?php endif; ?>
                    <?php if (!empty($cinema['has_4dx'])): ?><span class="mini-badge">4DX</span><?php endif; ?>
                </div>
            </div>
            <div class="cinema-card-footer">
                <span>Browse showtimes</span>
                <i data-lucide="arrow-up-right"></i>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($upcoming)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Showing Soon</h2>
        <a href="<?= e_link('/movies') ?>" class="section-link">Browse movies</a>
    </div>
    <div class="poster-wall stagger">
        <?php foreach ($upcoming as $index => $movie): ?>
        <a href="<?= e_link('/movies/' . rawurlencode($movie['slug'])) ?>" class="wall-card">
            <?php if (!empty($movie['poster_url'])): ?>
                <img src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="<?= htmlspecialchars($movie['title']) ?>" loading="lazy">
            <?php else: ?>
                <div class="wall-card-fallback"><?= htmlspecialchars(substr($movie['title'], 0, 1)) ?></div>
            <?php endif; ?>
            <div class="wall-card-info">
                <div class="wall-card-title"><?= htmlspecialchars($movie['title']) ?></div>
                <?php if (!empty($movie['release_date'])): ?>
                    <div class="wall-card-meta"><?= date('M j', strtotime($movie['release_date'])) ?></div>
                <?php endif; ?>
            </div>
        </a>
        <?php if ($index === 5): ?>
            <?php renderAd('large-rectangle', ['placement' => 'homepage_grid_inline']); ?>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php renderAd('leaderboard', ['placement' => 'homepage_footer']); ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
