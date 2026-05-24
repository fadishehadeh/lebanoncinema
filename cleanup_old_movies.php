<?php
require_once 'config.php';

$db = getDbConnection();

echo "=== Cleaning Up Old Sample Movies ===\n\n";

// Old sample movies to remove
$oldMovies = [
    'avatar',
    'inception',
    'interstellar',
    'the-dark-knight',
    'the-matrix'
];

echo "Removing old sample movies:\n";

foreach ($oldMovies as $slug) {
    // Get movie ID
    $stmt = $db->prepare("SELECT id FROM movies WHERE slug = ?");
    $stmt->execute([$slug]);
    $movieId = $stmt->fetchColumn();

    if ($movieId) {
        // Delete showtimes first (foreign key)
        $stmt = $db->prepare("DELETE FROM showtimes WHERE movie_id = ?");
        $stmt->execute([$movieId]);

        // Delete movie
        $stmt = $db->prepare("DELETE FROM movies WHERE id = ?");
        $stmt->execute([$movieId]);

        echo "  ✗ Deleted: $slug\n";
    }
}

echo "\n=== Database After Cleanup ===\n\n";

// Show remaining movies
$stmt = $db->query("
    SELECT title, tmdb_id, poster_url,
           COUNT(DISTINCT CONCAT(show_date, show_time)) as times_count
    FROM movies m
    LEFT JOIN showtimes s ON m.id = s.movie_id AND s.show_date >= CURDATE()
    WHERE m.status = 'now_showing'
    GROUP BY m.id
    ORDER BY m.id DESC
");

$movies = $stmt->fetchAll();

echo "Remaining movies (" . count($movies) . "):\n\n";

foreach ($movies as $m) {
    $poster = !empty($m['poster_url']) ? "✓" : "✗";
    echo "  $poster " . $m['title'] . " (Showtimes: {$m['times_count']})\n";
}

// Count totals
$stmt = $db->query("SELECT COUNT(*) FROM movies WHERE status = 'now_showing'");
$totalMovies = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM showtimes WHERE show_date >= CURDATE()");
$totalShowtimes = $stmt->fetchColumn();

echo "\n=== Summary ===\n";
echo "Total movies: $totalMovies\n";
echo "Upcoming showtimes: $totalShowtimes\n";
echo "\n✅ Cleaned! Now only REAL VOX movies.\n";
