<?php
/**
 * VOX Lebanon Showtime Scraper
 * Scrapes JSON-LD structured data from lbn.voxcinemas.com/showtimes?d=YYYYMMDD
 *
 * Usage:
 *   php vox_scraper.php              → scrapes today
 *   php vox_scraper.php 2026-05-10   → scrapes specific date
 *   php vox_scraper.php 7            → scrapes next 7 days
 */

require_once __DIR__ . '/../config.php';

class VoxScraper {

    const BASE_URL   = 'https://lbn.voxcinemas.com/showtimes';
    const CHAIN_SLUG = 'vox';
    const DELAY_MS   = 1500; // be polite, wait between requests

    private PDO $db;
    private array $log = [];

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    // ─────────────────────────────────────────────
    // PUBLIC: scrape one or multiple dates
    // ─────────────────────────────────────────────

    public function scrapeDate(string $date): array {
        $url  = self::BASE_URL . '?d=' . str_replace('-', '', $date);
        $html = $this->fetch($url);

        if (!$html) {
            $this->log("ERROR: Could not fetch $url");
            return [];
        }

        $blocks = $this->extractJsonLdBlocks($html);
        if (empty($blocks)) {
            $this->log("WARNING: No JSON-LD blocks found for $date");
            return [];
        }

        $showtimes = $this->parseShowtimes($html, $date);
        $this->log("INFO: Found " . count($showtimes) . " showtimes for $date");

        return $showtimes;
    }

