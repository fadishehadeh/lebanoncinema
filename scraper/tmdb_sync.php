<?php
/**
 * Comprehensive TMDb Sync
 *
 * Fetches now-playing + upcoming movies from TMDb and syncs into local DB.
 * Run via cron: php scraper/tmdb_sync.php
 *
 * Keeps local cinema/showtime data separate — TMDb has no Lebanon cinema schedules.
 */
require_once __DIR__ . '/../config.php';

class TmdbSync {
    private PDO $db;
    private int $created = 0;
    private int $updated = 0;
    private int $errors = 0;
    private int $apiCalls = 0;
    private string $imgBase;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->imgBase = rtrim(TMDB_IMG_BASE, '/w500');
    }

    public function run(): void {
        echo "=== TMDb Sync Starting ===\n";

        // Add backdrop_url column if missing
        $this->migrate();

        // Fetch from TMDb
        $nowPlaying = $this->fetchList('now_playing');
        $upcoming   = $this->fetchList('upcoming');

        $all = array_merge($nowPlaying, $upcoming);
        $seen = [];

        foreach ($all as $tmdbMovie) {
            $tid = $tmdbMovie['id'];
            if (isset($seen[$tid])) continue;
            $seen[$tid] = true;
            $this->syncMovie($tid, $tmdbMovie);
        }

        echo "\n=== TMDb Sync Complete ===\n";
        echo "Created: {$this->created} | Updated: {$this->updated} | Errors: {$this->errors} | API calls: {$this->apiCalls}\n";
    }

    private function migrate(): void {
        $cols = [
            'backdrop_url VARCHAR(500) DEFAULT NULL AFTER poster_url',
            'cast_json TEXT DEFAULT NULL AFTER trailer_url',
            'imdb_id VARCHAR(20) DEFAULT NULL AFTER tmdb_id',
            'api_source VARCHAR(20) DEFAULT \'tmdb\' AFTER imdb_id',
            'last_synced_at TIMESTAMP NULL DEFAULT NULL AFTER api_source',
        ];
        foreach ($cols as $col) {
            try {
                $this->db->exec("ALTER TABLE movies ADD COLUMN IF NOT EXISTS $col");
            } catch (\Exception $e) {
                // Column may already exist — ignore
            }
        }
    }

    private function fetchList(string $type): array {
        $results = [];
        $page = 1;
        $maxPages = 3;
        $region = 'LB';

        while ($page <= $maxPages) {
            $url = TMDB_BASE_URL . "/movie/{$type}"
                 . "?api_key=" . TMDB_API_KEY
                 . "&language=en-US&page={$page}&region={$region}";

            $data = $this->apiGet($url);
            if (empty($data['results'])) break;

            foreach ($data['results'] as $m) {
                $results[] = $m;
            }

            if ($page >= ($data['total_pages'] ?? 1)) break;
            $page++;
        }

        echo "Fetched " . count($results) . " {$type} movies\n";
        return $results;
    }

    private function syncMovie(int $tmdbId, array $tmdbMovie): void {
        $title = $tmdbMovie['title'] ?? '';
        if (!$title) return;

        // Check if movie exists locally
        $stmt = $this->db->prepare("SELECT id, tmdb_id FROM movies WHERE tmdb_id = ? OR slug = ?");
        $slug = $this->slugify($title);
        $stmt->execute([$tmdbId, $slug]);
        $existing = $stmt->fetch();

        // Fetch full details
        $details = $this->fetchDetails($tmdbId);
        if (!$details) {
            $this->errors++;
            return;
        }

        $posterUrl = $details['poster_url'] ?? null;
        $backdropUrl = $details['backdrop_url'] ?? null;
        $trailerUrl = $details['trailer_url'] ?? null;
        $synopsis = $details['synopsis'] ?? null;
        $duration = $details['duration_min'] ?? null;
        $releaseDate = $details['release_date'] ?? null;
        $genres = $details['genres'] ?? '';
        $language = $details['language'] ?? null;
        $castJson = $details['cast_json'] ?? null;
        $imdbId = $details['imdb_id'] ?? null;
        $now = date('Y-m-d H:i:s');

        // Determine status
        $status = 'now_showing';
        if ($releaseDate && $releaseDate > date('Y-m-d')) {
            $status = 'coming_soon';
        }

        if ($existing) {
            // Update existing
            $stmt = $this->db->prepare("
                UPDATE movies SET
                    synopsis     = COALESCE(NULLIF(?, ''), synopsis),
                    poster_url   = COALESCE(NULLIF(?, ''), poster_url),
                    backdrop_url = COALESCE(NULLIF(?, ''), backdrop_url),
                    trailer_url  = COALESCE(NULLIF(?, ''), trailer_url),
                    duration_min = COALESCE(NULLIF(?, 0), duration_min),
                    language     = COALESCE(NULLIF(?, ''), language),
                    release_date = COALESCE(NULLIF(?, ''), release_date),
                    genres       = COALESCE(NULLIF(?, ''), genres),
                    cast_json    = COALESCE(NULLIF(?, ''), cast_json),
                    tmdb_id      = ?,
                    imdb_id      = ?,
                    api_source   = 'tmdb',
                    status       = ?,
                    last_synced_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $synopsis, $posterUrl, $backdropUrl, $trailerUrl,
                $duration, $language, $releaseDate, $genres, $castJson,
                $tmdbId, $imdbId, $status, $now, $existing['id']
            ]);
            $this->updated++;
        } else {
            // Create new
            $stmt = $this->db->prepare("
                INSERT INTO movies (title, slug, synopsis, poster_url, backdrop_url, trailer_url, duration_min, language, tmdb_id, imdb_id, release_date, genres, cast_json, status, api_source, last_synced_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'tmdb', ?)
            ");
            $stmt->execute([
                $title, $slug, $synopsis, $posterUrl, $backdropUrl, $trailerUrl,
                $duration, $language, $tmdbId, $imdbId, $releaseDate, $genres, $castJson, $status, $now
            ]);
            $this->created++;
        }
    }

    private function fetchDetails(int $tmdbId): ?array {
        $detailUrl = TMDB_BASE_URL . "/movie/{$tmdbId}"
            . "?api_key=" . TMDB_API_KEY
            . "&append_to_response=videos,credits,external_ids"
            . "&language=en-US";

        $detail = $this->apiGet($detailUrl);
        if (empty($detail['id'])) return null;

        // Videos
        $trailerUrl = null;
        $trailerKey = null;
        foreach ($detail['videos']['results'] ?? [] as $v) {
            if ($v['site'] === 'YouTube' && $v['type'] === 'Trailer') {
                $trailerUrl = 'https://www.youtube.com/watch?v=' . $v['key'];
                $trailerKey = $v['key'];
                break;
            }
        }

        // Cast (top 5 billed)
        $cast = [];
        foreach ($detail['credits']['cast'] ?? [] as $i => $c) {
            if ($i >= 5) break;
            $cast[] = [
                'name' => $c['name'],
                'character' => $c['character'] ?? '',
                'profile' => $c['profile_path'] ? $this->imgBase . '/w185' . $c['profile_path'] : null,
            ];
        }

        // IMDb ID from external_ids (append_to_response includes it)
        $imdbId = $detail['external_ids']['imdb_id'] ?? $detail['imdb_id'] ?? null;

        return [
            'poster_url'   => $detail['poster_path'] ? $this->imgBase . '/w500' . $detail['poster_path'] : null,
            'backdrop_url' => $detail['backdrop_path'] ? $this->imgBase . '/w1280' . $detail['backdrop_path'] : null,
            'trailer_url'  => $trailerUrl,
            'trailer_key'  => $trailerKey,
            'synopsis'     => $detail['overview'] ?? null,
            'duration_min' => $detail['runtime'] ?? null,
            'release_date' => $detail['release_date'] ?? null,
            'genres'       => !empty($detail['genres']) ? implode(', ', array_column($detail['genres'], 'name')) : '',
            'language'     => $detail['spoken_languages'][0]['english_name'] ?? null,
            'cast_json'    => !empty($cast) ? json_encode($cast) : null,
            'imdb_id'      => $imdbId,
        ];
    }

    private function apiGet(string $url): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->apiCalls++;
        usleep(300000);

        if ($code === 200 && $body) {
            return json_decode($body, true) ?? [];
        }
        return [];
    }

    private function slugify(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }
}

// CLI entry point
if (php_sapi_name() === 'cli') {
    $db = getDbConnection();
    $sync = new TmdbSync($db);
    $sync->run();
}
