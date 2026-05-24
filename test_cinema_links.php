<?php
require_once 'config.php';

$db = getDbConnection();

echo "=== Testing Cinema Links ===\n\n";

// Get cinemas
$stmt = $db->query("SELECT id, name, slug FROM cinemas LIMIT 5");
$cinemas = $stmt->fetchAll();

echo "Cinemas in database:\n";
foreach ($cinemas as $c) {
    $slug = $c['slug'];
    $url = "/cinemas/$slug";
    echo "  - {$c['name']}\n";
    echo "    Slug: $slug\n";
    echo "    URL: $url\n";
    echo "    Test: ";

    // Try to fetch this cinema
    $stmt = $db->prepare("SELECT id, name FROM cinemas WHERE slug = ?");
    $stmt->execute([$slug]);
    $found = $stmt->fetch();

    if ($found) {
        echo "✓ Works\n\n";
    } else {
        echo "✗ NOT FOUND\n\n";
    }
}

echo "\n=== Testing Movie Cinema Links ===\n\n";

// Get a movie with showtimes
$stmt = $db->query("
    SELECT DISTINCT c.slug, c.name
    FROM showtimes s
    JOIN cinemas c ON s.cinema_id = c.id
    LIMIT 3
");

$cinemas = $stmt->fetchAll();

echo "Cinemas showing movies:\n";
foreach ($cinemas as $c) {
    echo "  - {$c['name']} → /cinemas/{$c['slug']}\n";
}
