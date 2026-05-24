<?php
echo "=== Fetching REAL Current Movies from VOX Lebanon ===\n\n";

$date = date('Ymd');
$url = "https://lbn.voxcinemas.com/showtimes?d=$date";

echo "URL: $url\n\n";

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
curl_close($ch);

// Save for inspection
file_put_contents('vox_page_raw.html', $html);
echo "Saved full page: vox_page_raw.html (" . strlen($html) . " bytes)\n\n";

echo "=== Analyzing HTML Structure ===\n\n";

// Look for movie titles in heading tags
preg_match_all('/<h[1-6][^>]*>([^<]{5,100})<\/h[1-6]>/i', $html, $headings);

echo "Found " . count($headings[1]) . " headings\n";
echo "First 20 headings:\n";

$count = 0;
foreach ($headings[1] as $heading) {
    $text = trim($heading);
    if (strlen($text) > 3 && !in_array(strtolower($text), ['cinema', 'vox', 'menu', 'nav', 'footer', 'button'])) {
        echo "  - " . substr($text, 0, 60) . "\n";
        $count++;
        if ($count >= 20) break;
    }
}

echo "\n=== Looking for Movie-Related Data ===\n\n";

// Look for common movie data patterns
$patterns = [
    'movie' => preg_match_all('/movie["\']?\s*:\s*["\']?([^"\',\}]{5,80})/i', $html, $m1) ? $m1[1] : [],
    'title' => preg_match_all('/"title"\s*:\s*"([^"]{5,80})"/i', $html, $m2) ? $m2[1] : [],
    'name' => preg_match_all('/"name"\s*:\s*"([^"]{5,80})"/i', $html, $m3) ? $m3[1] : [],
    'data-title' => preg_match_all('/data-title=["\']([^"\']{5,80})/i', $html, $m4) ? $m4[1] : [],
];

foreach ($patterns as $key => $values) {
    if (!empty($values)) {
        echo "Found via \"$key\": " . count($values) . " items\n";
        foreach (array_unique(array_slice($values, 0, 5)) as $val) {
            echo "  - " . substr($val, 0, 60) . "\n";
        }
        echo "\n";
    }
}

echo "=== Page Keywords ===\n\n";
$keywords = [
    'showtimes' => substr_count($html, 'showtime'),
    'cinema' => substr_count($html, 'cinema'),
    'booking' => substr_count($html, 'booking'),
    'movie' => substr_count($html, 'movie'),
    'time' => substr_count($html, 'time'),
];

foreach ($keywords as $word => $count) {
    echo "$word: $count occurrences\n";
}

echo "\n💡 Recommendation: Check vox_page_raw.html to see actual page structure\n";
