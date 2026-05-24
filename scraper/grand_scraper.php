<?php
/**
 * Grand Cinemas showtimes scraper.
 * Source: grandcinemas.com.lb
 */
require_once __DIR__ . '/ScraperService.php';

class GrandScraper extends ScraperService {
    private array $cinemas;

    public function __construct(PDO $db) {
        parent::__construct($db, 'grand', 'Grand Cinemas');
        // All Grand Cinemas branches from our database
        $this->cinemas = [
            ['slug' => 'grand-abc-achrafieh', 'url' => 'https://grandcinemas.com.lb/achrafieh'],
            ['slug' => 'grand-abc-dbayeh', 'url' => 'https://grandcinemas.com.lb/dbayeh'],
            ['slug' => 'grand-abc-verdun', 'url' => 'https://grandcinemas.com.lb/verdun'],
            ['slug' => 'grand-las-salinas', 'url' => 'https://grandcinemas.com.lb/las-salinas'],
            ['slug' => 'grand-the-spot-saida', 'url' => 'https://grandcinemas.com.lb/saida'],
        ];
    }

    protected function parseShowtimes(): array {
        $results = [];
        foreach ($this->cinemas as $cinema) {
            $html = $this->httpGet($cinema['url']);
            if (!$html) {
                $this->log("  Skipped {$cinema['slug']} — no response");
                continue;
            }

            $doc = new DOMDocument();
            @$doc->loadHTML($html);
            $xpath = new DOMXPath($doc);

            // Grand Cinemas typically lists movies in div.movie-item or similar
            $movies = $xpath->query("//div[contains(@class, 'movie-item')] | //div[contains(@class, 'showtime-item')] | //article[contains(@class, 'movie')]");

            if ($movies->length === 0) {
                // Fallback: try parsing from JSON-LD or script data
                $scripts = $xpath->query("//script[@type='application/ld+json']");
                foreach ($scripts as $script) {
                    $data = json_decode($script->textContent, true);
                    if ($data) {
                        $results = array_merge($results, $this->parseJsonLd($data, $cinema['slug']));
                    }
                }
                if (empty($results)) {
                    $this->log("  No movies found for {$cinema['slug']} — trying generic parse");
                    $results = array_merge($results, $this->parseGeneric($html, $cinema['slug']));
                }
                continue;
            }

            foreach ($movies as $movie) {
                $titleEl = $xpath->query(".//h2 | .//h3 | .//h4 | .//*[contains(@class, 'title')]", $movie)->item(0);
                $title = $titleEl ? trim($titleEl->textContent) : '';

                if (empty($title)) continue;
                $title = $this->normalizeTitle($title);

                // Find showtimes
                $timeEls = $xpath->query(".//*[contains(@class, 'time')] | .//*[contains(@class, 'showtime')] | .//a[contains(@href, 'booking')]", $movie);
                foreach ($timeEls as $el) {
                    $timeText = trim($el->textContent);
                    $time = $this->parseTime($timeText);
                    if (!$time) continue;

                    $results[] = [
                        'cinema_slug' => $cinema['slug'],
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

    private function parseTime(string $text): ?string {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        // Try 12h format: "7:30 PM", "12:00 AM"
        if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $text, $m)) {
            return date('H:i', strtotime("{$m[1]}:{$m[2]} {$m[3]}"));
        }
        // Try 24h format: "19:30"
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $text, $m)) {
            $h = (int)$m[1];
            if ($h >= 6 && $h <= 23) return sprintf('%02d:%s', $h, $m[2]);
        }
        return null;
    }

    private function parseJsonLd(array $data, string $cinemaSlug): array {
        $results = [];
        $events = $data['@graph'] ?? [$data];
        foreach ($events as $event) {
            if (($event['@type'] ?? '') !== 'ScreeningEvent') continue;
            $title = $event['workPresented']['name'] ?? '';
            $date = $event['startDate'] ?? '';
            if ($title && $date) {
                $dt = date('Y-m-d', strtotime($date));
                $tm = date('H:i', strtotime($date));
                if ($dt >= date('Y-m-d')) {
                    $results[] = [
                        'cinema_slug' => $cinemaSlug,
                        'movie_title' => $this->normalizeTitle($title),
                        'show_date' => $dt,
                        'show_time' => $tm,
                        'format' => 'Standard',
                        'booking_url' => $event['url'] ?? null,
                    ];
                }
            }
        }
        return $results;
    }

    private function parseGeneric(string $html, string $cinemaSlug): array {
        $results = [];
        // Match common time patterns in raw HTML
        if (preg_match_all('/\b(\d{1,2}):(\d{2})\s*(AM|PM)\b/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $time = date('H:i', strtotime("{$m[1]}:{$m[2]} {$m[3]}"));
                $results[] = [
                    'cinema_slug' => $cinemaSlug,
                    'movie_title' => 'Unknown',
                    'show_date' => date('Y-m-d'),
                    'show_time' => $time,
                    'format' => 'Standard',
                    'booking_url' => null,
                ];
            }
        }
        return $results;
    }
}

// CLI entry
if (php_sapi_name() === 'cli' && !isset($GLOBALS['noRun'])) {
    require_once __DIR__ . '/../config.php';
    $db = getDbConnection();
    $scraper = new GrandScraper($db);
    $scraper->run();
}