    public function scrapeDays(int $days = 7): void {
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            $this->log("Scraping $date...");
            $showtimes = $this->scrapeDate($date);
            $this->saveShowtimes($showtimes, $date);
            usleep(self::DELAY_MS * 1000);
        }
        $this->printLog();
    }

    // ─────────────────────────────────────────────
    // FETCH HTML
    // ─────────────────────────────────────────────

    private function fetch(string $url): ?string {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: gzip, deflate, br',
                'Cache-Control: no-cache',
            ],
            CURLOPT_ENCODING       => '', // auto-decode gzip
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($code === 200 && $html) ? $html : null;
    }

    // ─────────────────────────────────────────────
    // PARSE JSON-LD BLOCKS
    // ─────────────────────────────────────────────

    private function extractJsonLdBlocks(string $html): array {
        $blocks = [];
        preg_match_all(
            '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si',
            $html, $matches
        );
        foreach ($matches[1] as $json) {
            $data = json_decode(trim($json), true);
            if ($data) $blocks[] = $data;
        }
        return $blocks;
    }

    private function parseShowtimes(string $html, string $date): array {
        $results = [];

        // ── 1. Get movie metadata from JSON-LD ──
        $movies = [];
        $blocks = $this->extractJsonLdBlocks($html);
        foreach ($blocks as $block) {
            // Handle both single item and @graph array
            $items = isset($block['@graph']) ? $block['@graph'] : [$block];
            foreach ($items as $item) {
                if (($item['@type'] ?? '') === 'Movie') {
                    $slug = $this->slugify($item['name'] ?? '');
                    $movies[$slug] = [
                        'title'          => $item['name'] ?? '',
                        'slug'           => $slug,
                        'duration_min'   => $this->parseDuration($item['duration'] ?? ''),
                        'rating'         => $item['contentRating'] ?? null,
                        'poster_url'     => $item['image'] ?? null,
                        'language'       => $item['inLanguage'] ?? null,
                        'url_path'       => $item['url'] ?? null,
                    ];
                }
            }
        }

        // ── 2. Parse showtime rows from HTML ──
        // VOX renders showtimes in the DOM — parse cinema sections and time slots
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // Find cinema sections (adjust selectors once you inspect actual markup)
        // Pattern: each cinema block contains cinema name + list of movies + their times
        $cinemaSections = $xpath->query('//*[contains(@class,"cinema") or contains(@class,"location")]');

        if ($cinemaSections->length > 0) {
            foreach ($cinemaSections as $section) {
                $cinemaName = trim($xpath->query('.//*[contains(@class,"cinema-name") or contains(@class,"location-name")]', $section)->item(0)?->textContent ?? '');
                $movieRows  = $xpath->query('.//*[contains(@class,"movie") or contains(@class,"film")]', $section);

                foreach ($movieRows as $row) {
                    $title     = trim($xpath->query('.//*[contains(@class,"title") or contains(@class,"name")]', $row)->item(0)?->textContent ?? '');
                    $timeNodes = $xpath->query('.//*[contains(@class,"time") or contains(@class,"showtime") or contains(@class,"session")]', $row);
                    $times     = [];
                    foreach ($timeNodes as $tn) {
                        $t = trim($tn->textContent);
                        if (preg_match('/^\d{1,2}:\d{2}\s*(am|pm)?$/i', $t)) {
                            $times[] = $t;
                        }
                    }

                    if ($title && $times) {
                        $slug = $this->slugify($title);
                        $results[] = [
                            'cinema_name' => $cinemaName ?: 'VOX City Centre Beirut',
                            'chain_slug'  => self::CHAIN_SLUG,
                            'date'        => $date,
                            'movie'       => array_merge(['title' => $title, 'slug' => $slug], $movies[$slug] ?? []),
                            'times'       => $times,
                        ];
                    }
                }
            }
        }

        // ── Fallback: if DOM parse found nothing, return movie metadata only ──
        if (empty($results) && !empty($movies)) {
            $this->log("WARNING: DOM showtime parse failed for $date — returning movie list only");
            foreach ($movies as $movie) {
                $results[] = [
                    'cinema_name' => 'VOX City Centre Beirut',
                    'chain_slug'  => self::CHAIN_SLUG,
                    'date'        => $date,
                    'movie'       => $movie,
                    'times'       => [],
                ];
            }
        }

        return $results;
    }

    // ─────────────────────────────────────────────
    // SAVE TO DATABASE
    // ─────────────────────────────────────────────

    private function saveShowtimes(array $showtimes, string $date): void {
        if (empty($showtimes)) return;

        // Delete existing showtimes for this chain+date to avoid duplicates
        $stmt = $this->db->prepare("
            DELETE s FROM showtimes s
            JOIN cinemas c ON s.cinema_id = c.id
            JOIN chains ch ON c.chain_id = ch.id
            WHERE ch.slug = ? AND s.show_date = ?
        ");
        $stmt->execute([self::CHAIN_SLUG, $date]);

        foreach ($showtimes as $row) {
            // Upsert movie
            $movieId = $this->upsertMovie($row['movie']);

            // Get cinema ID
            $cinemaId = $this->getCinemaId($row['cinema_name'], $row['chain_slug']);
            if (!$cinemaId) {
                $this->log("WARNING: Cinema not found: {$row['cinema_name']}");
                continue;
            }

            // Insert showtimes
            foreach ($row['times'] as $time) {
                $showtime = $this->normalizeTime($time);
                $stmt = $this->db->prepare("
                    INSERT IGNORE INTO showtimes (movie_id, cinema_id, show_date, show_time)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$movieId, $cinemaId, $date, $showtime]);
            }
        }
    }

    private function upsertMovie(array $data): int {
        $stmt = $this->db->prepare("SELECT id FROM movies WHERE slug = ?");
        $stmt->execute([$data['slug']]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            // Update poster/duration if we have it
            $this->db->prepare("
                UPDATE movies SET
                    poster_url   = COALESCE(NULLIF(?, ''), poster_url),
                    duration_min = COALESCE(NULLIF(?, 0), duration_min),
                    rating       = COALESCE(NULLIF(?, ''), rating),
                    status       = 'now_showing'
                WHERE id = ?
            ")->execute([$data['poster_url'] ?? null, $data['duration_min'] ?? 0, $data['rating'] ?? null, $existing]);
            return (int)$existing;
        }

        $stmt = $this->db->prepare("
            INSERT INTO movies (title, slug, duration_min, rating, poster_url, status)
            VALUES (?, ?, ?, ?, ?, 'now_showing')
        ");
        $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['duration_min'] ?? null,
            $data['rating'] ?? null,
            $data['poster_url'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    private function getCinemaId(string $name, string $chainSlug): ?int {
        $stmt = $this->db->prepare("
            SELECT c.id FROM cinemas c
            JOIN chains ch ON c.chain_id = ch.id
            WHERE ch.slug = ? AND (c.name LIKE ? OR c.slug LIKE ?)
            LIMIT 1
        ");
        $stmt->execute([$chainSlug, "%$name%", $this->slugify($name)]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    private function parseDuration(string $iso): ?int {
        // ISO 8601 duration: PT115M or PT1H55M
        if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $iso, $m)) {
            return ((int)($m[1] ?? 0) * 60) + (int)($m[2] ?? 0);
        }
        return null;
    }

    private function normalizeTime(string $time): string {
        // Convert "2:30 pm" → "14:30:00"
        $ts = strtotime($time);
        return $ts ? date('H:i:s', $ts) : $time;
    }

    private function slugify(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }

    private function log(string $msg): void {
        $this->log[] = date('[H:i:s]') . " $msg";
    }

    private function printLog(): void {
        echo implode("\n", $this->log) . "\n";
    }
}

// ─────────────────────────────────────────────
// CLI RUNNER
// ─────────────────────────────────────────────

if (php_sapi_name() === 'cli') {
    $db     = getDbConnection();
    $scraper = new VoxScraper($db);

    $arg = $argv[1] ?? null;

    if (!$arg) {
        // Default: scrape today + next 6 days
        echo "Scraping 7 days of VOX showtimes...\n";
        $scraper->scrapeDays(7);

    } elseif (is_numeric($arg)) {
        echo "Scraping next $arg days...\n";
        $scraper->scrapeDays((int)$arg);

    } else {
        // Specific date e.g. 2026-05-10
        echo "Scraping $arg...\n";
        $result = $scraper->scrapeDate($arg);
        print_r($result);
    }
}
