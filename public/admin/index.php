<?php
/**
 * Admin Panel — Movie Management
 *
 * Routes:
 *   /admin/               → Dashboard
 *   /admin/movies         → Movie list + search + import
 *   /admin/movie-edit?id=X→ Edit movie
 *   /admin/tmdb-sync      → Sync logs & trigger
 *   /admin/catalog.php    → Weekly catalog review/import
 *
 * Uses TmdbService for API calls (admin-only).
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$db = getDbConnection();

// Run migration on first load
runSchemaMigrations($db);

$view = $_GET['view'] ?? 'dashboard';

// ─── Handle Actions ─────────────────────────────────
$message = '';
$error   = '';

// Import from TMDb
if (isset($_POST['import_tmdb_id'])) {
    $tmdbId = (int)$_POST['import_tmdb_id'];
    $service = getTmdbService();
    $result = $service->importMovie($tmdbId);
    if ($result['success']) {
        $message = 'Movie ' . $result['action'] . ': ID ' . $result['movie_id'];
    } else {
        $error = $result['error'] ?? 'Import failed';
    }
}

// TMDb search AJAX
if ($view === 'tmdb-search-ajax' && isset($_GET['q'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['q']);
    if (strlen($q) < 2) { echo '[]'; exit; }
    $service = getTmdbService();
    echo json_encode($service->searchMovies($q));
    exit;
}

// Save movie edit
if ($view === 'save-movie' && isset($_POST['movie_id'])) {
    $id = (int)$_POST['movie_id'];
    $fields = ['title', 'slug', 'overview', 'seo_title', 'seo_description'];
    $set = [];
    $params = [];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $set[] = "$f = ?";
            $params[] = $_POST[$f];
        }
    }
    // Boolean fields
    foreach (['is_showing', 'is_coming_soon', 'is_featured'] as $f) {
        $set[] = "$f = ?";
        $params[] = isset($_POST[$f]) ? 1 : 0;
    }
    $params[] = $id;
    if (!empty($set)) {
        $db->prepare("UPDATE movies SET " . implode(', ', $set) . " WHERE id = ?")->execute($params);
        $message = 'Movie updated';
    }
}

// Run sync
if ($view === 'run-sync' && isset($_GET['id'])) {
    $service = getTmdbService();
    $result = $service->syncMovie((int)$_GET['id']);
    $message = $result['success'] ? 'Sync completed' : 'Sync failed: ' . ($result['error'] ?? '');
}

// Fetch data
$movies = $db->query("
    SELECT id, tmdb_id, imdb_id, title, slug, vote_average,
           is_showing, is_coming_soon, is_featured,
           last_synced_at, created_at
    FROM movies ORDER BY created_at DESC LIMIT 100
")->fetchAll();

$stats = [
    'total'      => count($movies),
    'showing'    => $db->query("SELECT COUNT(*) FROM movies WHERE is_showing = 1")->fetchColumn(),
    'coming'     => $db->query("SELECT COUNT(*) FROM movies WHERE is_coming_soon = 1")->fetchColumn(),
    'tmdb'       => $db->query("SELECT COUNT(*) FROM movies WHERE tmdb_id IS NOT NULL")->fetchColumn(),
    'no_tmdb'    => $db->query("SELECT COUNT(*) FROM movies WHERE tmdb_id IS NULL")->fetchColumn(),
    'showtimes'  => $db->query("SELECT COUNT(*) FROM showtimes WHERE show_date >= CURDATE()")->fetchColumn(),
    'cinemas'    => $db->query("SELECT COUNT(*) FROM cinemas WHERE is_active = 1")->fetchColumn(),
    'logs'       => $db->query("SELECT COUNT(*) FROM tmdb_sync_logs")->fetchColumn(),
];

$logs = $db->query("SELECT * FROM tmdb_sync_logs ORDER BY created_at DESC LIMIT 30")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#0B0B0F; color:#f0f0f0; font-size:14px; }
        .wrap { max-width:1400px; margin:0 auto; padding:24px; }
        h1 { font-size:1.4rem; font-weight:700; margin-bottom:20px; color:#fff; }
        h2 { font-size:1.1rem; font-weight:600; margin-bottom:12px; color:#FF3D71; }
        .nav { display:flex; gap:4px; margin-bottom:24px; background:#15151B; padding:8px; border-radius:12px; flex-wrap:wrap; }
        .nav a { padding:8px 16px; border-radius:8px; color:#888; text-decoration:none; font-weight:500; }
        .nav a:hover { color:#fff; background:#1B1B22; }
        .nav a.active { background:#FF3D71; color:#000; }
        .msg { padding:12px 16px; border-radius:8px; margin-bottom:16px; font-weight:500; }
        .msg.success { background:rgba(0,200,83,0.12); color:#00C853; border:1px solid rgba(0,200,83,0.2); }
        .msg.error { background:rgba(255,61,113,0.12); color:#FF3D71; border:1px solid rgba(255,61,113,0.2); }
        table { width:100%; border-collapse:collapse; background:#15151B; border-radius:12px; overflow:hidden; }
        th,td { padding:10px 14px; text-align:left; border-bottom:1px solid rgba(255,255,255,0.06); }
        th { background:#1B1B22; font-weight:600; color:#888; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.05em; }
        td { font-size:0.85rem; }
        tr:hover td { background:rgba(255,255,255,0.03); }
        .btn { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:8px; font-weight:600; font-size:0.8rem; cursor:pointer; border:none; font-family:inherit; text-decoration:none; transition:all 0.15s; }
        .btn-primary { background:#FF3D71; color:#000; }
        .btn-primary:hover { opacity:0.85; }
        .btn-outline { background:transparent; border:1px solid rgba(255,255,255,0.15); color:#f0f0f0; }
        .btn-outline:hover { border-color:#FF3D71; color:#FF3D71; }
        .btn-sm { padding:4px 10px; font-size:0.7rem; }
        .btn-danger { background:#d32f2f; color:#fff; }
        .flex { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        input, select, textarea { background:#1B1B22; border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:8px 12px; color:#f0f0f0; font-family:inherit; font-size:0.85rem; width:100%; }
        input:focus, select:focus, textarea:focus { outline:none; border-color:#FF3D71; }
        label { display:block; font-size:0.7rem; font-weight:600; color:#888; margin-bottom:4px; text-transform:uppercase; }
        .card { background:#15151B; border:1px solid rgba(255,255,255,0.06); border-radius:12px; padding:20px; margin-bottom:20px; }
        .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:12px; margin-bottom:24px; }
        .stat-card { background:#15151B; border-radius:12px; padding:16px; text-align:center; }
        .stat-num { font-size:1.8rem; font-weight:700; color:#FF3D71; }
        .stat-label { font-size:0.7rem; color:#888; margin-top:4px; text-transform:uppercase; }
        .badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:0.65rem; font-weight:600; }
        .badge-green { background:rgba(0,200,83,0.15); color:#00C853; }
        .badge-yellow { background:rgba(255,171,0,0.15); color:#FFAB00; }
        .badge-red { background:rgba(255,61,113,0.15); color:#FF3D71; }
        .badge-blue { background:rgba(94,139,255,0.15); color:#5E8BFF; }
        .tmdb-results { max-height:420px; overflow-y:auto; border:1px solid rgba(255,255,255,0.06); border-radius:8px; margin-top:8px; }
        .tmdb-result { display:flex; gap:12px; padding:10px; border-bottom:1px solid rgba(255,255,255,0.04); align-items:center; }
        .tmdb-result:hover { background:rgba(255,61,113,0.04); }
        .tmdb-result img { width:36px; height:54px; border-radius:4px; object-fit:cover; background:#1B1B22; }
        .tmdb-result-info { flex:1; min-width:0; }
        .tmdb-result-title { font-weight:600; font-size:0.85rem; }
        .tmdb-result-meta { font-size:0.7rem; color:#888; }
        .form-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; margin-bottom:12px; }
        .mt-2 { margin-top:12px; }
        .mb-2 { margin-bottom:12px; }
        .w-full { width:100%; }
        .empty { text-align:center; padding:40px; color:#888; }
        @media (max-width:768px) { .wrap { padding:16px; } .nav a { padding:6px 10px; font-size:0.8rem; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="flex" style="justify-content:space-between;">
        <h1><?= SITE_NAME ?> Admin</h1>
        <div class="flex">
            <a href="<?= _link('/') ?>" class="btn btn-outline btn-sm" target="_blank">View Site</a>
            <a href="<?= _link('/admin') ?>" class="btn btn-outline btn-sm">Dashboard</a>
            <a href="<?= _link('/admin/logout.php') ?>" class="btn btn-outline btn-sm" style="color:#FF3D71;">Logout</a>
        </div>
    </div>

    <?php if ($message): ?><div class="msg success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="nav">
        <a href="?view=dashboard" class="<?= $view === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="?view=movies" class="<?= $view === 'movies' ? 'active' : '' ?>">Movies</a>
        <a href="?view=import" class="<?= $view === 'import' ? 'active' : '' ?>">TMDb Import</a>
        <a href="catalog.php">Catalog Review</a>
        <a href="?view=logs" class="<?= $view === 'logs' ? 'active' : '' ?>">Sync Logs</a>
        <a href="ads.php">AdSense</a>
    </div>

    <?php if ($view === 'dashboard'): ?>
    <div class="stats">
        <div class="stat-card"><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-label">Total Movies</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['showing'] ?></div><div class="stat-label">Now Showing</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['coming'] ?></div><div class="stat-label">Coming Soon</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['tmdb'] ?></div><div class="stat-label">Synced w/ TMDb</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['no_tmdb'] ?></div><div class="stat-label">Not Synced</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['showtimes'] ?></div><div class="stat-label">Upcoming Showtimes</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['cinemas'] ?></div><div class="stat-label">Active Cinemas</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['logs'] ?></div><div class="stat-label">Sync Logs</div></div>
    </div>
    <?php endif; ?>

    <?php if ($view === 'movies'): ?>
    <div class="flex" style="justify-content:space-between;margin-bottom:16px;">
        <h2>All Movies</h2>
        <a href="?view=import" class="btn btn-primary btn-sm">+ Import from TMDb</a>
    </div>
    <?php if (empty($movies)): ?>
        <div class="empty">No movies yet. <a href="?view=import" style="color:#FF3D71;">Import your first movie from TMDb.</a></div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table>
        <thead><tr><th>ID</th><th>Title</th><th>TMDb</th><th>IMDb</th><th>Rating</th><th>Status</th><th>Synced</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($movies as $m): ?>
            <tr>
                <td><?= $m['id'] ?></td>
                <td><strong><?= htmlspecialchars($m['title']) ?></strong></td>
                <td><?= $m['tmdb_id'] ? '<a href="https://www.themoviedb.org/movie/' . $m['tmdb_id'] . '" target="_blank" style="color:#5E8BFF;">' . $m['tmdb_id'] . '</a>' : '-' ?></td>
                <td><?= $m['imdb_id'] ? '<a href="https://www.imdb.com/title/' . $m['imdb_id'] . '/" target="_blank" style="color:#f5c518;">ID</a>' : '-' ?></td>
                <td><?= $m['vote_average'] ? number_format($m['vote_average'], 1) : '-' ?></td>
                <td>
                    <?php if ($m['is_showing']): ?><span class="badge badge-green">Showing</span><?php endif; ?>
                    <?php if ($m['is_coming_soon']): ?><span class="badge badge-yellow">Coming</span><?php endif; ?>
                    <?php if ($m['is_featured']): ?><span class="badge badge-blue">Featured</span><?php endif; ?>
                </td>
                <td style="font-size:0.7rem;color:#888;"><?= $m['last_synced_at'] ? date('M j', strtotime($m['last_synced_at'])) : '-' ?></td>
                <td class="flex">
                    <a href="?view=edit&id=<?= $m['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                    <?php if ($m['tmdb_id']): ?>
                    <a href="?view=run-sync&id=<?= $m['id'] ?>" class="btn btn-outline btn-sm" onclick="return confirm('Sync this movie?')">Sync</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($view === 'edit' && isset($_GET['id'])):
        $m = $db->prepare("SELECT * FROM movies WHERE id = ?");
        $m->execute([(int)$_GET['id']]);
        $m = $m->fetch();
        if (!$m): ?><div class="msg error">Movie not found</div>
        <?php else: ?>
    <h2>Edit: <?= htmlspecialchars($m['title']) ?></h2>
    <div class="card">
        <form method="post" action="?view=save-movie">
            <input type="hidden" name="movie_id" value="<?= $m['id'] ?>">
            <div class="form-row">
                <div><label>Title</label><input name="title" value="<?= htmlspecialchars($m['title']) ?>"></div>
                <div><label>Slug</label><input name="slug" value="<?= htmlspecialchars($m['slug']) ?>"></div>
            </div>
            <div class="form-row">
                <div><label>SEO Title</label><input name="seo_title" value="<?= htmlspecialchars($m['seo_title'] ?? '') ?>" placeholder="Auto-generated if empty"></div>
                <div><label>TMDb Rating</label><input value="<?= $m['vote_average'] ?? '' ?>" disabled style="opacity:0.5;"></div>
            </div>
            <div><label>Overview / Synopsis</label><textarea name="overview" rows="3"><?= htmlspecialchars($m['overview'] ?? '') ?></textarea></div>
            <div><label>SEO Description</label><textarea name="seo_description" rows="2" placeholder="Auto-generated if empty"><?= htmlspecialchars($m['seo_description'] ?? '') ?></textarea></div>
            <div class="form-row">
                <div><label class="flex" style="gap:8px;"><input type="checkbox" name="is_showing" value="1" <?= $m['is_showing'] ? 'checked' : '' ?>> Now Showing</label></div>
                <div><label class="flex" style="gap:8px;"><input type="checkbox" name="is_coming_soon" value="1" <?= $m['is_coming_soon'] ? 'checked' : '' ?>> Coming Soon</label></div>
                <div><label class="flex" style="gap:8px;"><input type="checkbox" name="is_featured" value="1" <?= $m['is_featured'] ? 'checked' : '' ?>> Featured</label></div>
            </div>
            <?php if ($m['tmdb_id']): ?>
            <div style="font-size:0.8rem;color:#888;margin-bottom:8px;">
                TMDb: <a href="https://www.themoviedb.org/movie/<?= $m['tmdb_id'] ?>" target="_blank" style="color:#5E8BFF;"><?= $m['tmdb_id'] ?></a>
                <?php if ($m['imdb_id']): ?> · IMDb: <a href="https://www.imdb.com/title/<?= $m['imdb_id'] ?>/" target="_blank" style="color:#f5c518;"><?= $m['imdb_id'] ?></a><?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="mt-2"><button type="submit" class="btn btn-primary">Save Changes</button></div>
        </form>
    </div>
    <?php endif; endif; ?>

    <?php if ($view === 'import'): ?>
    <h2>Import from TMDb</h2>
    <div class="card">
        <label>Search TMDb</label>
        <input id="tmdb-search" placeholder="Type a movie title..." autocomplete="off" style="margin-bottom:8px;">
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
                const res = await fetch('?view=tmdb-search-ajax&q=' + encodeURIComponent(q));
                const data = await res.json();
                resultsDiv.innerHTML = '';
                (data.results || []).slice(0, 10).forEach(m => {
                    const year = m.release_date ? m.release_date.substring(0,4) : '';
                    const poster = m.poster_path ? 'https://image.tmdb.org/t/p/w92' + m.poster_path : '';
                    resultsDiv.innerHTML += `
                        <div class="tmdb-result">
                            ${poster ? '<img src="' + poster + '" alt="">' : '<div style="width:36px;height:54px;background:#1B1B22;border-radius:4px;flex-shrink:0;"></div>'}
                            <div class="tmdb-result-info">
                                <div class="tmdb-result-title">${m.title} ${year ? '(' + year + ')' : ''}</div>
                                <div class="tmdb-result-meta">${m.overview ? m.overview.substring(0,120) + '...' : ''}</div>
                            </div>
                            <form method="post" action="?view=import" style="flex-shrink:0;">
                                <input type="hidden" name="import_tmdb_id" value="${m.id}">
                                <button type="submit" class="btn btn-primary btn-sm">Import</button>
                            </form>
                        </div>
                    `;
                });
                if (!data.results || data.results.length === 0) {
                    resultsDiv.innerHTML = '<div style="padding:16px;color:#888;">No results.</div>';
                }
            } catch(e) { console.error(e); }
        }, 400);
    });
    </script>
    <?php endif; ?>

    <?php if ($view === 'logs'): ?>
    <h2>Sync Logs</h2>
    <?php if (empty($logs)): ?>
        <div class="empty">No sync logs yet. Import a movie to see logs.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table>
        <thead><tr><th>Time</th><th>Movie ID</th><th>TMDb ID</th><th>Action</th><th>Status</th><th>Message</th></tr></thead>
        <tbody>
            <?php foreach ($logs as $l): ?>
            <tr>
                <td style="white-space:nowrap;"><?= $l['created_at'] ?></td>
                <td><?= $l['movie_id'] ?? '-' ?></td>
                <td><?= $l['tmdb_id'] ?? '-' ?></td>
                <td><span class="badge badge-<?= $l['action'] === 'import' ? 'green' : 'blue' ?>"><?= htmlspecialchars($l['action'] ?? '') ?></span></td>
                <td><span class="badge badge-<?= $l['status'] === 'success' ? 'green' : 'red' ?>"><?= htmlspecialchars($l['status'] ?? '') ?></span></td>
                <td style="font-size:0.75rem;color:#888;"><?= htmlspecialchars(substr($l['message'] ?? '', 0, 150)) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
