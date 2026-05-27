<?php
/**
 * Import scraped cinema JSON into the local database.
 *
 * Usage: php scraper/import_scraped_json.php [path/to/movies.json]
 *
 * Reconciles scraped showtimes for the imported movie/cinema/date scope:
 * - normalizes titles and formats
 * - maps source locations to local cinema slugs
 * - deletes stale rows in the affected scope
 * - inserts current rows using the showtime unique key
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

$localMovies = [];
$stmt = $db->query("SELECT id, title, slug FROM movies");
foreach ($stmt as $row) {
    $localMovies[] = $row;
}

$locationMap = [
    'beirut souks cinemacity' => 'cinemacity-souks',
    'abc achrafieh' => 'grand-abc-achrafieh',
    'grand abc achrafieh' => 'grand-abc-achrafieh',
    'grand abc dbayeh' => 'grand-abc-dbayeh',
    'grand abc verdun' => 'grand-abc-verdun',
    'las salinas' => 'grand-las-salinas',
    'the spot saida' => 'grand-the-spot-saida',
    'city centre beirut' => 'vox-city-centre-beirut',
];

$formatSuffixes = [
    '(vip)' => 'VIP',
    '(studio.15)' => 'STUDIO.15',
    '(3d)' => '3D',
    '(imax)' => 'IMAX',
    '(4dx)' => '4DX',
    '(gold)' => 'GOLD',
    '(standard)' => 'Standard',
];

$locationFormats = [
    '- std' => 'Standard',
    '- vip' => 'VIP',
    '- imax' => 'IMAX',
    '- 4dx' => '4DX',
    '- gold' => 'GOLD',
    '- standard' => 'Standard',
    '- offline' => 'Standard',
];

$titleOverrides = [
    'mortal kombat ii' => 'Mortal Kombat 2',
    'mortal kombat 2' => 'Mortal Kombat II',
    'asad' => 'Asad',
    'asad Ø£Ø³Ø¯' => 'Asad',
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
    'top gun 40th anniversary' => 'TOP GUN 40TH ANNIVERSARY',
    'animal farm' => 'Animal Farm',
];

$stats = [
    'matched' => 0,
    'auto_created' => 0,
    'unmatched_movies' => [],
    'unmatched_locations' => [],
    'inserted' => 0,
    'duplicates' => 0,
    'deleted_stale' => 0,
    'errors' => 0,
];

$normalizedRows = [];

foreach ($movies as $item) {
    $rawTitle = trim($item['movie'] ?? '');
    $rawLocation = trim($item['location'] ?? '');
    $date = trim($item['date'] ?? '');
    $time = trim($item['time'] ?? '');
    $format = 'Standard';

    if ($rawTitle === '' || $date === '' || $time === '') {
        continue;
    }

    $locationLower = strtolower($rawLocation);
    foreach ($locationFormats as $suffix => $mappedFormat) {
        if (str_ends_with($locationLower, $suffix)) {
            $format = $mappedFormat;
            $rawLocation = trim(substr($rawLocation, 0, -strlen($suffix)));
            break;
        }
    }

    $titleLower = strtolower($rawTitle);
    foreach ($formatSuffixes as $suffix => $mappedFormat) {
        if (str_ends_with($titleLower, $suffix)) {
            $format = $mappedFormat;
            $rawTitle = trim(substr($rawTitle, 0, -strlen($suffix)));
            break;
        }
    }

    $normalizedTitle = trim((string) preg_replace('/\s+/', ' ', rtrim($rawTitle, ',')));
    $lookupKey = strtolower($normalizedTitle);

    if (isset($titleOverrides[$lookupKey])) {
        $searchTitle = $titleOverrides[$lookupKey];
    } else {
        $searchTitle = ucfirst(preg_replace_callback('/\b\w+\b/', function ($match) {
            $lower = strtolower($match[0]);
            $exceptions = ['the', 'a', 'an', 'in', 'of', 'for', 'and', 'or', 'but', 'at', 'by', 'to', 'is', 'it'];
            if (in_array($lower, $exceptions, true)) {
                return $lower;
            }
            return ucfirst($lower);
        }, $normalizedTitle));
    }

    $movieId = matchMovie($db, $localMovies, $searchTitle, $normalizedTitle, $lookupKey);
    if (!$movieId) {
        if (in_array('--auto-create', $argv ?? [], true)) {
            $movieId = autoCreateMovie($db, $normalizedTitle);
            if ($movieId) {
                $stats['auto_created']++;
                $localMovies[] = ['id' => $movieId, 'title' => $searchTitle, 'slug' => strtolower((string) preg_replace('/[^a-z0-9]+/', '-', $normalizedTitle))];
            }
        }

        if (!$movieId) {
            $stats['unmatched_movies'][$rawTitle] = ($stats['unmatched_movies'][$rawTitle] ?? 0) + 1;
            continue;
        }
    }
    $stats['matched']++;

    $locationKey = strtolower(trim((string) preg_replace('/\s+/', ' ', $rawLocation)));
    $cinemaSlug = $locationMap[$locationKey] ?? null;
    if (!$cinemaSlug) {
        $stats['unmatched_locations'][$rawLocation] = ($stats['unmatched_locations'][$rawLocation] ?? 0) + 1;
        continue;
    }

    static $cinemaCache = [];
    if (!array_key_exists($cinemaSlug, $cinemaCache)) {
        $cinemaStmt = $db->prepare("SELECT id FROM cinemas WHERE slug = ? AND is_active = 1");
        $cinemaStmt->execute([$cinemaSlug]);
        $cinemaCache[$cinemaSlug] = $cinemaStmt->fetchColumn() ?: false;
    }
    $cinemaId = $cinemaCache[$cinemaSlug];
    if (!$cinemaId) {
        $stats['errors']++;
        continue;
    }

    $normalizedRows[] = [
        'movie_id' => (int) $movieId,
        'cinema_id' => (int) $cinemaId,
        'show_date' => $date,
        'show_time' => $time,
        'format' => $format ?: 'Standard',
    ];
}

$incomingKeys = [];
$dedupedRows = [];
$cinemaScopeIds = [];

foreach ($normalizedRows as $row) {
    $key = buildShowtimeKey($row['movie_id'], $row['cinema_id'], $row['show_date'], $row['show_time'], $row['format']);
    if (isset($incomingKeys[$key])) {
        $stats['duplicates']++;
        continue;
    }

    $incomingKeys[$key] = true;
    $dedupedRows[] = $row;
    $cinemaScopeIds[$row['cinema_id']] = true;
}

$db->beginTransaction();

try {
    $windowStart = date('Y-m-d');
    $windowEnd = date('Y-m-d', strtotime('+6 days'));

    if ($cinemaScopeIds) {
        $cinemaIds = array_map('intval', array_keys($cinemaScopeIds));
        $placeholders = implode(',', array_fill(0, count($cinemaIds), '?'));
        $selectStmt = $db->prepare("
            SELECT id, movie_id, cinema_id, show_date, show_time, format
            FROM showtimes
            WHERE cinema_id IN ($placeholders) AND show_date BETWEEN ? AND ?
        ");
        $selectStmt->execute([...$cinemaIds, $windowStart, $windowEnd]);

        $deleteIds = [];
        foreach ($selectStmt->fetchAll() as $existing) {
            $existingKey = buildShowtimeKey(
                (int) $existing['movie_id'],
                (int) $existing['cinema_id'],
                $existing['show_date'],
                substr((string) $existing['show_time'], 0, 5),
                $existing['format'] ?: 'Standard'
            );

            if (!isset($incomingKeys[$existingKey])) {
                $deleteIds[] = (int) $existing['id'];
            }
        }

        if ($deleteIds) {
            $deletePlaceholders = implode(',', array_fill(0, count($deleteIds), '?'));
            $deleteStmt = $db->prepare("DELETE FROM showtimes WHERE id IN ($deletePlaceholders)");
            $deleteStmt->execute($deleteIds);
            $stats['deleted_stale'] += count($deleteIds);
        }
    }

    $insertStmt = $db->prepare("
        INSERT INTO showtimes (movie_id, cinema_id, show_date, show_time, format, language)
        VALUES (?, ?, ?, ?, ?, 'English')
        ON DUPLICATE KEY UPDATE format = VALUES(format)
    ");

    foreach ($dedupedRows as $row) {
        $insertStmt->execute([
            $row['movie_id'],
            $row['cinema_id'],
            $row['show_date'],
            $row['show_time'],
            $row['format'],
        ]);

        if ($insertStmt->rowCount() === 1) {
            $stats['inserted']++;
        }
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

echo "\n=== Import Summary ===\n";
echo "Total records processed: " . count($movies) . "\n";
echo "Movies matched: {$stats['matched']}\n";
echo "Auto-created: {$stats['auto_created']}\n";
echo "Showtimes inserted: {$stats['inserted']}\n";
echo "Duplicates skipped: {$stats['duplicates']}\n";
echo "Stale rows deleted: {$stats['deleted_stale']}\n";
echo "Errors: {$stats['errors']}\n";

if (!empty($stats['unmatched_movies'])) {
    echo "\n--- Unmatched Movies ---\n";
    foreach ($stats['unmatched_movies'] as $title => $count) {
        echo "  \"$title\" ($count times)\n";
    }
}

if (!empty($stats['unmatched_locations'])) {
    echo "\n--- Unmatched Locations ---\n";
    foreach ($stats['unmatched_locations'] as $location => $count) {
        echo "  \"$location\" ($count times)\n";
    }
}

echo "\nDone.\n";

function buildShowtimeKey(int $movieId, int $cinemaId, string $date, string $time, string $format): string
{
    return implode('|', [$movieId, $cinemaId, $date, $time, strtoupper(trim($format ?: 'Standard'))]);
}

function matchMovie(PDO $db, array $localMovies, string $searchTitle, string $normalized, string $lookupKey): ?int
{
    foreach ($localMovies as $movie) {
        if (strcasecmp($movie['title'], $searchTitle) === 0) {
            return (int) $movie['id'];
        }
    }

    foreach ($localMovies as $movie) {
        if (strcasecmp($movie['title'], $normalized) === 0) {
            return (int) $movie['id'];
        }
    }

    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($normalized)), '-');
    foreach ($localMovies as $movie) {
        if ($movie['slug'] === $slug) {
            return (int) $movie['id'];
        }
    }

    $stmt = $db->prepare("SELECT id FROM movies WHERE title LIKE ? LIMIT 1");
    $stmt->execute(['%' . substr($normalized, 0, 12) . '%']);
    if ($row = $stmt->fetch()) {
        return (int) $row['id'];
    }

    $words = explode(' ', strtolower($normalized));
    foreach ($localMovies as $movie) {
        $candidateWords = explode(' ', strtolower($movie['title']));
        $common = array_intersect($words, $candidateWords);
        if (count($common) >= min(2, count($words))) {
            return (int) $movie['id'];
        }
    }

    return null;
}

function autoCreateMovie(PDO $db, string $normalized): ?int
{
    $title = ucwords($normalized);
    $title = preg_replace_callback('/\b(The|A|An)\b/i', function ($match) {
        return $match[1];
    }, $title);

    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');

    $stmt = $db->prepare("SELECT COUNT(*) FROM movies WHERE slug = ?");
    $stmt->execute([$slug]);
    if ((int) $stmt->fetchColumn() > 0) {
        $slug .= '-' . time();
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO movies (title, slug, status, is_showing, created_at)
            VALUES (?, ?, 'now_showing', 1, NOW())
        ");
        $stmt->execute([$title, $slug]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        return null;
    }
}
