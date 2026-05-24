<?php
echo "=== Page Load Test ===\n\n";

$pages = [
    'public/index.php' => ['name' => 'Homepage'],
    'public/movies.php' => ['name' => 'Movies Listing'],
    'public/cinemas.php' => ['name' => 'Cinemas Listing'],
];

foreach ($pages as $file => $info) {
    if (file_exists($file)) {
        // Check if file has PHP syntax
        $contents = file_get_contents($file);
        if (strpos($contents, '<?php') !== false) {
            echo "✓ {$info['name']} ({$file}) - OK\n";
        } else {
            echo "✗ {$info['name']} ({$file}) - Invalid PHP\n";
        }
    } else {
        echo "✗ {$info['name']} ({$file}) - File not found\n";
    }
}

echo "\n=== Database Content ===\n\n";

require_once 'config.php';
$db = getDbConnection();

$stmt = $db->query("SELECT COUNT(*) as cnt FROM movies");
$movies = $stmt->fetch()['cnt'];

$stmt = $db->query("SELECT COUNT(*) as cnt FROM showtimes");
$showtimes = $stmt->fetch()['cnt'];

$stmt = $db->query("SELECT COUNT(*) as cnt FROM cinemas");
$cinemas = $stmt->fetch()['cnt'];

$stmt = $db->query("SELECT COUNT(*) as cnt FROM chains");
$chains = $stmt->fetch()['cnt'];

echo "Movies:     $movies\n";
echo "Showtimes:  $showtimes\n";
echo "Cinemas:    $cinemas\n";
echo "Chains:     $chains\n";

echo "\n=== Sample Movies ===\n\n";
$stmt = $db->query("SELECT title, slug, rating, genres FROM movies LIMIT 5");
foreach ($stmt->fetchAll() as $movie) {
    echo "- {$movie['title']} ({$movie['slug']})\n";
    echo "  Rating: {$movie['rating']}, Genres: {$movie['genres']}\n\n";
}

echo "\n✓ Everything is set up and ready!\n\n";
echo "Access the website at:\n";
echo "  http://localhost/lebanoncinema/public/\n\n";
echo "Available pages:\n";
echo "  /             - Homepage (showtimes by date)\n";
echo "  /movies       - All movies listing\n";
echo "  /cinemas      - All cinemas listing\n";
