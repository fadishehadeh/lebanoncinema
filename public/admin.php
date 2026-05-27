<?php
/**
 * LebanonCinema Admin Panel
 *
 * Features:
 *   - List all movies
 *   - Search TMDb and import
 *   - Edit movie metadata
 *   - Manage showtimes
 *   - Manage cinemas
 *   - Trigger TMDb sync
 *   - View sync logs
 *
 * Access: Basic HTTP auth placeholder.
 * TODO: Add proper authentication before production.
 */
require_once __DIR__ . '/../config.php';

$db = getDbConnection();
$action = $_GET['action'] ?? 'movies';

// Handle POST actions
$message = '';
$error = '';

// ─── TMDb Search AJAX ───
if ($action === 'tmdb-search' && isset($_GET['q'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['q']);
    if (strlen($q) < 2) { echo json_encode([]); exit; }
    $url = TMDB_BASE_URL . '/search/movie?api_key=' . TMDB_API_KEY . '&query=' . urlencode($q) . '&language=en-US&page=1&region=LB';
    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => false]);
    $body = curl_exec($ch); curl_close($ch);
    echo $body ?: '[]';
    exit;
}

// ─── Import from TMDb ───
if ($action === 'tmdb-import' && isset($_POST['tmdb_id'])) {
    $tmdbId = (int)$_POST['tmdb_id'];
    // Run sync for this single movie
    require_once __DIR__ . '/../scraper/tmdb_sync.php';
    $sync = new TmdbSync($db);
    $ref = new ReflectionClass($sync);
    $method = $ref->getMethod('syncMovie');
    $method->setAccessible(true);
    $method->invoke($sync, $tmdbId, ['id' => $tmdbId, 'title' => '']);
    $message = "Movie imported/updated from TMDb (ID: $tmdbId)";
}

