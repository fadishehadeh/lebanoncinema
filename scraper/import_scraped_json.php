<?php
/**
 * Import scraped cinema JSON into the local database.
 *
 * Usage: php scraper/import_scraped_json.php [path/to/movies.json]
 *
 * Processes scraped showtime data from VOX, Cinema City, Grand Cinema.
 * Normalizes movie titles, matches to local DB, maps locations to cinema slugs,
 * extracts formats (VIP, IMAX, 4DX, etc.), and inserts showtimes.
 */

require_once __DIR__ . '/../config.php';

$jsonPath = $argv[1] ?? __DIR__ . '/../cinema scrapper/movies.json';
if (!file_exists($jsonPath)) {
    die("File not found: $jsonPath\n");
}

$db = getDbConnection();
$data = json_decode(file_get_contents($jsonPath), true);
$movies = $data['movies'] ?? [];

if (empty($movies)) {
    die("No movies found in JSON\n");
}

echo "=== Importing " . count($movies) . " showtime records ===\n\n";

// ─── Load local movies for matching ───
$localMovies = [];
$stmt = $db->query("SELECT id, title, slug FROM movies");
foreach ($stmt as $r) {
    $localMovies[] = $r;
}

// ─── Cinema location → slug mapping ───
$locationMap = [
    // Cinema City
    'beirut souks cinemacity' => 'cinemacity-souks',
    // Grand Cinema
    'abc achrafieh' => 'grand-abc-achrafieh',
    'grand abc achrafieh' => 'grand-abc-achrafieh',
    'grand abc dbayeh' => 'grand-abc-dbayeh',
    'grand abc verdun' => 'grand-abc-verdun',
    'las salinas' => 'grand-las-salinas',
    'the spot saida' => 'grand-the-spot-saida',
    // VOX
    'city centre beirut' => 'vox-city-centre-beirut',
];

// ─── Format mapping from title suffix ───
$formatSuffixes = [
    '(vip)' => 'VIP',
    '(studio.15)' => 'STUDIO.15',
    '(3d)' => '3D',
    '(imax)' => 'IMAX',
    '(4dx)' => '4DX',
    '(gold)' => 'GOLD',
    '(standard)' => 'Standard',
];

// ─── Location format suffix mapping ───
$locationFormats = [
    '- std' => 'Standard',
    '- vip' => 'VIP',
    '- imax' => 'IMAX',
    '- 4dx' => '4DX',
    '- gold' => 'GOLD',
    '- standard' => 'Standard',
];

// ─── Direct movie title overrides (scraped → our DB title) ───
$titleOverrides = [
    'mortal kombat ii' => 'Mortal Kombat 2',
    'mortal kombat 2' => 'Mortal Kombat 2',
    'asad' => 'ASAD أسد',
    'asad أسد' => 'ASAD أسد',
    'billie eilish   hit me hard and soft the tour' => 'Billie Eilish - Hit Me Hard and Soft: The Tour',
    'billie eilish - hit me hard and soft: the tour' => 'Billie Eilish - Hit Me Hard and Soft: The Tour',
    'billie eilish: hit me hard and soft (3d)' => 'Billie Eilish - Hit Me Hard and Soft: The Tour',
    'billie eilish: hit me hard and soft' => 'Billie Eilish - Hit Me Hard and Soft: The Tour',
    'palestine 36' => 'Palestine 36',
    "palestine '36" => 'Palestine 36',
    'star wars the mandalorian and grogu gc' => 'Star Wars: The Mandalorian and Grogu',
    'star wars the mandalorian and grogu' => 'Star Wars: The Mandalorian and Grogu',
    'star wars: the mandalorian and grogu' => 'Star Wars: The Mandalorian and Grogu',
    'el kalam ala eh (awel leila)' => 'El Kalam Ala Eh (Awel Leila)',
    'el kalam ala eh (awel leila),' => 'El Kalam Ala Eh (Awel Leila)',
    'el kalam ala eh awel leila' => 'El Kalam Ala Eh (Awel Leila)',
    'the devil wears prada 2' => 'The Devil Wears Prada 2',
    'the devil wears prada 2 (vip)' => 'The Devil Wears Prada 2',
    'the sheep detectives (studio.15)' => 'The Sheep Detectives',
    'michael (vip)' => 'Michael',
    'michael (studio.15)' => 'Michael',
    'obsession (vip)' => 'Obsession',
    'obsession (studio.15)' => 'Obsession',
    'the drama (vip)' => 'The Drama',
    'the drama (studio.15)' => 'The Drama',
    '7 dogs' => '7 Dogs',
    'hokum' => 'Hokum',
    'liste de mariage' => 'Liste De Mariage',
    'el kalam ala eh (awel leila)' => 'Liste De Mariage',
    'el kalam ala eh (awel leila),' => 'Liste De Mariage',
    'el kalam ala eh awel leila' => 'Liste De Mariage',
    'asad' => 'Asad',
    'asad أسد' => 'Asad',
    'top gun 40th anniversary' => 'TOP GUN 40TH ANNIVERSARY',
    'animal farm' => 'Animal Farm',
];

