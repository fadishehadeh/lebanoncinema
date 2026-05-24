<?php
/**
 * TmdbService — Reusable TMDb API client.
 *
 * All API calls are isolated here. Public pages never call TMDb directly.
 * Admin pages use this service to search, import, and sync movies.
 */
class TmdbService {
    private string $apiKey;
    private string $baseUrl;
    private string $imgBase;
    private PDO $db;
    public int $apiCalls = 0;

    public function __construct(PDO $db) {
        $this->apiKey  = defined('TMDB_API_KEY') ? TMDB_API_KEY : '';
        $this->baseUrl = defined('TMDB_BASE_URL') ? TMDB_BASE_URL : 'https://api.themoviedb.org/3';
        $this->imgBase = defined('TMDB_IMG_BASE') ? TMDB_IMG_BASE : 'https://image.tmdb.org/t/p';
        $this->db      = $db;
    }

    // ─── API METHODS ─────────────────────────────────

    public function searchMovies(string $query, int $page = 1): array {
        $url = $this->baseUrl . '/search/movie'
            . '?api_key=' . $this->apiKey
            . '&query=' . urlencode($query)
            . '&language=en-US&page=' . $page . '&region=LB';
        return $this->get($url);
    }

    public function getMovieDetails(int $tmdbId): array {
        $url = $this->baseUrl . '/movie/' . $tmdbId
            . '?api_key=' . $this->apiKey
            . '&language=en-US';
        return $this->get($url);
    }

    public function getMovieCredits(int $tmdbId): array {
        $url = $this->baseUrl . '/movie/' . $tmdbId . '/credits'
            . '?api_key=' . $this->apiKey
            . '&language=en-US';
        return $this->get($url);
    }

    public function getMovieVideos(int $tmdbId): array {
        $url = $this->baseUrl . '/movie/' . $tmdbId . '/videos'
            . '?api_key=' . $this->apiKey
            . '&language=en-US';
        return $this->get($url);
    }

    public function getExternalIds(int $tmdbId): array {
        $url = $this->baseUrl . '/movie/' . $tmdbId . '/external_ids'
            . '?api_key=' . $this->apiKey;
        return $this->get($url);
    }

    // ─── IMAGE HELPERS ───────────────────────────────

    public function buildPosterUrl(?string $path, string $size = 'w500'): ?string {
        if (!$path) return null;
        return $this->imgBase . '/' . $size . $path;
    }

    public function buildBackdropUrl(?string $path, string $size = 'w1280'): ?string {
        if (!$path) return null;
        return $this->imgBase . '/' . $size . $path;
    }

    public function buildProfileUrl(?string $path, string $size = 'w185'): ?string {
        if (!$path) return null;
        return $this->imgBase . '/' . $size . $path;
    }

    // ─── IMPORT — fetch from TMDb, store locally ──────

