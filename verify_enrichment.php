<?php
require_once 'config.php';

$db = getDbConnection();

echo "=== ✓ Movie Enrichment Complete ===\n\n";

$stmt = $db->query('SELECT title, tmdb_id, synopsis, poster_url, trailer_url, genres FROM movies ORDER BY title');
$movies = $stmt->fetchAll();

foreach ($movies as $m) {
    echo strtoupper($m['title']) . "\n";
    echo str_repeat("─", strlen($m['title'])) . "\n";
    echo "TMDB ID:       " . $m['tmdb_id'] . "\n";
    echo "Poster:        " . (!empty($m['poster_url']) ? '✓ ' . $m['poster_url'] : '✗ None') . "\n";
    echo "Trailer:       " . (!empty($m['trailer_url']) ? '✓ ' . $m['trailer_url'] : '✗ None') . "\n";
    echo "Genres:        " . $m['genres'] . "\n";
    echo "Synopsis:      " . substr($m['synopsis'], 0, 70) . "...\n";
    echo "\n";
}

echo "\n=== Database Statistics ===\n";
$stmt = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN poster_url IS NOT NULL THEN 1 ELSE 0 END) as with_posters,
    SUM(CASE WHEN trailer_url IS NOT NULL THEN 1 ELSE 0 END) as with_trailers,
    SUM(CASE WHEN synopsis IS NOT NULL THEN 1 ELSE 0 END) as with_synopsis
FROM movies");

$stats = $stmt->fetch();
echo "Total Movies:        " . $stats['total'] . "\n";
echo "With Posters:        " . $stats['with_posters'] . " (" . round($stats['with_posters'] / $stats['total'] * 100) . "%)\n";
echo "With Trailers:       " . $stats['with_trailers'] . " (" . round($stats['with_trailers'] / $stats['total'] * 100) . "%)\n";
echo "With Synopses:       " . $stats['with_synopsis'] . " (" . round($stats['with_synopsis'] / $stats['total'] * 100) . "%)\n";

echo "\n=== ✅ Ready to View ===\n";
echo "Open in browser: http://localhost/lebanoncinema/public/\n";
