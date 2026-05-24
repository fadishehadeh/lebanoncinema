<?php
/**
 * ScraperService — base class for all cinema chain scrapers.
 *
 * Each scraper extends this and implements parseShowtimes().
 * Shared HTTP client + HTML parsing + DB writing logic lives here.
 */
abstract class ScraperService {
    protected PDO $db;
    protected string $chainSlug;
    protected string $chainName;
    public int $moviesFound = 0;
    public int $timesFound = 0;
    public int $errors = 0;
    protected array $errorsList = [];

    public function __construct(PDO $db, string $chainSlug, string $chainName) {
        $this->db = $db;
        $this->chainSlug = $chainSlug;
        $this->chainName = $chainName;
    }

    /**
     * Fetch and parse showtimes for this cinema chain.
     * Returns array of [cinema_slug, movie_title, show_date, show_time, format, booking_url]
     */
    abstract protected function parseShowtimes(): array;

    /**
     * Run the scraper: parse, match, insert.
     */
    public function run(): array {
        $this->beforeRun();
        $rows = $this->parseShowtimes();
        $this->moviesFound = count(array_unique(array_column($rows, 'movie_title')));
        $inserted = 0;

        foreach ($rows as $row) {
            try {
                $result = $this->insertShowtime($row);
                if ($result) $inserted++;
            } catch (\Exception $e) {
                $this->errors++;
                $this->errorsList[] = $e->getMessage();
            }
        }

        $this->timesFound = $inserted;
        $this->afterRun();
        return ['movies' => $this->moviesFound, 'times' => $this->timesFound, 'errors' => $this->errors];
    }

    /**
     * HTTP GET helper with timeout and retry.
     */
    protected function httpGet(string $url, int $timeout = 15): ?string {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 LebanonCinema/1.0',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        usleep(500000); // 0.5s delay between requests
        return ($code === 200 && $body) ? $body : null;
    }

    /**
     * Match a scraped movie title to an existing local movie record.
     * Tries exact match first, then LIKE, then slug.
     */
    protected function matchMovie(string $title): ?int {
        // Exact match
        $stmt = $this->db->prepare("SELECT id FROM movies WHERE title = ? LIMIT 1");
        $stmt->execute([$title]);
        if ($r = $stmt->fetch()) return (int)$r['id'];

        // LIKE match (handles minor differences)
        $like = '%' . $title . '%';
        $stmt = $this->db->prepare("SELECT id, title FROM movies WHERE title LIKE ? LIMIT 1");
        $stmt->execute([$like]);
        if ($r = $stmt->fetch()) return (int)$r['id'];

        // Slug match
        $slug = $this->slugify($title);
        $stmt = $this->db->prepare("SELECT id FROM movies WHERE slug LIKE ? LIMIT 1");
        $stmt->execute(["%$slug%"]);
        if ($r = $stmt->fetch()) return (int)$r['id'];

        // Try without special characters
        $clean = preg_replace('/[^a-z0-9\s]/i', '', $title);
        $stmt = $this->db->prepare("SELECT id FROM movies WHERE title LIKE ? LIMIT 1");
        $stmt->execute(["%$clean%"]);
        if ($r = $stmt->fetch()) return (int)$r['id'];

        return null;
    }

    /**
     * Find cinema ID by slug.
     */
    protected function findCinema(string $slug): ?int {
        $stmt = $this->db->prepare("SELECT id FROM cinemas WHERE slug = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$slug]);
        return ($r = $stmt->fetch()) ? (int)$r['id'] : null;
    }

    /**
     * Insert a single showtime row.
     */
    protected function insertShowtime(array $row): bool {
        $movieId = $this->matchMovie($row['movie_title']);
        if (!$movieId) {
            $this->errorsList[] = "Movie not found: {$row['movie_title']}";
            return false;
        }

        $cinemaId = $this->findCinema($row['cinema_slug']);
        if (!$cinemaId) {
            $this->errorsList[] = "Cinema not found: {$row['cinema_slug']}";
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT INTO showtimes (movie_id, cinema_id, show_date, show_time, format, language, booking_url)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE format = VALUES(format), booking_url = VALUES(booking_url)
        ");
        $stmt->execute([
            $movieId,
            $cinemaId,
            $row['show_date'],
            $row['show_time'],
            $row['format'] ?? 'Standard',
            $row['language'] ?? 'English',
            $row['booking_url'] ?? null,
        ]);
        return true;
    }

    /**
     * Send output to STDOUT and optionally to log.
     */
    protected function log(string $msg): void {
        $line = '[' . date('H:i:s') . '] ' . $msg;
        echo $line . "\n";
    }

    /**
     * Before-run hook — override to add logging.
     */
    protected function beforeRun(): void {
        $this->log("Starting scrape for {$this->chainName}");
    }

    /**
     * After-run hook — log results and write to scraper_log table.
     */
    protected function afterRun(): void {
        $this->log("{$this->chainName}: {$this->moviesFound} movies, {$this->timesFound} showtimes, {$this->errors} errors");
        try {
            $status = match (true) {
                $this->errors === 0 => 'success',
                $this->timesFound > 0 => 'partial',
                default => 'failed',
            };
            $stmt = $this->db->prepare("INSERT INTO scraper_log (chain_slug, scrape_date, status, movies_found, times_found, error_msg) VALUES (?, CURDATE(), ?, ?, ?, ?)");
            $stmt->execute([$this->chainSlug, $status, $this->moviesFound, $this->timesFound, implode('; ', array_slice($this->errorsList, 0, 3))]);
        } catch (\Exception $e) {}
    }

    protected function slugify(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        return preg_replace('/[\s-]+/', '-', trim($text, '-'));
    }

    protected function normalizeTitle(string $title): string {
        // Remove extra whitespace, normalize quotes, etc.
        $title = preg_replace('/\s+/', ' ', trim($title));
        $title = str_replace(['’', 'ʻ', 'ʼ'], "'", $title);
        $title = preg_replace('/\s*\(.*?\)\s*/', '', $title); // Remove parentheticals
        return trim($title);
    }
}
