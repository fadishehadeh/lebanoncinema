<?php
$date = date('Ymd');
$url = "https://lbn.voxcinemas.com/showtimes?d=$date";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => ['User-Agent: Mozilla/5.0'],
    CURLOPT_ENCODING       => '',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$html = curl_exec($ch);
curl_close($ch);

// Extract JSON-LD
preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches);

echo "=== VOX JSON-LD Structure ===\n\n";

foreach ($matches[1] as $i => $json) {
    $data = json_decode(trim($json), true);

    if ($data) {
        echo "Block #" . ($i + 1) . ":\n";
        echo "Type: " . ($data['@type'] ?? 'N/A') . "\n";

        // If it's an itemList, parse items
        if ($data['@type'] === 'ItemList') {
            echo "Items: " . count($data['itemListElement'] ?? []) . "\n\n";

            foreach (array_slice($data['itemListElement'] ?? [], 0, 3) as $item) {
                echo "  - " . $item['name'] . " (" . $item['@type'] . ")\n";

                if ($item['@type'] === 'Movie') {
                    echo "    Image: " . ($item['image'] ?? 'N/A') . "\n";
                    echo "    Duration: " . ($item['duration'] ?? 'N/A') . "\n";
                    echo "    Rating: " . ($item['contentRating'] ?? 'N/A') . "\n";
                }
            }
        }

        // If it's a graph, show items
        if (isset($data['@graph'])) {
            echo "Graph items: " . count($data['@graph']) . "\n\n";

            $movies = array_filter($data['@graph'], fn($item) => ($item['@type'] ?? '') === 'Movie');
            echo "Movies in graph: " . count($movies) . "\n";

            if (count($movies) > 0) {
                foreach (array_slice($movies, 0, 3) as $movie) {
                    echo "  - " . $movie['name'] . "\n";
                }
            }
        }
    }
}

echo "\n=== Sample Showtimes in HTML ===\n\n";

// Look for showtime-related content
if (preg_match_all('/showtime|session|time.*\d{2}:\d{2}|movie.*showing/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
    echo "Found " . count($matches[0]) . " showtime-related matches\n";

    foreach (array_slice($matches[0], 0, 5) as $match) {
        $start = max(0, $match[1] - 50);
        $end = min(strlen($html), $match[1] + 100);
        $context = substr($html, $start, $end - $start);
        echo "\n...{$context}...\n";
    }
}

echo "\n=== Recommendation ===\n";
echo "The VOX website structure may have changed.\n";
echo "The scraper is currently looking for Movie objects in JSON-LD.\n";
echo "The actual data might be in the ItemList or rendered in JavaScript.\n";
