<?php
require_once 'config.php';

$db = getDbConnection();

// Test TMDB search for one movie
$title = "The Dark Knight";

echo "Testing TMDB API with movie: $title\n";
echo "=====================================\n\n";

// Build search URL
$url = TMDB_BASE_URL . '/search/movie'
    . '?api_key=' . TMDB_API_KEY
    . '&query=' . urlencode($title)
    . '&language=en-US&page=1';

echo "URL: $url\n\n";

// Make the request
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);

$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $code\n\n";

if ($code === 200 && $body) {
    $data = json_decode($body, true);
    echo "Results found: " . count($data['results'] ?? []) . "\n\n";

    foreach ($data['results'] as $i => $result) {
        echo "Result #" . ($i + 1) . ":\n";
        echo "  Title: " . $result['title'] . "\n";
        echo "  Year: " . $result['release_date'] . "\n";
        echo "  TMDB ID: " . $result['id'] . "\n";
        echo "  Vote Count: " . $result['vote_count'] . "\n";
        echo "  Vote Average: " . $result['vote_average'] . "\n";
        echo "  Poster: " . ($result['poster_path'] ?? 'NULL') . "\n\n";

        if ($i >= 2) break; // Show first 3 results
    }

    // Try fetching details for the first result
    if (!empty($data['results'][0])) {
        $firstResult = $data['results'][0];
        $movieId = $firstResult['id'];

        echo "\nFetching full details for TMDB ID $movieId...\n";
        echo "=====================================\n\n";

        $detailUrl = TMDB_BASE_URL . '/movie/' . $movieId . '?api_key=' . TMDB_API_KEY;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $detailUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && $body) {
            $detail = json_decode($body, true);
            echo "✓ Details fetched:\n";
            echo "  Title: " . $detail['title'] . "\n";
            echo "  Overview: " . substr($detail['overview'], 0, 80) . "...\n";
            echo "  Runtime: " . $detail['runtime'] . " min\n";
            echo "  Release Date: " . $detail['release_date'] . "\n";
            echo "  Genres: " . implode(', ', array_column($detail['genres'], 'name')) . "\n";
            echo "  IMDB ID: " . $detail['imdb_id'] . "\n";
            echo "  Poster Path: " . $detail['poster_path'] . "\n";
        } else {
            echo "✗ Failed to fetch details (HTTP $code)\n";
        }
    }
} else {
    echo "✗ API request failed\n";
    echo "Response: " . substr($body, 0, 200) . "\n";
}
