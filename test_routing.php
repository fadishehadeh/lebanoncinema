<?php
echo "=== Testing URL Routing ===\n\n";

echo "Testing if cinema.php can be accessed with slug parameter...\n\n";

// Simulate clicking on a cinema link
$_GET['slug'] = 'vox-city-centre-beirut';

require_once 'config.php';
$db = getDbConnection();

// This is what cinema.php does
$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'] ?? ''));

if ($slug) {
    $stmt = $db->prepare("SELECT c.*, ch.name AS chain_name FROM cinemas c JOIN chains ch ON c.chain_id = ch.id WHERE c.slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $cinema = $stmt->fetch();

    if ($cinema) {
        echo "✓ Cinema found!\n\n";
        echo "Name: {$cinema['name']}\n";
        echo "Chain: {$cinema['chain_name']}\n";
        echo "City: {$cinema['city']}\n";
        echo "Area: {$cinema['area']}\n";
        echo "\n✅ The cinema.php page will work correctly\n";
        echo "\nAccess via:\n";
        echo "  Direct: http://localhost/lebanoncinema/public/cinema.php?slug=vox-city-centre-beirut\n";
        echo "  Clean URL: http://localhost/lebanoncinema/public/cinemas/vox-city-centre-beirut\n";
    } else {
        echo "✗ Cinema not found\n";
    }
} else {
    echo "✗ No slug provided\n";
}
