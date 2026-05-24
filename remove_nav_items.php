<?php
require_once 'config.php';

$db = getDbConnection();

echo "=== Removing Website Navigation Items ===\n\n";

// These are navigation/footer items, not movies
$navItems = [
    'download-our-mobile-app',
    'explore-our-site',
    'help-support',
    'stay-in-touch'
];

echo "Removing nav items:\n";

foreach ($navItems as $slug) {
    $stmt = $db->prepare("SELECT id FROM movies WHERE slug = ?");
    $stmt->execute([$slug]);
    $movieId = $stmt->fetchColumn();

    if ($movieId) {
        $stmt = $db->prepare("DELETE FROM showtimes WHERE movie_id = ?");
        $stmt->execute([$movieId]);

        $stmt = $db->prepare("DELETE FROM movies WHERE id = ?");
        $stmt->execute([$movieId]);

        echo "  ✗ Deleted: $slug\n";
    }
}

echo "\n=== REAL MOVIES NOW ===\n\n";

$stmt = $db->query("
    SELECT title, genres, poster_url,
           COUNT(DISTINCT CONCAT(show_date, show_time)) as showtimes
    FROM movies m
    LEFT JOIN showtimes s ON m.id = s.movie_id AND s.show_date >= CURDATE()
    WHERE m.status = 'now_showing'
    GROUP BY m.id
    ORDER BY m.title ASC
");

$movies = $stmt->fetchAll();

foreach ($movies as $m) {
    $poster = !empty($m['poster_url']) ? "✓ POSTER" : "✗";
    echo $m['title'] . "\n";
    echo "  Genres: " . ($m['genres'] ?? 'TBD') . "\n";
    echo "  Status: $poster | Showtimes: {$m['showtimes']}\n\n";
}

$stmt = $db->query("SELECT COUNT(*) FROM movies WHERE status = 'now_showing'");
echo "Total: " . $stmt->fetchColumn() . " movies\n";

echo "\n✅ Perfect! Only REAL movies now.\n";
echo "Visit: http://localhost/lebanoncinema/public/\n";
