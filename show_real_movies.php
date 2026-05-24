<?php
require_once 'config.php';

$db = getDbConnection();

echo "=== REAL Current Movies Now in Database ===\n\n";

$stmt = $db->query("
    SELECT title, tmdb_id, poster_url, genres,
           COUNT(DISTINCT CONCAT(show_date, show_time)) as times_count
    FROM movies m
    LEFT JOIN showtimes s ON m.id = s.movie_id AND s.show_date >= CURDATE()
    WHERE m.status = 'now_showing'
    GROUP BY m.id
    ORDER BY m.id DESC
    LIMIT 20
");

$movies = $stmt->fetchAll();

foreach ($movies as $m) {
    echo strtoupper($m['title']) . "\n";
    echo "TMDB ID: " . ($m['tmdb_id'] ?? '—') . "\n";
    echo "Poster: " . (!empty($m['poster_url']) ? "✓ YES" : "✗ No") . "\n";
    echo "Genres: " . ($m['genres'] ?? "—") . "\n";
    echo "Showtimes: " . $m['times_count'] . "\n";
    echo "\n";
}

echo "=== Summary ===\n";
$stmt = $db->query("SELECT COUNT(*) FROM movies WHERE status = 'now_showing'");
$total = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM showtimes WHERE show_date >= CURDATE()");
$upcoming = $stmt->fetchColumn();

echo "Total movies: $total\n";
echo "Upcoming showtimes: $upcoming\n";
echo "\n✅ Website updated with REAL current movies!\n";
echo "Visit: http://localhost/lebanoncinema/public/\n";
