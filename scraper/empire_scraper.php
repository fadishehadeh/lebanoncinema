<?php
/**
 * Empire Cinemas showtimes scraper.
 * Source: empirecinemas.com.lb
 */
require_once __DIR__ . '/ScraperService.php';

class EmpireScraper extends ScraperService {
    private array $cinemas;

    public function __construct(PDO $db) {
        parent::__construct($db, 'empire', 'Empire Cinemas');
        $this->cinemas = [
            ['slug' => 'empire-choueifat', 'url' => 'https://www.empirecinemas.com.lb/choueifat'],
            ['slug' => 'empire-premier', 'url' => 'https://www.empirecinemas.com.lb/premier'],
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

            $movies = $xpath->query("//div[contains(@class, 'movie')] | //li[contains(@class, 'movie')] | //div[contains(@class, 'film')]");
            if ($movies->length === 0) {
                $results = array_merge($results, $this->parseGeneric($html, $cinema['slug']));
                continue;
            }

            foreach ($movies as $movie) {
                $titleEl = $xpath->query(".//h2 | .//h3 | .//h4 | .//*[contains(@class, 'title')]", $movie)->item(0);
                $title = $titleEl ? $this->normalizeTitle(trim($titleEl->textContent)) : '';
                if (empty($title)) continue;

                $timeEls = $xpath->query(".//*[contains(@class, 'time')] | .//a[contains(text(), ':')] | .//span[contains(@class, 'showtime')]", $movie);
                foreach ($timeEls as $el) {
                    $time = $this->parseTime(trim($el->textContent));
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
        $text = preg_replace('/\s+/', ' ', trim($text));
        if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $text, $m)) {
            return date('H:i', strtotime("{$m[1]}:{$m[2]} {$m[3]}"));
        }
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $text, $m)) {
            $h = (int)$m[1];
            if ($h >= 6 && $h <= 23) return sprintf('%02d:%s', $h, $m[2]);
        }
        return null;
    }

    private function parseGeneric(string $html, string $cinemaSlug): array {
        $results = [];
        if (preg_match_all('/\b(\d{1,2}:\d{2}\s*(?:AM|PM))\b/i', $html, $matches)) {
            $times = array_unique($matches[1]);
            foreach ($times as $t) {
                $time = date('H:i', strtotime($t));
                if ($time) {
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
        }
        return $results;
    }
}

if (php_sapi_name() === 'cli' && !isset($GLOBALS['noRun'])) {
    require_once __DIR__ . '/../config.php';
    $db = getDbConnection();
    $scraper = new EmpireScraper($db);
    $scraper->run();
}
