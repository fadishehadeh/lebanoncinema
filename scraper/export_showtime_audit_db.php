<?php
require_once __DIR__ . '/../config.php';

try {
    $db = getDbConnection();
    $stmt = $db->query("
        SELECT
            m.id AS movie_id,
            m.title AS movie_title,
            m.slug AS movie_slug,
            m.status AS movie_status,
            c.id AS cinema_id,
            c.name AS cinema_name,
            c.slug AS cinema_slug,
            ch.name AS chain_name,
            s.show_date,
            s.show_time,
            s.format,
            s.booking_url
        FROM showtimes s
        JOIN movies m ON m.id = s.movie_id
        JOIN cinemas c ON c.id = s.cinema_id
        JOIN chains ch ON ch.id = c.chain_id
        WHERE s.show_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)
        ORDER BY ch.name, m.title, c.name, s.show_date, s.show_time
    ");

    $rows = [];
    foreach ($stmt as $row) {
        $titleKey = function_exists('catalogPublicTitleKey')
            ? catalogPublicTitleKey((string) $row['movie_title'])
            : strtolower(trim((string) $row['movie_title']));

        $rows[] = [
            'movie_id' => (int) $row['movie_id'],
            'movie_title' => $row['movie_title'],
            'movie_slug' => $row['movie_slug'],
            'movie_status' => $row['movie_status'],
            'movie_key' => $titleKey,
            'cinema_id' => (int) $row['cinema_id'],
            'cinema_name' => $row['cinema_name'],
            'cinema_slug' => $row['cinema_slug'],
            'chain_name' => $row['chain_name'],
            'show_date' => $row['show_date'],
            'show_time' => substr((string) $row['show_time'], 0, 5),
            'format' => $row['format'] ?: 'Standard',
            'booking_url' => $row['booking_url'] ?: '',
        ];
    }

    echo json_encode([
        'generated_at' => date(DATE_ATOM),
        'rows' => $rows,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(1);
}
