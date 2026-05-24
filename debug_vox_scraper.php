<?php
$date = date('Ymd');
$url = "https://lbn.voxcinemas.com/showtimes?d=$date";

echo "Fetching VOX Lebanon showtimes...\n";
echo "URL: $url\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ],
    CURLOPT_ENCODING       => '',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $code\n";
echo "HTML Size: " . strlen($html) . " bytes\n\n";

if ($code === 200 && $html) {
    // Check for JSON-LD
    if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches)) {
        echo "Found " . count($matches[1]) . " JSON-LD blocks\n\n";

        foreach ($matches[1] as $i => $json) {
            $data = json_decode(trim($json), true);
            if ($data) {
                echo "Block #" . ($i + 1) . ": ";
                if (isset($data['@type'])) {
                    echo $data['@type'];
                } elseif (isset($data['@graph'])) {
                    echo "Graph with " . count($data['@graph']) . " items";
                }
                echo "\n";
            }
        }
    } else {
        echo "No JSON-LD blocks found\n\n";
    }

    // Check page structure
    echo "\nPage contains:\n";
    echo "- <body>: " . (strpos($html, '<body') !== false ? 'YES' : 'NO') . "\n";
    echo "- 'movie': " . (stripos($html, 'movie') !== false ? 'YES' : 'NO') . "\n";
    echo "- 'showtime': " . (stripos($html, 'showtime') !== false ? 'YES' : 'NO') . "\n";
    echo "- 'cinema': " . (stripos($html, 'cinema') !== false ? 'YES' : 'NO') . "\n";
    echo "- 'booking': " . (stripos($html, 'booking') !== false ? 'YES' : 'NO') . "\n";

    // Show first 2000 chars
    echo "\n=== First 2000 chars of HTML ===\n";
    echo substr($html, 0, 2000) . "\n";
} else {
    echo "Failed to fetch page\n";
}
