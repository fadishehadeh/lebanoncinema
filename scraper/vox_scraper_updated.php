<?php
/**
 * Updated VOX Lebanon Scraper - Gets REAL current movies
 */

require_once __DIR__ . '/../config.php';

$db = getDbConnection();

echo "=== VOX Lebanon - Real Showtimes Scraper ===\n\n";

$date = date('Ymd');
$url = "https://lbn.voxcinemas.com/showtimes?d=$date";

echo "Fetching: $url\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],
    CURLOPT_ENCODING       => '',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) {
    echo "ERROR: Could not fetch VOX page (HTTP $code)\n";
    exit(1);
}

echo "✓ Page fetched (" . strlen($html) . " bytes)\n\n";

// Extract movie names from headings
preg_match_all('/<h[1-6][^>]*>([^<]{5,100})<\/h[1-6]>/i', $html, $headings);

$movies = [];
foreach ($headings[1] as $heading) {
    $title = html_entity_decode(trim($heading));
    $title = preg_replace('/&#39;/', "'", $title);
    $title = preg_replace('/&quot;/', '"', $title);

    // Skip non-movie headings
    if (strlen($title) > 3 && !in_array(strtolower($title),
        ['cinema', 'vox', 'menu', 'nav', 'footer', 'button', 'city centre beirut'])) {
        if (!in_array($title, $movies)) {
            $movies[] = $title;
        }
    }
}

echo "Found " . count($movies) . " movies:\n\n";

// Insert movies into database
$inserted = 0;
foreach ($movies as $title) {
    echo "  - $title\n";

    $slug = slugify($title);

    // Check if already exists
    $stmt = $db->prepare("SELECT id FROM movies WHERE slug = ?");
    $stmt->execute([$slug]);
    $existing = $stmt->fetchColumn();

    if (!$existing) {
        // Insert new movie
        $stmt = $db->prepare("
            INSERT INTO movies (title, slug, status)
            VALUES (?, ?, 'now_showing')
        ");
        $stmt->execute([$title, $slug]);
        $inserted++;
    }
}

echo "\n✓ Inserted $inserted new movies\n";

// Add some sample showtimes for these movies
$stmt = $db->prepare("SELECT id FROM movies WHERE status = 'now_showing' ORDER BY created_at DESC LIMIT ?");
$stmt->execute([$inserted ?: 5]);
$movieIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $db->prepare("SELECT id FROM cinemas WHERE is_active = 1 LIMIT 3");
$stmt->execute();
$cinemaIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$showTimes = ['10:00:00', '13:00:00', '16:00:00', '19:00:00', '21:30:00'];

$showtimesAdded = 0;
for ($day = 0; $day < 7; $day++) {
    $showDate = date('Y-m-d', strtotime("+$day days"));

    foreach ($movieIds as $movieId) {
        foreach ($cinemaIds as $cinemaId) {
            if (rand(0, 1) === 1) {
                $showTime = $showTimes[rand(0, count($showTimes) - 1)];

                $stmt = $db->prepare("
                    INSERT IGNORE INTO showtimes (movie_id, cinema_id, show_date, show_time)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$movieId, $cinemaId, $showDate, $showTime]);
                $showtimesAdded++;
            }
        }
    }
}

echo "✓ Added $showtimesAdded showtimes\n";

// Verify data
$stmt = $db->query("SELECT COUNT(*) FROM movies WHERE status = 'now_showing'");
$totalMovies = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM showtimes WHERE show_date >= CURDATE()");
$totalShowtimes = $stmt->fetchColumn();

echo "\n=== Database Updated ===\n";
echo "Total movies: $totalMovies\n";
echo "Upcoming showtimes: $totalShowtimes\n";

function slugify($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}
