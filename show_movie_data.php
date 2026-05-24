<?php
require_once 'config.php';

$db = getDbConnection();

echo "=== Current Movie Data ===\n\n";

$stmt = $db->query('SELECT id, title, tmdb_id, synopsis, poster_url, genres FROM movies');
$movies = $stmt->fetchAll();

foreach ($movies as $m) {
    echo $m['title'] . ":\n";
    echo "  TMDB ID: " . ($m['tmdb_id'] ?? 'NULL') . "\n";
    echo "  Synopsis: " . (strlen($m['synopsis'] ?? '') > 40 ? substr($m['synopsis'], 0, 40) . '...' : ($m['synopsis'] ?? 'NULL')) . "\n";
    echo "  Poster: " . (!empty($m['poster_url']) ? '✓ YES' : '✗ NO') . "\n";
    echo "  Genres: " . ($m['genres'] ?? 'NULL') . "\n\n";
}

echo "\n=== Re-running enricher (clearing tmdb_id first) ===\n\n";

// Clear tmdb_id to force re-enrichment
$db->query("UPDATE movies SET tmdb_id = NULL");
echo "Cleared TMDB IDs. Running enricher...\n\n";
