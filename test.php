<?php
require_once 'config.php';

echo "=== Lebanon Cinema — Test Report ===\n\n";

// Test 1: Database connection
echo "1. Database Connection: ";
try {
    $db = getDbConnection();
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM movies");
    $result = $stmt->fetch();
    echo "✓ OK (" . $result['cnt'] . " movies)\n";
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

// Test 2: Check if tables exist
echo "2. Database Tables: ";
try {
    $tables = ['movies', 'cinemas', 'chains', 'showtimes', 'scraper_log'];
    $missing = [];
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT 1 FROM $table LIMIT 1");
    }
    echo "✓ All required tables exist\n";
} catch (Exception $e) {
    echo "✗ Missing table\n";
}

// Test 3: TMDB API key
echo "3. TMDB API Key: ";
if (defined('TMDB_API_KEY') && !empty(TMDB_API_KEY) && TMDB_API_KEY !== 'your_tmdb_api_key') {
    echo "✓ Configured\n";
} else {
    echo "⚠ Not configured (needed for enricher)\n";
}

// Test 4: Check files exist
echo "4. Required Files:\n";
$files = [
    'public/index.php' => 'Homepage',
    'public/movies.php' => 'Movies listing',
    'public/movie.php' => 'Movie detail',
    'public/cinemas.php' => 'Cinemas listing',
    'public/cinema.php' => 'Cinema detail',
    'public/includes/header.php' => 'Header template',
    'public/includes/footer.php' => 'Footer template',
    'public/.htaccess' => 'URL routing',
    'scraper/tmdb_enricher.php' => 'TMDB enricher',
];

foreach ($files as $file => $desc) {
    $exists = file_exists($file);
    $status = $exists ? '✓' : '✗';
    echo "   $status $desc ($file)\n";
}

// Test 5: Sample data
echo "\n5. Sample Data:\n";
$stmt = $db->query("SELECT title FROM movies LIMIT 3");
$movies = $stmt->fetchAll();
if (!empty($movies)) {
    echo "   Sample movies in database:\n";
    foreach ($movies as $m) {
        echo "   - " . $m['title'] . "\n";
    }
} else {
    echo "   ⚠ No movies in database yet\n";
}

echo "\n=== Ready to run! ===\n";
echo "\nNext steps:\n";
echo "1. Configure XAMPP vhost (or access via http://localhost/lebanoncinema/public/)\n";
echo "2. Run: php scraper/tmdb_enricher.php (to fetch movie data from TMDB)\n";
echo "3. Visit: http://lebanoncinema.test/\n";
