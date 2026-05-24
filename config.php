<?php
// ─── Database ───
define('DB_HOST', 'localhost');
define('DB_NAME', 'lebanon_cinema');
define('DB_USER', 'root');
define('DB_PASS', '');

// ─── TMDB API ───
define('TMDB_API_KEY', '6bd4ffa3cf62d122c558c4821fe66999');
define('TMDB_BASE_URL', 'https://api.themoviedb.org/3');
define('TMDB_IMG_BASE', 'https://image.tmdb.org/t/p');
define('TMDB_IMG_W500', 'https://image.tmdb.org/t/p/w500');
define('TMDB_IMG_W1280', 'https://image.tmdb.org/t/p/w1280');
define('TMDB_IMG_W185', 'https://image.tmdb.org/t/p/w185');

// ─── Dynamic BASE_URL ───
function detectBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // For non-localhost domains (production), use the host directly
    if ($host !== 'localhost' && $host !== '127.0.0.1') {
        return "$scheme://$host";
    }

    // For localhost, detect the base path from SCRIPT_NAME
    $uri    = $_SERVER['SCRIPT_NAME'] ?? '/';
    $baseDir = dirname(str_replace('\\', '/', $uri));
    $baseDir = rtrim($baseDir, '/');

    if (empty($baseDir) || $baseDir === '.') {
        return "$scheme://$host";
    }
    return "$scheme://$host$baseDir";
}

define('SITE_NAME', 'Lebanon Cinema');
define('BASE_URL', detectBaseUrl());

// ─── Admin credentials ───
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'lebcinema@2026');

// Production override — keep this set for live domain, auto-detect otherwise
if (defined('FORCE_SITE_URL')) {
    define('SITE_URL', FORCE_SITE_URL);
} else {
    define('SITE_URL', BASE_URL);
}

/**
 * Generate a full URL for any path.
 * Always use this for links, assets, canonical URLs, and redirects.
 *
 * Usage:
 *   url('/movies')           →  http://localhost/lebanoncinema/public/movies
 *   url('/movies/inception') →  http://localhost/lebanoncinema/public/movies/inception
 *   url()                    →  http://localhost/lebanoncinema/public
 */
function url(string $path = ''): string {
    $base = rtrim(SITE_URL, '/');
    $path = ltrim($path, '/');
    return $path ? "$base/$path" : $base;
}

/**
 * Generate a relative link path (for local href attributes).
 * Use this for internal site links.
 *
 * Usage:
 *   link('/movies/inception')  →  /lebanoncinema/public/movies/inception
 */
function _link(string $path = ''): string {
    static $prefix = null;
    if ($prefix === null) {
        $base = rtrim(SITE_URL, '/');
        $parts = parse_url($base);
        $prefix = $parts['path'] ?? '';
    }
    if ($path === '' || $path === '/') {
        return $prefix ?: '/';
    }
    return $prefix . '/' . ltrim($path, '/');
}

/**
 * Shortcut: _link() + htmlspecialchars() in one call.
 */
function e_link(string $path = ''): string {
    return htmlspecialchars(_link($path));
}

function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    return $pdo;
}

/**
 * Map new TMDb columns to old template variable names.
 * Call this before passing movie data to templates for backward compat.
 */
function mapMovieRow(array $m): array {
    $m['poster_url']  = $m['poster_path'] ?? $m['poster_url'] ?? '';
    $m['backdrop']    = $m['backdrop_path'] ?? $m['poster_path'] ?? '';
    $m['synopsis']    = $m['overview'] ?? $m['synopsis'] ?? '';
    $m['rating']      = (!empty($m['vote_average'])) ? number_format((float)$m['vote_average'], 1) : ($m['rating'] ?? '');
    $m['trailer_url'] = !empty($m['trailer_key']) ? 'https://www.youtube.com/watch?v=' . $m['trailer_key'] : ($m['trailer_url'] ?? '');
    $m['genres']      = $m['genres'] ?? '';
    if (!empty($m['genres_json'])) {
        $g = json_decode($m['genres_json'], true);
        if (!empty($g) && is_array($g)) {
            $m['genres'] = implode(', ', array_column($g, 'name'));
        }
    }
    $m['genres_list'] = !empty($m['genres']) ? array_map('trim', explode(',', $m['genres'])) : [];
    return $m;
}

/**
 * Autoload TmdbService when needed.
 */
function getTmdbService(): TmdbService {
    require_once __DIR__ . '/public/includes/services/TmdbService.php';
    return new TmdbService(getDbConnection());
}

/**
 * Run schema migrations (idempotent — safe to call repeatedly).
 */
function runSchemaMigrations(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS tmdb_sync_logs (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            movie_id   INT DEFAULT NULL,
            tmdb_id    INT DEFAULT NULL,
            action     VARCHAR(20) DEFAULT NULL,
            status     VARCHAR(20) DEFAULT NULL,
            message    TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Add new columns to movies table if missing
    $cols = [
        'overview TEXT DEFAULT NULL AFTER slug',
        'poster_path VARCHAR(500) DEFAULT NULL AFTER overview',
        'backdrop_path VARCHAR(500) DEFAULT NULL AFTER poster_path',
        'trailer_key VARCHAR(20) DEFAULT NULL AFTER backdrop_path',
        'vote_average DECIMAL(4,1) DEFAULT NULL AFTER trailer_key',
        'vote_count INT DEFAULT NULL AFTER vote_average',
        'genres_json TEXT DEFAULT NULL AFTER vote_count',
        'cast_json TEXT DEFAULT NULL AFTER genres_json',
        'api_source VARCHAR(20) DEFAULT NULL AFTER cast_json',
        'is_showing TINYINT(1) DEFAULT 0 AFTER api_source',
        'is_coming_soon TINYINT(1) DEFAULT 0 AFTER is_showing',
        'is_featured TINYINT(1) DEFAULT 0 AFTER is_coming_soon',
        'local_trending_score INT DEFAULT 0 AFTER is_featured',
        'seo_title VARCHAR(255) DEFAULT NULL AFTER local_trending_score',
        'seo_description TEXT DEFAULT NULL AFTER seo_title',
        'last_synced_at TIMESTAMP NULL DEFAULT NULL AFTER seo_description',
    ];
    // Add runtime column (TMDB returns 'runtime', existing DB has 'duration_min')
    try { $db->exec("ALTER TABLE movies ADD COLUMN runtime INT DEFAULT NULL AFTER trailer_key"); } catch (\Exception $e) {}
    
    foreach ($cols as $col) {
        try {
            $db->exec("ALTER TABLE movies ADD COLUMN IF NOT EXISTS $col");
        } catch (\Exception $e) {
            // Column may already exist
        }
    }
}
