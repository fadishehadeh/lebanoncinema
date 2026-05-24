<?php
require_once __DIR__ . '/ScraperService.php';

class CinemaCityScraper extends ScraperService {
    private array $cinemas;

    public function __construct(PDO $db) {
        parent::__construct($db, 'cinemacity', 'CinemaCity');
        $this->cinemas = [
            ['slug' => 'cinemacity-souks', 'url' => 'https://www.cinemacity.com.lb/souks'],
            ['slug' => 'cinemacity-abc-ashrafieh', 'url' => 'https://www.cinemacity.com.lb/abc-ashrafieh'],
            ['slug' => 'cinemacity-tripoli', 'url' => 'https://www.cinemacity.com.lb/tripoli'],
            ['slug' => 'cinemacity-jounieh', 'url' => 'https://www.cinemacity.com.lb/jounieh'],
        ];
    }

    protected function parseShowtimes(): array {
        $results = [];
        foreach ($this->cinemas as $cinema) {
            $html = $this->httpGet($cinema['url']);
            if (!$html) { $this->log("  Skipped {$cinema['slug']}"); continue; }

            $doc = new DOMDocument(); @$doc->loadHTML($html);
            $xpath = new DOMXPath($doc);
            $movies = $xpath->query("//div[contains(@class, 'movie')] | //div[contains(@class, 'film')] | //article");

            if ($movies->length === 0) {
                // Try VOX-style JSON endpoint
                $jsonUrl = "https://www.cinemacity.com.lb/api/showtimes/{$cinema['slug']}";
                $json = $this->httpGet($jsonUrl);
                if ($json) {
                    $data = json_decode($json, true);
                    if ($data) $results = array_merge($results, $this->parseApi($data, $cinema['slug']));
                }
                continue;
            }

            foreach ($movies as $movie) {
                $titleEl = $xpath->query(".//h2 | .//h3 | .//*[contains(@class, 'title')]", $movie)->item(0);
                $title = $titleEl ? $this->normalizeTitle(trim($titleEl->textContent)) : '';
                if (empty($title)) continue;

                $timeEls = $xpath->query(".//*[contains(@class, 'time')] | .//a | .//span[contains(@class, 'show')]", $movie);
                foreach ($timeEls as $el) {
                    $text = trim($el->textContent);
                    if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)?/i', $text, $m)) {
                        $time = $m[3] ? date('H:i', strtotime("{$m[1]}:{$m[2]} {$m[3]}")) : sprintf('%02d:%s', $m[1], $m[2]);
                        $results[] = ['cinema_slug' => $cinema['slug'], 'movie_title' => $title, 'show_date' => date('Y-m-d'), 'show_time' => $time, 'format' => 'Standard', 'booking_url' => $el->getAttribute('href') ?: null];
                    }
                }
            }
        }
        return $results;
    }

    private function parseApi(array $data, string $slug): array {
        $results = [];
        foreach ($data['showtimes'] ?? $data as $item) {
            $title = $item['movie_title'] ?? $item['title'] ?? '';
            $date = $item['date'] ?? date('Y-m-d');
            $time = $item['time'] ?? '';
            if ($title && $time) {
                $results[] = ['cinema_slug' => $slug, 'movie_title' => $this->normalizeTitle($title), 'show_date' => $date, 'show_time' => $time, 'format' => $item['format'] ?? 'Standard', 'booking_url' => $item['booking_url'] ?? null];
            }
        }
        return $results;
    }
}

if (php_sapi_name() === 'cli' && !isset($GLOBALS['noRun'])) {
    require_once __DIR__ . '/../config.php';
    $db = getDbConnection();
    $scraper = new CinemaCityScraper($db);
    $scraper->run();
}