    public function importMovie(int $tmdbId): array {
        // Fetch all data in one combined request
        $detail = $this->getMovieDetails($tmdbId);
        if (empty($detail['id'])) {
            return ['success' => false, 'error' => 'Movie not found on TMDb'];
        }

        $credits = $this->getMovieCredits($tmdbId);
        $videos  = $this->getMovieVideos($tmdbId);
        $extIds  = $this->getExternalIds($tmdbId);

        // Prevent duplicate import
        $check = $this->db->prepare("SELECT id FROM movies WHERE tmdb_id = ?");
        $check->execute([$tmdbId]);
        if ($existing = $check->fetch()) {
            // Update instead of skipping
            $this->syncMovieData($existing['id'], $detail, $credits, $videos, $extIds);
            $this->logSync($existing['id'], $tmdbId, 'update', 'success', 'Re-imported');
            return ['success' => true, 'movie_id' => $existing['id'], 'action' => 'updated'];
        }

        // Build data
        $title      = $detail['title'] ?? 'Untitled';
        $slug       = $this->slugify($title);
        $poster     = $this->buildPosterUrl($detail['poster_path']);
        $backdrop   = $this->buildBackdropUrl($detail['backdrop_path']);
        $trailerKey = $this->findTrailerKey($videos);
        $overview   = $detail['overview'] ?? '';
        $runtime    = $detail['runtime'] ? (int)$detail['runtime'] : null;
        $voteAvg    = $detail['vote_average'] ?? null;
        $voteCount  = $detail['vote_count'] ?? null;
        $releaseDt  = $detail['release_date'] ?? null;
        $genresJson = !empty($detail['genres']) ? json_encode($detail['genres']) : null;
        $castJson   = $this->buildCastJson($credits);
        $imdbId     = $extIds['imdb_id'] ?? $detail['imdb_id'] ?? null;
        $now        = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare("
            INSERT INTO movies
                (tmdb_id, imdb_id, title, slug, overview, poster_path, backdrop_path, trailer_key,
                 runtime, vote_average, vote_count, release_date, genres_json, cast_json,
                 api_source, is_showing, is_coming_soon, last_synced_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'tmdb', ?, ?, ?, NOW(), NOW())
        ");

        $isNowShowing   = $releaseDt && $releaseDt <= date('Y-m-d') ? 1 : 0;
        $isComingSoon   = $releaseDt && $releaseDt > date('Y-m-d') ? 1 : 0;

        $stmt->execute([
            $tmdbId, $imdbId, $title, $slug, $overview, $poster, $backdrop, $trailerKey,
            $runtime, $voteAvg, $voteCount, $releaseDt, $genresJson, $castJson,
            $isNowShowing, $isComingSoon, $now,
        ]);

        $movieId = (int)$this->db->lastInsertId();
        $this->logSync($movieId, $tmdbId, 'import', 'success', 'Imported successfully');
        return ['success' => true, 'movie_id' => $movieId, 'action' => 'created'];
    }

    // ─── SYNC — update existing movie ────────────────

    public function syncMovie(int $movieId): array {
        $stmt = $this->db->prepare("SELECT tmdb_id FROM movies WHERE id = ?");
        $stmt->execute([$movieId]);
        $movie = $stmt->fetch();
        if (!$movie || !$movie['tmdb_id']) {
            return ['success' => false, 'error' => 'Movie has no TMDb ID'];
        }
        return $this->importMovie((int)$movie['tmdb_id']);
    }

    // ─── INTERNAL ────────────────────────────────────

    private function syncMovieData(int $movieId, array $detail, array $credits, array $videos, array $extIds): void {
        $poster     = $this->buildPosterUrl($detail['poster_path']);
        $backdrop   = $this->buildBackdropUrl($detail['backdrop_path']);
        $trailerKey = $this->findTrailerKey($videos);
        $overview   = $detail['overview'] ?? '';
        $runtime    = $detail['runtime'] ? (int)$detail['runtime'] : null;
        $voteAvg    = $detail['vote_average'] ?? null;
        $voteCount  = $detail['vote_count'] ?? null;
        $releaseDt  = $detail['release_date'] ?? null;
        $genresJson = !empty($detail['genres']) ? json_encode($detail['genres']) : null;
        $castJson   = $this->buildCastJson($credits);
        $imdbId     = $extIds['imdb_id'] ?? $detail['imdb_id'] ?? null;
        $now        = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare("
            UPDATE movies SET
                imdb_id       = COALESCE(NULLIF(?, ''), imdb_id),
                overview      = COALESCE(NULLIF(?, ''), overview),
                poster_path   = COALESCE(NULLIF(?, ''), poster_path),
                backdrop_path = COALESCE(NULLIF(?, ''), backdrop_path),
                trailer_key   = COALESCE(NULLIF(?, ''), trailer_key),
                runtime       = COALESCE(NULLIF(?, 0), runtime),
                vote_average  = ?,
                vote_count    = ?,
                release_date  = COALESCE(NULLIF(?, ''), release_date),
                genres_json   = COALESCE(NULLIF(?, ''), genres_json),
                cast_json     = COALESCE(NULLIF(?, ''), cast_json),
                is_showing    = CASE WHEN ? <= CURDATE() THEN 1 ELSE is_showing END,
                is_coming_soon = CASE WHEN ? > CURDATE() THEN 1 ELSE is_coming_soon END,
                last_synced_at = ?,
                updated_at    = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $imdbId, $overview, $poster, $backdrop, $trailerKey,
            $runtime, $voteAvg, $voteCount, $releaseDt, $genresJson, $castJson,
            $releaseDt, $releaseDt, $now, $movieId,
        ]);
    }

    private function findTrailerKey(array $videos): ?string {
        foreach ($videos['results'] ?? [] as $v) {
            if (($v['site'] ?? '') === 'YouTube' && ($v['type'] ?? '') === 'Trailer') {
                return $v['key'];
            }
        }
        foreach ($videos['results'] ?? [] as $v) {
            if (($v['site'] ?? '') === 'YouTube') {
                return $v['key'];
            }
        }
        return null;
    }

    private function buildCastJson(array $credits): ?string {
        $cast = [];
        foreach ($credits['cast'] ?? [] as $i => $c) {
            if ($i >= 10) break;
            $cast[] = [
                'name'      => $c['name'],
                'character' => $c['character'] ?? '',
                'profile'   => $this->buildProfileUrl($c['profile_path']),
                'order'     => $c['order'] ?? 999,
            ];
        }
        usort($cast, fn($a, $b) => ($a['order'] ?? 999) - ($b['order'] ?? 999));
        return !empty($cast) ? json_encode($cast) : null;
    }

    private function slugify(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        $base = trim($text, '-');
        // Ensure unique slug
        $slug = $base;
        $counter = 1;
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM movies WHERE slug = ? AND (tmdb_id IS NULL OR tmdb_id != ?)");
        while (true) {
            $stmt->execute([$slug, 0]);
            if ((int)$stmt->fetchColumn() === 0) break;
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    private function logSync(int $movieId, ?int $tmdbId, string $action, string $status, string $message = ''): void {
        try {
            $stmt = $this->db->prepare("INSERT INTO tmdb_sync_logs (movie_id, tmdb_id, action, status, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$movieId, $tmdbId, $action, $status, $message]);
        } catch (\Exception $e) {
            // Silently fail — logging should never break the app
        }
    }

    private function get(string $url): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $this->apiCalls++;
        usleep(300000); // Rate limit: ~3 req/sec
        if ($code === 200 && $body) {
            return json_decode($body, true) ?? [];
        }
        return [];
    }
}