// ─── Statistics ───
$stats = [
    'matched' => 0,
    'auto_created' => 0,
    'unmatched_movies' => [],
    'unmatched_locations' => [],
    'inserted' => 0,
    'duplicates' => 0,
    'errors' => 0,
];

// ─── Process each showtime ───
$db->beginTransaction();

foreach ($movies as $item) {
    $rawTitle = trim($item['movie'] ?? '');
    $rawLocation = trim($item['location'] ?? '');
    $rawCinema = trim($item['cinema'] ?? '');
    $date = trim($item['date'] ?? '');
    $time = trim($item['time'] ?? '');
    $format = 'Standard';

    if (!$rawTitle || !$date || !$time) continue;

    // ─── Step 1: Extract format from location ───
    $locationLower = strtolower($rawLocation);
    foreach ($locationFormats as $suffix => $fmt) {
        if (str_ends_with($locationLower, $suffix)) {
            $format = $fmt;
            $rawLocation = trim(substr($rawLocation, 0, -strlen($suffix)));
            break;
        }
    }

    // ─── Step 2: Extract format from title ───
    $titleLower = strtolower($rawTitle);
    foreach ($formatSuffixes as $suffix => $fmt) {
        if (str_ends_with($titleLower, $suffix)) {
            $format = $fmt;
            $rawTitle = trim(substr($rawTitle, 0, -strlen($suffix)));
            break;
        }
    }

    // ─── Step 3: Normalize title ───
    $normalized = trim(preg_replace('/\s+/', ' ', $rawTitle));
    $normalized = rtrim($normalized, ',');

    // Check overrides first
    $lookupKey = strtolower($normalized);
    if (isset($titleOverrides[$lookupKey])) {
        $searchTitle = $titleOverrides[$lookupKey];
    } else {
        $searchTitle = ucwords($normalized);
        // Capitalize properly
        $searchTitle = preg_replace_callback('/\b\w+\b/', function($m) {
            $lower = strtolower($m[0]);
            $exceptions = ['the', 'a', 'an', 'in', 'of', 'for', 'and', 'or', 'but', 'at', 'by', 'to', 'is', 'it'];
            if (in_array($lower, $exceptions)) return $lower;
            return ucfirst($lower);
        }, $normalized);
        $searchTitle = ucfirst($searchTitle);
    }

    // ─── Step 4: Match movie ───
    $movieId = matchMovie($db, $localMovies, $searchTitle, $normalized, $lookupKey);

    if (!$movieId) {
        // Auto-create movie if --auto-create flag is set
        if (in_array('--auto-create', $argv ?? [])) {
            $movieId = autoCreateMovie($db, $normalized, $searchTitle);
            if ($movieId) {
                $stats['auto_created']++;
                echo "  AUTO-CREATED: $normalized (ID $movieId)\n";
            } else {
                $stats['unmatched_movies'][$rawTitle] = ($stats['unmatched_movies'][$rawTitle] ?? 0) + 1;
                continue;
            }
        } else {
            $stats['unmatched_movies'][$rawTitle] = ($stats['unmatched_movies'][$rawTitle] ?? 0) + 1;
            continue;
        }
    }
    $stats['matched']++;

    // ─── Step 5: Map location to cinema slug ───
    $locationKey = strtolower(trim(preg_replace('/\s+/', ' ', $rawLocation)));
    $cinemaSlug = $locationMap[$locationKey] ?? null;

    if (!$cinemaSlug) {
        $stats['unmatched_locations'][$rawLocation] = ($stats['unmatched_locations'][$rawLocation] ?? 0) + 1;
        continue;
    }

    // ─── Step 6: Get cinema ID ───
    static $cinemaCache = [];
    if (!isset($cinemaCache[$cinemaSlug])) {
        $st = $db->prepare("SELECT id FROM cinemas WHERE slug = ? AND is_active = 1");
        $st->execute([$cinemaSlug]);
        $cinemaCache[$cinemaSlug] = $st->fetchColumn() ?: false;
    }
    $cinemaId = $cinemaCache[$cinemaSlug];
    if (!$cinemaId) {
        $stats['errors']++;
        continue;
    }

    // ─── Step 7: Insert showtime ───
    try {
        $stmt = $db->prepare("
            INSERT INTO showtimes (movie_id, cinema_id, show_date, show_time, format, language)
            VALUES (?, ?, ?, ?, ?, 'English')
            ON DUPLICATE KEY UPDATE format = VALUES(format)
        ");
        $stmt->execute([$movieId, $cinemaId, $date, $time, $format]);
        if ($stmt->rowCount() === 1) {
            $stats['inserted']++;
        } else {
            $stats['duplicates']++;
        }
    } catch (PDOException $e) {
        $stats['errors']++;
        echo "  ERROR: {$e->getMessage()}\n";
    }
}

$db->commit();

// ─── Report ───
echo "\n=== Import Summary ===\n";
echo "Total records processed: " . count($movies) . "\n";
echo "Movies matched: {$stats['matched']}\n";
echo "Auto-created: {$stats['auto_created']}\n";
echo "Showtimes inserted: {$stats['inserted']}\n";
echo "Duplicates skipped: {$stats['duplicates']}\n";
echo "Errors: {$stats['errors']}\n";

if (!empty($stats['unmatched_movies'])) {
    echo "\n--- Unmatched Movies ---\n";
    foreach ($stats['unmatched_movies'] as $title => $count) {
        echo "  \"$title\" ($count times)\n";
    }
}

if (!empty($stats['unmatched_locations'])) {
    echo "\n--- Unmatched Locations ---\n";
    foreach ($stats['unmatched_locations'] as $loc => $count) {
        echo "  \"$loc\" ($count times)\n";
    }
}

echo "\nDone.\n";

// ═══════════════════════════════════════════════════════

function matchMovie(PDO $db, array $localMovies, string $searchTitle, string $normalized, string $lookupKey): ?int {
    // 1. Direct match by searchTitle
    foreach ($localMovies as $m) {
        if (strcasecmp($m['title'], $searchTitle) === 0) return (int)$m['id'];
    }

    // 2. Direct match by normalized
    foreach ($localMovies as $m) {
        if (strcasecmp($m['title'], $normalized) === 0) return (int)$m['id'];
    }

    // 3. Slug match
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', trim($normalized)));
    $slug = trim($slug, '-');
    foreach ($localMovies as $m) {
        if ($m['slug'] === $slug) return (int)$m['id'];
    }

    // 4. LIKE match (first 12 chars)
    $like = substr($db->quote(substr($normalized, 0, 12)), 1, -1);
    $stmt = $db->prepare("SELECT id FROM movies WHERE title LIKE ? LIMIT 1");
    $stmt->execute(["%$like%"]);
    if ($r = $stmt->fetch()) return (int)$r['id'];

    // 5. Word intersection: check if most words match
    $words = explode(' ', strtolower($normalized));
    foreach ($localMovies as $m) {
        $mw = explode(' ', strtolower($m['title']));
        $common = array_intersect($words, $mw);
        if (count($common) >= min(2, count($words))) return (int)$m['id'];
    }

    return null;
}

/**
 * Auto-create a movie record when a scraped movie doesn't match the DB.
 * Creates a placeholder; admin can later import TMDb data.
 */
function autoCreateMovie(PDO $db, string $normalized, string $searchTitle): ?int {
    // Use the normalized title for the DB
    $title = ucwords($normalized);
    $title = preg_replace_callback('/\b(The|A|An)\b/i', function($m) { return $m[1]; }, $title);
    
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', trim($title)));
    $slug = trim($slug, '-');
    
    // Check for duplicate slug
    $stmt = $db->prepare("SELECT COUNT(*) FROM movies WHERE slug = ?");
    $stmt->execute([$slug]);
    if ((int)$stmt->fetchColumn() > 0) {
        $slug .= '-' . time();
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO movies (title, slug, status, is_showing, created_at) VALUES (?, ?, 'now_showing', 1, NOW())");
        $stmt->execute([$title, $slug]);
        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        return null;
    }
}