// ─── Save movie metadata ───
if ($action === 'save-movie' && isset($_POST['movie_id'])) {
    $id = (int)$_POST['movie_id'];
    $title = $_POST['title'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $sync = $_POST['synopsis'] ?? '';
    $rating = $_POST['rating'] ?? '';
    $genres = $_POST['genres'] ?? '';
    $duration = $_POST['duration_min'] ? (int)$_POST['duration_min'] : null;
    $status = $_POST['status'] ?? 'now_showing';
    $stmt = $db->prepare("UPDATE movies SET title=?, slug=?, synopsis=?, rating=?, genres=?, duration_min=?, status=? WHERE id=?");
    $stmt->execute([$title, $slug, $sync, $rating, $genres, $duration, $status, $id]);
    $message = "Movie updated: $title";
}

// ─── Add showtime ───
if ($action === 'add-showtime' && isset($_POST['movie_id'], $_POST['cinema_id'], $_POST['show_date'], $_POST['show_time'])) {
    $stmt = $db->prepare("INSERT INTO showtimes (movie_id, cinema_id, show_date, show_time, format, language, booking_url) VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE format=VALUES(format), language=VALUES(language), booking_url=VALUES(booking_url)");
    $stmt->execute([
        (int)$_POST['movie_id'], (int)$_POST['cinema_id'],
        $_POST['show_date'], $_POST['show_time'],
        $_POST['format'] ?? 'Standard', $_POST['language'] ?? 'English',
        $_POST['booking_url'] ?? null
    ]);
    $message = "Showtime added";
}

// ─── Delete showtime ───
if ($action === 'delete-showtime' && isset($_GET['id'])) {
    $stmt = $db->prepare("DELETE FROM showtimes WHERE id=?");
    $stmt->execute([(int)$_GET['id']]);
    $message = "Showtime deleted";
}

// ─── Run full sync ───
if ($action === 'run-sync') {
    $output = shell_exec('php ' . escapeshellarg(__DIR__ . '/../scraper/tmdb_sync.php') . ' 2>&1');
    $message = "Sync completed. Output: " . substr($output, 0, 500);
}

// ─── Fetch data ───
$movies = $db->query("SELECT id, title, slug, tmdb_id, imdb_id, rating, genres, status, last_synced_at FROM movies ORDER BY title ASC")->fetchAll();
$cinemas = $db->query("SELECT c.id, c.name, c.slug, c.city, ch.name AS chain_name FROM cinemas c JOIN chains ch ON c.chain_id = ch.id ORDER BY ch.name, c.name")->fetchAll();
$chains = $db->query("SELECT id, name FROM chains ORDER BY name")->fetchAll();
$showtimes = $db->query("SELECT s.id, m.title AS movie_title, c.name AS cinema_name, s.show_date, s.show_time, s.format FROM showtimes s JOIN movies m ON s.movie_id = m.id JOIN cinemas c ON s.cinema_id = c.id WHERE s.show_date >= CURDATE() ORDER BY s.show_date, s.show_time LIMIT 50")->fetchAll();
$logs = $db->query("SELECT * FROM scraper_log ORDER BY ran_at DESC LIMIT 20")->fetchAll();

// Get last sync info
$lastSync = $db->query("SELECT MAX(last_synced_at) AS last_sync FROM movies WHERE api_source = 'tmdb'")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-HWSXMBHVRG"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-HWSXMBHVRG');
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0B0B0F; color: #f0f0f0; font-size: 14px; line-height: 1.5; }
        .admin-wrap { max-width: 1400px; margin: 0 auto; padding: 24px; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 24px; color: #fff; }
        h2 { font-size: 1.1rem; font-weight: 600; margin-bottom: 16px; color: #FF3D71; }
        .nav { display: flex; gap: 4px; margin-bottom: 32px; flex-wrap: wrap; background: #15151B; padding: 8px; border-radius: 12px; }
        .nav a { padding: 8px 16px; border-radius: 8px; color: #888; text-decoration: none; font-weight: 500; transition: all 0.15s; }
        .nav a:hover { color: #fff; background: #1B1B22; }
        .nav a.active { background: #FF3D71; color: #000; }
        .msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-weight: 500; }
        .msg.success { background: rgba(0,200,83,0.12); color: #00C853; border: 1px solid rgba(0,200,83,0.2); }
        .msg.error { background: rgba(255,61,113,0.12); color: #FF3D71; border: 1px solid rgba(255,61,113,0.2); }
        table { width: 100%; border-collapse: collapse; background: #15151B; border-radius: 12px; overflow: hidden; }
        th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.06); }
        th { background: #1B1B22; font-weight: 600; color: #888; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
        td { font-size: 0.85rem; }
        tr:hover td { background: rgba(255,255,255,0.03); }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 8px; font-weight: 600; font-size: 0.8rem; cursor: pointer; transition: all 0.15s; border: none; font-family: inherit; text-decoration: none; }
        .btn-primary { background: #FF3D71; color: #000; }
        .btn-primary:hover { opacity: 0.85; }
        .btn-outline { background: transparent; border: 1px solid rgba(255,255,255,0.15); color: #f0f0f0; }
        .btn-outline:hover { border-color: #FF3D71; color: #FF3D71; }
        .btn-sm { padding: 4px 10px; font-size: 0.7rem; }
        .btn-danger { background: #d32f2f; color: #fff; }
        .btn-danger:hover { opacity: 0.8; }
        input, select, textarea { background: #1B1B22; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 8px 12px; color: #f0f0f0; font-family: inherit; font-size: 0.85rem; width: 100%; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #FF3D71; }
        label { display: block; font-size: 0.75rem; font-weight: 600; color: #888; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px; }
        .card { background: #15151B; border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .stat-card { background: #15151B; border-radius: 12px; padding: 16px; text-align: center; }
        .stat-num { font-size: 1.8rem; font-weight: 700; color: #FF3D71; }
        .stat-label { font-size: 0.75rem; color: #888; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.05em; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 600; }
        .badge-green { background: rgba(0,200,83,0.15); color: #00C853; }
        .badge-yellow { background: rgba(255,171,0,0.15); color: #FFAB00; }
        .badge-red { background: rgba(255,61,113,0.15); color: #FF3D71; }
        .tmdb-results { max-height: 400px; overflow-y: auto; border: 1px solid rgba(255,255,255,0.06); border-radius: 8px; margin-top: 8px; }
        .tmdb-result { display: flex; gap: 12px; padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.04); cursor: pointer; transition: background 0.1s; }
        .tmdb-result:hover { background: rgba(255,61,113,0.06); }
        .tmdb-result img { width: 40px; height: 60px; border-radius: 4px; object-fit: cover; background: #1B1B22; }
        .tmdb-result-info { flex: 1; }
        .tmdb-result-title { font-weight: 600; font-size: 0.85rem; }
        .tmdb-result-meta { font-size: 0.75rem; color: #888; }
        .tmdb-result .btn { flex-shrink: 0; }
        .flex { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .mt-2 { margin-top: 12px; }
        .mb-2 { margin-bottom: 12px; }
        .w-full { width: 100%; }
        @media (max-width: 768px) { .admin-wrap { padding: 16px; } .nav { gap: 2px; } .nav a { padding: 6px 10px; font-size: 0.8rem; } }
    </style>
</head>
<body>
<div class="admin-wrap">
    <div class="flex" style="justify-content:space-between;">
        <h1><?= SITE_NAME ?> Admin</h1>
        <div class="flex">
            <a href="<?= _link('/') ?>" class="btn btn-outline btn-sm">View Site</a>
            <?php if ($lastSync && $lastSync['last_sync']): ?>
            <span style="font-size:0.75rem;color:#888;">Last sync: <?= $lastSync['last_sync'] ?></span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?><div class="msg success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="nav">
        <a href="?action=movies" class="<?= $action === 'movies' ? 'active' : '' ?>">Movies</a>
        <a href="?action=showtimes" class="<?= $action === 'showtimes' ? 'active' : '' ?>">Showtimes</a>
        <a href="?action=cinemas" class="<?= $action === 'cinemas' ? 'active' : '' ?>">Cinemas</a>
        <a href="?action=import" class="<?= $action === 'import' ? 'active' : '' ?>">TMDb Import</a>
        <a href="?action=sync" class="<?= $action === 'sync' ? 'active' : '' ?>">Sync</a>
        <a href="?action=logs" class="<?= $action === 'logs' ? 'active' : '' ?>">Logs</a>
    </div>

    <!-- Stats -->
    <div class="stats">
        <div class="stat-card"><div class="stat-num"><?= count($movies) ?></div><div class="stat-label">Movies</div></div>
        <div class="stat-card"><div class="stat-num"><?= count($cinemas) ?></div><div class="stat-label">Cinemas</div></div>
        <div class="stat-card"><div class="stat-num"><?= count($showtimes) ?></div><div class="stat-label">Upcoming Showtimes</div></div>
        <div class="stat-card"><div class="stat-num"><?= count($db->query("SELECT DISTINCT city FROM cinemas WHERE is_active=1")->fetchAll()) ?></div><div class="stat-label">Cities</div></div>
    </div>

    <?php if ($action === 'movies'): ?>
    <div class="flex" style="justify-content:space-between;margin-bottom:16px;">
        <h2>All Movies</h2>
        <a href="?action=import" class="btn btn-primary btn-sm">Import from TMDb</a>
    </div>
    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr><th>ID</th><th>Title</th><th>Slug</th><th>TMDb</th><th>IMDb</th><th>Rating</th><th>Genres</th><th>Status</th><th>Synced</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($movies as $m): ?>
            <tr>
                <td><?= $m['id'] ?></td>
                <td><strong><?= htmlspecialchars($m['title']) ?></strong></td>
                <td style="font-size:0.75rem;color:#888;"><?= htmlspecialchars($m['slug']) ?></td>
                <td><?= $m['tmdb_id'] ? '<a href="https://www.themoviedb.org/movie/' . $m['tmdb_id'] . '" target="_blank" style="color:#5E8BFF;">' . $m['tmdb_id'] . '</a>' : '-' ?></td>
                <td><?= $m['imdb_id'] ? '<a href="https://www.imdb.com/title/' . $m['imdb_id'] . '/" target="_blank" style="color:#f5c518;">' . $m['imdb_id'] . '</a>' : '-' ?></td>
                <td><?= htmlspecialchars($m['rating'] ?? '-') ?></td>
                <td style="font-size:0.75rem;"><?= htmlspecialchars(substr($m['genres'] ?? '', 0, 40)) ?></td>
                <td><span class="badge badge-<?= $m['status'] === 'now_showing' ? 'green' : ($m['status'] === 'coming_soon' ? 'yellow' : 'red') ?>"><?= $m['status'] ?></span></td>
                <td style="font-size:0.7rem;color:#888;"><?= $m['last_synced_at'] ? date('M j', strtotime($m['last_synced_at'])) : '-' ?></td>
                <td><a href="?action=edit-movie&id=<?= $m['id'] ?>" class="btn btn-outline btn-sm">Edit</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <?php if ($action === 'edit-movie' && isset($_GET['id'])):
        $m = $db->prepare("SELECT * FROM movies WHERE id=?")->execute([(int)$_GET['id']]) ? $db->query("SELECT * FROM movies WHERE id=" . (int)$_GET['id']) : null;
        $m = $m ? $m->fetch() : null;
        if (!$m): ?><div class="msg error">Movie not found</div>
        <?php else: ?>
    <h2>Edit: <?= htmlspecialchars($m['title']) ?></h2>
    <div class="card">
        <form method="post" action="?action=save-movie">
            <input type="hidden" name="movie_id" value="<?= $m['id'] ?>">
            <div class="form-row">
                <div><label>Title</label><input name="title" value="<?= htmlspecialchars($m['title']) ?>"></div>
                <div><label>Slug</label><input name="slug" value="<?= htmlspecialchars($m['slug']) ?>"></div>
                <div><label>Rating</label><input name="rating" value="<?= htmlspecialchars($m['rating'] ?? '') ?>"></div>
                <div><label>Duration (min)</label><input name="duration_min" value="<?= $m['duration_min'] ?? '' ?>"></div>
            </div>
            <div class="form-row">
                <div><label>Genres (comma-separated)</label><input name="genres" value="<?= htmlspecialchars($m['genres'] ?? '') ?>"></div>
                <div><label>Status</label><select name="status"><option value="now_showing" <?= $m['status'] === 'now_showing' ? 'selected' : '' ?>>Now Showing</option><option value="coming_soon" <?= $m['status'] === 'coming_soon' ? 'selected' : '' ?>>Coming Soon</option><option value="ended" <?= $m['status'] === 'ended' ? 'selected' : '' ?>>Ended</option></select></div>
            </div>
            <div><label>Synopsis</label><textarea name="synopsis" rows="3"><?= htmlspecialchars($m['synopsis'] ?? '') ?></textarea></div>
            <div class="mt-2"><button type="submit" class="btn btn-primary">Save Movie</button></div>
        </form>
    </div>
    <?php if ($m['imdb_id']): ?><p style="font-size:0.8rem;color:#888;">IMDb: <a href="https://www.imdb.com/title/<?= htmlspecialchars($m['imdb_id']) ?>/" target="_blank" style="color:#f5c518;"><?= htmlspecialchars($m['imdb_id']) ?></a></p><?php endif; ?>
    <?php endif; endif; ?>

    <?php if ($action === 'showtimes'): ?>
    <h2>Upcoming Showtimes</h2>
    <div style="overflow-x:auto;">
    <table>
        <thead><tr><th>Movie</th><th>Cinema</th><th>Date</th><th>Time</th><th>Format</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($showtimes as $st): ?>
            <tr>
                <td><?= htmlspecialchars($st['movie_title']) ?></td>
                <td><?= htmlspecialchars($st['cinema_name']) ?></td>
                <td><?= $st['show_date'] ?></td>
                <td><?= date('g:i a', strtotime($st['show_time'])) ?></td>
                <td><?= htmlspecialchars($st['format'] ?? 'Standard') ?></td>
                <td><a href="?action=delete-showtime&id=<?= $st['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this showtime?')">Delete</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <h2 class="mt-2">Add Showtime</h2>
    <div class="card">
        <form method="post" action="?action=add-showtime">
            <div class="form-row">
                <div><label>Movie</label><select name="movie_id"><?php foreach ($movies as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?></option><?php endforeach; ?></select></div>
                <div><label>Cinema</label><select name="cinema_id"><?php foreach ($cinemas as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['chain_name']) ?>)</option><?php endforeach; ?></select></div>
                <div><label>Date</label><input type="date" name="show_date" value="<?= date('Y-m-d') ?>"></div>
                <div><label>Time</label><input type="time" name="show_time" value="19:00"></div>
                <div><label>Format</label><select name="format"><option value="Standard">Standard</option><option value="IMAX">IMAX</option><option value="VIP">VIP</option><option value="4DX">4DX</option></select></div>
                <div><label>Language</label><select name="language"><option value="English">English</option><option value="Arabic">Arabic</option><option value="French">French</option></select></div>
            </div>
            <div><label>Booking URL</label><input name="booking_url" placeholder="https://..."></div>
            <div class="mt-2"><button type="submit" class="btn btn-primary">Add Showtime</button></div>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($action === 'cinemas'): ?>
    <h2>Cinemas</h2>
    <div style="overflow-x:auto;">
    <table>
        <thead><tr><th>Name</th><th>Chain</th><th>City</th><th>Slug</th></tr></thead>
        <tbody>
            <?php foreach ($cinemas as $c): ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                <td><?= htmlspecialchars($c['chain_name']) ?></td>
                <td><?= htmlspecialchars($c['city']) ?></td>
                <td style="font-size:0.75rem;color:#888;"><?= htmlspecialchars($c['slug']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <?php if ($action === 'import'): ?>
    <h2>TMDb Import</h2>
    <div class="card">
        <label>Search TMDb</label>
        <input id="tmdb-search" placeholder="Search for a movie..." autocomplete="off">
        <div class="tmdb-results" id="tmdb-results"></div>
    </div>
    <script>
    const searchInput = document.getElementById('tmdb-search');
    const resultsDiv = document.getElementById('tmdb-results');
    let timer;
    searchInput.addEventListener('input', () => {
        clearTimeout(timer);
        const q = searchInput.value.trim();
        if (q.length < 2) { resultsDiv.innerHTML = ''; return; }
        timer = setTimeout(async () => {
            try {
                const res = await fetch('?action=tmdb-search&q=' + encodeURIComponent(q));
                const data = await res.json();
                resultsDiv.innerHTML = '';
                (data.results || []).slice(0, 10).forEach(m => {
                    const year = m.release_date ? m.release_date.substring(0,4) : '';
                    const poster = m.poster_path ? 'https://image.tmdb.org/t/p/w92' + m.poster_path : '';
                    resultsDiv.innerHTML += `
                        <div class="tmdb-result">
                            ${poster ? '<img src="' + poster + '" alt="">' : '<div style="width:40px;height:60px;background:#1B1B22;border-radius:4px;"></div>'}
                            <div class="tmdb-result-info">
                                <div class="tmdb-result-title">${m.title} ${year ? '(' + year + ')' : ''}</div>
                                <div class="tmdb-result-meta">${m.overview ? m.overview.substring(0,100) + '...' : ''}</div>
                            </div>
                            <form method="post" action="?action=tmdb-import" style="flex-shrink:0;">
                                <input type="hidden" name="tmdb_id" value="${m.id}">
                                <button type="submit" class="btn btn-primary btn-sm">Import</button>
                            </form>
                        </div>
                    `;
                });
                if (!data.results || data.results.length === 0) {
                    resultsDiv.innerHTML = '<div style="padding:16px;color:#888;">No results found.</div>';
                }
            } catch(e) { console.error(e); }
        }, 400);
    });
    </script>
    <?php endif; ?>

    <?php if ($action === 'sync'): ?>
    <h2>Sync &amp; Maintenance</h2>
    <div class="stats" style="margin-bottom:16px;">
        <div class="stat-card">
            <div class="stat-num"><?= count($db->query("SELECT id FROM movies WHERE tmdb_id IS NOT NULL")->fetchAll()) ?></div>
            <div class="stat-label">Synced with TMDb</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= count($db->query("SELECT id FROM movies WHERE tmdb_id IS NULL")->fetchAll()) ?></div>
            <div class="stat-label">Not Synced</div>
        </div>
    </div>
    <div class="flex">
        <a href="?action=run-sync" class="btn btn-primary" onclick="return confirm('Run full TMDb sync? This may take a while.')">Run Full Sync Now</a>
        <a href="?action=import" class="btn btn-outline">Import Single Movie</a>
    </div>
    <?php endif; ?>

    <?php if ($action === 'logs'): ?>
    <h2>Sync Logs</h2>
    <div style="overflow-x:auto;">
    <table>
        <thead><tr><th>Date</th><th>Chain</th><th>Status</th><th>Movies</th><th>Times</th><th>Error</th></tr></thead>
        <tbody>
            <?php if (empty($logs)): ?><tr><td colspan="6" style="text-align:center;color:#888;">No logs yet</td></tr><?php endif; ?>
            <?php foreach ($logs as $l): ?>
            <tr>
                <td><?= $l['ran_at'] ?></td>
                <td><?= htmlspecialchars($l['chain_slug'] ?? '-') ?></td>
                <td><span class="badge badge-<?= $l['status'] === 'success' ? 'green' : ($l['status'] === 'partial' ? 'yellow' : 'red') ?>"><?= $l['status'] ?></span></td>
                <td><?= (int)$l['movies_found'] ?></td>
                <td><?= (int)$l['times_found'] ?></td>
                <td style="font-size:0.75rem;color:#888;"><?= htmlspecialchars(substr($l['error_msg'] ?? '', 0, 100)) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
