<?php
require_once __DIR__ . '/../config.php';

$db = getDbConnection();

$stmt = $db->prepare("
    SELECT
        c.id, c.name, c.slug, c.city, c.area,
        c.has_imax, c.has_vip, c.has_4dx,
        ch.id AS chain_id, ch.name AS chain_name, ch.slug AS chain_slug, ch.color_hex,
        COUNT(DISTINCT s.movie_id) AS movies_today
    FROM cinemas c
    JOIN chains ch ON c.chain_id = ch.id
    LEFT JOIN showtimes s ON s.cinema_id = c.id AND s.show_date = CURDATE()
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY ch.name, c.name
");
$stmt->execute();
$cinemaRows = $stmt->fetchAll() ?: [];

$byChain = [];
$cities = [];
foreach ($cinemaRows as $row) {
    $chainId = $row['chain_id'];
    if (!isset($byChain[$chainId])) {
        $byChain[$chainId] = [
            'chain_name' => $row['chain_name'],
            'chain_slug' => $row['chain_slug'],
            'color_hex' => $row['color_hex'],
            'cinemas' => [],
        ];
    }
    $byChain[$chainId]['cinemas'][] = $row;
    $cities[$row['city']] = true;
}

$canonical = '/cinemas';
$pageTitle = 'Cinemas in Lebanon | ' . SITE_NAME;
$pageDescription = 'Find cinemas in Lebanon including VOX, Grand, Empire, CinemaCity, Cinemall and more. Open each cinema page for today\'s movies, facilities, and showtime links.';
$pageUpdatedAt = date('Y-m-d H:i:s');
$breadcrumbs = [['pos' => 2, 'name' => 'Cinemas', 'url' => '/cinemas']];
$jsonLd = [[
    '@type' => 'CollectionPage',
    '@id' => url('/cinemas') . '#page',
    'name' => $pageTitle,
    'description' => $pageDescription,
    'isPartOf' => ['@id' => rtrim(SITE_URL, '/') . '/#website'],
], [
    '@type' => 'FAQPage',
    'mainEntity' => [
        [
            '@type' => 'Question',
            'name' => 'Which cinemas are active in Lebanon?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => count($cinemaRows) . ' active cinema locations are listed on LebanonCinema across chains such as VOX, Grand, Empire, CinemaCity, and Cinemall.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'How do I compare cinema showtimes in Lebanon?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Open any cinema page to see its next seven days of movie sessions, formats, and direct links into movie details.',
            ],
        ],
    ],
]];

require_once __DIR__ . '/includes/ad.php';
include __DIR__ . '/includes/header.php';
?>
<div class="page-enter">
    <div class="section-header" style="margin-bottom:12px;padding-top:24px;">
        <h1 class="section-title" style="font-size:2rem;">Cinemas in Lebanon</h1>
        <?php if (!empty($cinemaRows)): ?>
            <span class="section-link"><?= count($cinemaRows) ?> locations</span>
        <?php endif; ?>
    </div>
    <div class="seo-summary" style="padding:0 24px 16px;">
        <p style="color:var(--text-muted);max-width:920px;line-height:1.7;">
            Compare cinema locations across Lebanon by chain, city, and facility. Each cinema page links into today's movie lineup, the seven-day schedule, and related city showtime pages. <?= htmlspecialchars(seo_updated_label($pageUpdatedAt)) ?>.
        </p>
        <p style="color:var(--text-muted);font-size:0.9rem;margin-top:10px;">
            What you'll find on this page: cinema chain groupings, deep links to each location, and paths into city and movie pages.
        </p>
    </div>

    <?php if (empty($byChain)): ?>
        <div class="empty-state"><p>No cinemas found.</p></div>
    <?php else: ?>
        <?php $chainIdx = 0; ?>
        <?php foreach ($byChain as $chain): $chainIdx++; ?>
        <div style="margin-bottom:40px;">
            <div style="display:flex;align-items:center;gap:12px;padding:0 24px;margin-bottom:16px;">
                <div style="width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($chain['color_hex']) ?>;flex-shrink:0;"></div>
                <h2 style="font-size:1.1rem;font-weight:600;font-family:'Space Grotesk',sans-serif;"><?= htmlspecialchars($chain['chain_name']) ?></h2>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;padding:0 24px;">
                <?php foreach ($chain['cinemas'] as $cinema): ?>
                <a href="<?= e_link('/cinemas/' . rawurlencode($cinema['slug'])) ?>" class="cinema-card" style="width:100%;">
                    <div class="cinema-card-top">
                        <div class="cinema-dot" style="background:<?= htmlspecialchars($chain['color_hex']) ?>"></div>
                        <div class="cinema-card-name"><?= htmlspecialchars($cinema['name']) ?></div>
                        <div style="font-size:0.8rem;font-weight:700;color:var(--accent);"><?= (int) $cinema['movies_today'] ?></div>
                    </div>
                    <div class="cinema-card-detail">
                        <?= htmlspecialchars($cinema['city']) ?><?= $cinema['area'] ? ', ' . htmlspecialchars($cinema['area']) : '' ?>
                    </div>
                    <div class="cinema-card-footer">
                        <div class="cinema-card-badges">
                            <?php if ($cinema['has_imax']): ?><span class="mini-badge">IMAX</span><?php endif; ?>
                            <?php if ($cinema['has_vip']): ?><span class="mini-badge">VIP</span><?php endif; ?>
                            <?php if ($cinema['has_4dx']): ?><span class="mini-badge">4DX</span><?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($chainIdx % 2 === 0 && $chainIdx < count($byChain)): ?>
            <?php renderAd('rectangle', ['placement' => 'cinemas_chain_inline']); ?>
        <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($cities)): ?>
    <section class="section" style="padding:0 24px 24px;">
        <h2 class="section-title" style="margin-bottom:16px;">Browse Cinemas by City</h2>
        <div class="genre-strip">
            <?php foreach (array_slice(array_keys($cities), 0, 8) as $city): ?>
            <a href="<?= e_link('/cities/' . rawurlencode(city_slug($city))) ?>" class="genre-pill"><?= htmlspecialchars($city) ?></a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
