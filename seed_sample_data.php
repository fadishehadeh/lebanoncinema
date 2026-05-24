<?php
require_once 'config.php';

$db = getDbConnection();

// Insert sample movies
$movies = [
    ['title' => 'The Dark Knight', 'slug' => 'the-dark-knight', 'duration_min' => 152, 'rating' => 'PG-13', 'genres' => 'Action, Crime, Drama', 'status' => 'now_showing'],
    ['title' => 'Inception', 'slug' => 'inception', 'duration_min' => 148, 'rating' => 'PG-13', 'genres' => 'Action, Sci-Fi, Thriller', 'status' => 'now_showing'],
    ['title' => 'Interstellar', 'slug' => 'interstellar', 'duration_min' => 169, 'rating' => 'PG-13', 'genres' => 'Adventure, Drama, Sci-Fi', 'status' => 'now_showing'],
    ['title' => 'The Matrix', 'slug' => 'the-matrix', 'duration_min' => 136, 'rating' => 'R', 'genres' => 'Action, Sci-Fi', 'status' => 'now_showing'],
    ['title' => 'Avatar', 'slug' => 'avatar', 'duration_min' => 162, 'rating' => 'PG-13', 'genres' => 'Action, Adventure, Sci-Fi', 'status' => 'now_showing'],
];

echo "Inserting sample movies...\n";
foreach ($movies as $movie) {
    $stmt = $db->prepare("
        INSERT IGNORE INTO movies (title, slug, duration_min, rating, genres, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $movie['title'],
        $movie['slug'],
        $movie['duration_min'],
        $movie['rating'],
        $movie['genres'],
        $movie['status'],
    ]);
}

// Get movie IDs
$stmt = $db->query("SELECT id, slug FROM movies");
$movieIds = [];
foreach ($stmt->fetchAll() as $row) {
    $movieIds[$row['slug']] = $row['id'];
}

// Get cinema IDs
$stmt = $db->query("SELECT id FROM cinemas WHERE is_active = 1 LIMIT 5");
$cinemaIds = [];
foreach ($stmt->fetchAll() as $row) {
    $cinemaIds[] = $row['id'];
}

// Insert sample showtimes for the next 7 days
echo "Inserting sample showtimes...\n";
$movieSlugs = array_keys($movieIds);
$showTimes = ['10:00:00', '13:00:00', '16:00:00', '19:00:00', '21:30:00'];
$formats = ['Standard', 'IMAX'];

for ($day = 0; $day < 7; $day++) {
    $date = date('Y-m-d', strtotime("+$day days"));

    foreach ($movieSlugs as $movieSlug) {
        $movieId = $movieIds[$movieSlug];

        // Assign movies to cinemas
        foreach ($cinemaIds as $cinemaId) {
            // Only add some showtimes (not all combinations)
            if (rand(0, 1) === 1) {
                $format = $formats[rand(0, count($formats) - 1)];
                $time = $showTimes[rand(0, count($showTimes) - 1)];

                $stmt = $db->prepare("
                    INSERT IGNORE INTO showtimes (movie_id, cinema_id, show_date, show_time, format)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$movieId, $cinemaId, $date, $time, $format]);
            }
        }
    }
}

// Verify data was inserted
$stmt = $db->query("SELECT COUNT(*) as cnt FROM movies");
$movieCount = $stmt->fetch()['cnt'];

$stmt = $db->query("SELECT COUNT(*) as cnt FROM showtimes");
$showtimeCount = $stmt->fetch()['cnt'];

echo "\n✓ Sample data inserted:\n";
echo "  - $movieCount movies\n";
echo "  - $showtimeCount showtimes\n";
