<?php
/**
 * Cinemall showtimes scraper.
 * Source: cinemall.com.lb
 */
require_once __DIR__ . '/ScraperService.php';

class CinemallScraper extends ScraperService {
    public function __construct(PDO $db) {
        parent::__construct($db, 'cinemall', 'Cinemall');
    }

    protected function parseShowtimes(): array {
        $results = [];
        $slug = 'cinemall';
        $url = 'https://cinemall.com.lb/showtimes';

        $html = $this->httpGet($url);
        if (!$html) {
            $this->log("  No response from Cinemall");
            return $results;
        }

        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);

        $movies = $xpath->query("//div[contains(@class, 'movie')] | //div[contains(@class, 'film')] | //tr[contains(@class, 'movie')]");
        if ($movies->length === 0) {
            $this->log("  No movie elements found — trying generic time extraction");
            $results = array_merge($results, $this->extractTimes($html, $slug));
            return $results;
        }

        foreach ($movies as $movie) {
            $titleEl = $xpath->query(".//h2 | .//h3 | .//h4 | .//*[contains(@class, 'title')] | .//strong", $movie)->item(0);
            $title = $titleEl ? $this->normalizeTitle(trim($titleEl->textContent)) : '';
            if (empty($title)) continue;

            $timeEls = $xpath->query(".//*[contains(@class, 'time')] | .//span[contains(@class, 'show')] | .//a[contains(@href, 'book')]", $movie);
            foreach ($timeEls as $el) {
                $text = trim($el->textContent);
                if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)?/i', $text, $m)) {
                    $time = $m[3] ? date('H:i', strtotime("{$m[1]}:{$m[2]} {$m[3]}")) : sprintf('%02d:%s', $m[1], $m[2]);
                    $results[] = [
                        'cinema_slug' => $slug,
                        'movie_title' => $title,
                        'show_date' => date('Y-m-d'),
                        'show_time' => $time,
                        'format' => 'Standard',
                        'booking_url' => $el->getAttribute('href') ?: null,
                    ];
                }
            }
        }
        return $results;
    }

    private function extractTimes(string $html, string $slug): array {
        $results = [];
        if (preg_match_all('/\b(\d{1,2}:\d{2}\s*(?:AM|PM)?)\b/i', $html, $matches)) {
            $seen = [];
            foreach (array_unique($matches[1]) as $t) {
                if (in_array($t, $seen)) continue;
                $seen[] = $t;
                $time = date('H:i', strtotime($t));
                if ($time && $time >= '06:00' && $time <= '23:59') {
                    $results[] = [
                        'cinema_slug' => $slug,
                        'movie_title' => 'Unknown',
                        'show_date' => date('Y-m-d'),
                        'show_time' => $time,
                        'format' => 'Standard',
                        'booking_url' => null,
                    ];
                }
            }
        }
        return $results;
    }
}

if (php_sapi_name() === 'cli' && !isset($GLOBALS['noRun'])) {
    require_once __DIR__ . '/../config.php';
    $db = getDbConnection();
    $scraper = new CinemallScraper($db);
    $scraper->run();
}
