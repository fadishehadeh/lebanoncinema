<?php
/**
 * TMDB Movie Enricher
 * Enriches movies in the database with poster, synopsis, trailer, genres, and metadata
 *
 * Usage:
 *   php tmdb_enricher.php           → enriches movies where tmdb_id IS NULL
 *   php tmdb_enricher.php --force   → re-enriches all movies
 */

require_once __DIR__ . '/../config.php';

class TmdbEnricher {

    private PDO $db;
    private int $enriched = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private bool $force = false;

    public function __construct(PDO $db, bool $force = false) {
        $this->db = $db;
        $this->force = $force;
    }

    public function run(): void {
        $this->enrichAll();
        $this->logResults();
        echo "Done. Enriched: {$this->enriched} | Skipped: {$this->skipped} | Errors: {$this->errors}\n";
    }

    private function enrichAll(): void {
        $sql = $this->force
            ? "SELECT id, title, slug FROM movies"
            : "SELECT id, title, slug FROM movies WHERE tmdb_id IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $movies = $stmt->fetchAll();

        foreach ($movies as $movie) {
            $this->enrichMovie($movie);
        }
    }

    private function enrichMovie(array $movie): void {
        $tmdbId = $movie['id'];

        // Search for the movie
        $searchResult = $this->tmdbSearch($movie['title']);
        if (!$searchResult) {
            $this->skipped++;
            return;
        }

        // Fetch details and videos
        $details = $this->tmdbDetails($searchResult['id']);
        if (!$details) {
            $this->errors++;
            return;
        }

        // Update database
        try {
            $stmt = $this->db->prepare("
                UPDATE movies SET
                    synopsis     = ?,
                    poster_url   = CASE WHEN (poster_url IS NULL OR poster_url = '') THEN ? ELSE poster_url END,
                    trailer_url  = ?,
                    duration_min = COALESCE(NULLIF(?, 0), duration_min),
                    language     = ?,
                    tmdb_id      = ?,
                    imdb_id      = ?,
                    release_date = ?,
                    genres       = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $details['synopsis'],
                $details['poster_url'],
                $details['trailer_url'],
                $details['duration_min'],
                $details['language'],
                $details['tmdb_id'],
                $details['imdb_id'],
                $details['release_date'],
                $details['genres'],
                $movie['id'],
            ]);

            $this->enriched++;
        } catch (Exception $e) {
            $this->errors++;
        }
    }

    private function tmdbSearch(string $title): ?array {
        $url = TMDB_BASE_URL . '/search/movie'
            . '?api_key=' . TMDB_API_KEY
            . '&query=' . urlencode($title)
            . '&language=en-US&page=1';

        $data = $this->tmdbGet($url);
        $results = $data['results'] ?? [];

        foreach ($results as $result) {
            if (($result['vote_count'] ?? 0) > 50) {
                return $result;
            }
        }

        return null;
    }

    private function tmdbDetails(int $id): ?array {
        $detailUrl = TMDB_BASE_URL . '/movie/' . $id . '?api_key=' . TMDB_API_KEY;
        $videosUrl = TMDB_BASE_URL . '/movie/' . $id . '/videos?api_key=' . TMDB_API_KEY;

        $detail = $this->tmdbGet($detailUrl);
        if (empty($detail['id'])) {
            return null;
        }

        $videos = $this->tmdbGet($videosUrl);

        $trailerUrl = null;
        foreach ($videos['results'] ?? [] as $v) {
            if ($v['site'] === 'YouTube' && $v['type'] === 'Trailer') {
                $trailerUrl = 'https://www.youtube.com/watch?v=' . $v['key'];
                break;
            }
        }

        $posterUrl = null;
        if (!empty($detail['poster_path'])) {
            $posterUrl = TMDB_IMG_BASE . $detail['poster_path'];
        }

        $language = null;
        if (!empty($detail['spoken_languages'][0]['english_name'])) {
            $language = $detail['spoken_languages'][0]['english_name'];
        }

        $genres = '';
        if (!empty($detail['genres'])) {
            $genres = implode(', ', array_column($detail['genres'], 'name'));
        }

        return [
            'tmdb_id'      => $detail['id'],
            'imdb_id'      => $detail['imdb_id'] ?? null,
            'synopsis'     => $detail['overview'] ?? null,
            'duration_min' => $detail['runtime'] ?? null,
            'release_date' => $detail['release_date'] ?? null,
            'genres'       => $genres,
            'language'     => $language,
            'poster_url'   => $posterUrl,
            'trailer_url'  => $trailerUrl,
        ];
    }

    private function tmdbGet(string $url): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($code === 200 && $body) {
            usleep(250000); // Rate limit: 4 req/sec
            return json_decode($body, true) ?? [];
        }

        return [];
    }

    private function logResults(): void {
        try {
            $status = match (true) {
                $this->errors === 0 => 'success',
                $this->enriched > 0 => 'partial',
                default => 'failed',
            };

            $stmt = $this->db->prepare("
                INSERT INTO scraper_log (chain_slug, scrape_date, status, movies_found, error_msg)
                VALUES ('tmdb', CURDATE(), ?, ?, ?)
            ");

            $errorMsg = $this->errors > 0 ? "Errors: {$this->errors}" : null;

            $stmt->execute([$status, $this->enriched, $errorMsg]);
        } catch (Exception $e) {
            // Silently fail if logging fails
        }
    }
}

if (php_sapi_name() === 'cli') {
    $db = getDbConnection();
    $force = in_array('--force', $argv ?? []);
    $enricher = new TmdbEnricher($db, $force);
    $enricher->run();
}
