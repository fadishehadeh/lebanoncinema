<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/catalog_import.php';

requireAuth();

$db = getDbConnection();
runSchemaMigrations($db);
ensureCatalogStorageDirs();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_catalog'])) {
    if (empty($_FILES['catalog_json']['tmp_name']) || !is_uploaded_file($_FILES['catalog_json']['tmp_name'])) {
        $error = 'Choose a catalog JSON file to upload.';
    } else {
        $uploadPath = catalogStorageDir() . '/catalog_review_upload.json';
        if (!move_uploaded_file($_FILES['catalog_json']['tmp_name'], $uploadPath)) {
            $error = 'Upload failed.';
        } else {
            header('Location: ' . _link('/admin/catalog.php?dataset=upload'));
            exit;
        }
    }
}

$datasetChoice = $_GET['dataset'] ?? 'latest';
$datasetPath = catalogDatasetPath($datasetChoice) ?? catalogDatasetPath('upload') ?? catalogDatasetPath('latest');
$datasetChoice = $datasetPath && str_contains($datasetPath, 'catalog_review_upload.json') ? 'upload' : $datasetChoice;

$dataset = null;
$reviewRows = [];
$diff = ['added' => [], 'moved' => [], 'removed' => [], 'reference_count' => 0];
$referenceDataset = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_catalog'])) {
    $postedPath = (string) ($_POST['dataset_path'] ?? '');
    try {
        $dataset = catalogLoadDataset($postedPath);
        $reviewRows = catalogBuildReviewRows($db, $dataset);
        $referencePath = realpath($postedPath) === realpath(catalogDatasetPath('latest') ?: '')
            ? catalogDatasetPath('previous')
            : catalogDatasetPath('latest');
        $referenceDataset = $referencePath ? catalogLoadDataset($referencePath) : null;
        $diff = catalogBuildDiff($reviewRows, $referenceDataset);

        $selectedKeys = array_keys($_POST['include'] ?? []);
        $statusOverrides = $_POST['status'] ?? [];
        $cleanupRemoved = !empty($_POST['cleanup_removed']);

        $result = catalogImportRows($db, $reviewRows, $selectedKeys, $statusOverrides, $cleanupRemoved, $diff['removed']);
        catalogPromoteDataset($postedPath);
        $message = sprintf(
            'Catalog import complete. Updated %d, created %d, ended %d, skipped %d.',
            $result['updated'],
            $result['created'],
            $result['ended'],
            $result['skipped']
        );
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (!$datasetPath) {
    $error = $error ?: 'No catalog dataset found yet. Run `npm run scrape:catalog` in `cinema scrapper`, or upload a catalog JSON below.';
} else {
    try {
        $dataset = $dataset ?? catalogLoadDataset($datasetPath);
        $reviewRows = $reviewRows ?: catalogBuildReviewRows($db, $dataset);
        $referencePath = realpath($datasetPath) === realpath(catalogDatasetPath('latest') ?: '')
            ? catalogDatasetPath('previous')
            : catalogDatasetPath('latest');
        $referenceDataset = $referencePath ? catalogLoadDataset($referencePath) : null;
        $diff = catalogBuildDiff($reviewRows, $referenceDataset);
    } catch (Throwable $e) {
        $dataset = null;
        $reviewRows = [];
        $error = $e->getMessage();
    }
}

$stats = [
    'items' => count($reviewRows),
    'matched' => count(array_filter($reviewRows, fn($row) => $row['match_state'] === 'matched')),
    'new' => count(array_filter($reviewRows, fn($row) => $row['match_state'] === 'new')),
    'ambiguous' => count(array_filter($reviewRows, fn($row) => $row['match_state'] === 'ambiguous')),
    'added' => count($diff['added']),
    'moved' => count($diff['moved']),
    'removed' => count($diff['removed']),
];
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
    <title>Weekly Catalog Review - <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#0B0B0F; color:#f0f0f0; font-size:14px; }
        .wrap { max-width:1440px; margin:0 auto; padding:24px; }
        h1 { font-size:1.6rem; font-weight:700; margin-bottom:18px; }
        h2 { font-size:1.05rem; font-weight:600; margin:0 0 12px; color:#FF3D71; }
        .nav { display:flex; gap:4px; margin-bottom:24px; background:#15151B; padding:8px; border-radius:12px; flex-wrap:wrap; }
        .nav a { padding:8px 16px; border-radius:8px; color:#888; text-decoration:none; font-weight:500; }
        .nav a:hover { color:#fff; background:#1B1B22; }
        .nav a.active { background:#FF3D71; color:#000; }
        .card { background:#15151B; border:1px solid rgba(255,255,255,0.06); border-radius:14px; padding:18px; margin-bottom:16px; }
        .msg { padding:12px 16px; border-radius:8px; margin-bottom:16px; font-weight:500; }
        .msg.success { background:rgba(0,200,83,0.12); color:#00C853; border:1px solid rgba(0,200,83,0.2); }
        .msg.error { background:rgba(255,61,113,0.12); color:#FF3D71; border:1px solid rgba(255,61,113,0.2); }
        .muted { color:#9c9ca7; line-height:1.6; }
        .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:12px; margin-bottom:18px; }
        .stat-card { background:#111117; border-radius:12px; padding:14px; border:1px solid rgba(255,255,255,0.06); }
        .stat-num { font-size:1.5rem; font-weight:700; color:#fff; }
        .stat-label { font-size:0.72rem; color:#8c8c96; text-transform:uppercase; letter-spacing:0.08em; margin-top:4px; }
        .toolbar { display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
        .btn { display:inline-flex; align-items:center; gap:6px; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:600; font-family:inherit; text-decoration:none; }
        .btn-primary { background:#FF3D71; color:#000; }
        .btn-outline { background:transparent; border:1px solid rgba(255,255,255,0.16); color:#f0f0f0; }
        .btn-small { padding:6px 12px; font-size:0.78rem; }
        input[type=file], select { background:#1B1B22; border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:10px 12px; color:#f0f0f0; font-family:inherit; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:10px 8px; border-top:1px solid rgba(255,255,255,0.05); vertical-align:top; }
        th { color:#888; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:600; }
        .badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; }
        .badge-green { background:rgba(0,200,83,0.14); color:#00C853; }
        .badge-yellow { background:rgba(255,171,0,0.14); color:#FFAB00; }
        .badge-blue { background:rgba(94,139,255,0.14); color:#5E8BFF; }
        .badge-red { background:rgba(255,61,113,0.14); color:#FF3D71; }
        .stack { display:flex; flex-direction:column; gap:4px; }
        .stack small, .list small { color:#8c8c96; }
        .list { display:flex; flex-wrap:wrap; gap:6px; }
        .list span { background:#111117; border:1px solid rgba(255,255,255,0.06); border-radius:999px; padding:4px 8px; font-size:0.75rem; }
        .path { color:#ffb8c6; font-family:ui-monospace,SFMono-Regular,Consolas,monospace; font-size:0.78rem; }
        .danger-note { color:#FFAB00; font-size:0.85rem; }
        .section-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px; }
        .checkbox-cell { width:42px; }
        .table-wrap { overflow:auto; }
        .title-row { display:flex; flex-direction:column; gap:4px; min-width:240px; }
        .title-row a { color:#5E8BFF; text-decoration:none; }
        .title-row a:hover { text-decoration:underline; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="nav">
        <a href="<?= _link('/admin') ?>">Dashboard</a>
        <a href="<?= _link('/admin?view=movies') ?>">Movies</a>
        <a href="<?= _link('/admin?view=import') ?>">TMDb Import</a>
        <a href="<?= _link('/admin/catalog.php') ?>" class="active">Catalog Review</a>
        <a href="<?= _link('/admin/ads.php') ?>">AdSense</a>
        <a href="<?= _link('/admin/logout.php') ?>" style="color:#FF3D71;">Logout</a>
    </div>

    <h1>Weekly Catalog Review</h1>
    <?php if ($message): ?><div class="msg success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
        <h2>Workflow</h2>
        <p class="muted">Run <span class="path">npm run scrape:catalog</span> in <span class="path">cinema scrapper</span> to generate <span class="path">storage/catalog/catalog_latest.json</span>, or upload a combined catalog JSON here. Review exact matches, create new movies only when needed, then publish the approved weekly status changes.</p>
    </div>

    <div class="card">
        <div class="toolbar">
            <a class="btn btn-outline btn-small" href="<?= e_link('/admin/catalog.php?dataset=latest') ?>">Use Latest Server Dataset</a>
            <a class="btn btn-outline btn-small" href="<?= e_link('/admin/catalog.php?dataset=upload') ?>">Use Uploaded Dataset</a>
            <?php if ($datasetPath): ?>
                <span class="path"><?= htmlspecialchars($datasetPath) ?></span>
            <?php endif; ?>
        </div>
        <form method="post" enctype="multipart/form-data" style="margin-top:16px;" class="toolbar">
            <input type="file" name="catalog_json" accept="application/json,.json">
            <button type="submit" name="upload_catalog" class="btn btn-primary">Upload Review JSON</button>
        </form>
    </div>

    <div class="stats">
        <div class="stat-card"><div class="stat-num"><?= $stats['items'] ?></div><div class="stat-label">Current Items</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['matched'] ?></div><div class="stat-label">Exact Matches</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['new'] ?></div><div class="stat-label">New Movies</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['ambiguous'] ?></div><div class="stat-label">Ambiguous</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['added'] ?></div><div class="stat-label">Added vs Prior</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['moved'] ?></div><div class="stat-label">Moved Status</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['removed'] ?></div><div class="stat-label">Removed vs Prior</div></div>
    </div>

    <?php if ($dataset): ?>
    <div class="section-grid">
        <div class="card">
            <h2>Dataset Metadata</h2>
            <div class="stack">
                <span>Scraped at: <strong><?= htmlspecialchars($dataset['metadata']['scrapedAt'] ?? 'Unknown') ?></strong></span>
                <span>Total items: <strong><?= (int) ($dataset['metadata']['totalItems'] ?? count($reviewRows)) ?></strong></span>
                <span>Now showing: <strong><?= (int) ($dataset['metadata']['totalNowShowing'] ?? 0) ?></strong></span>
                <span>Coming soon: <strong><?= (int) ($dataset['metadata']['totalComingSoon'] ?? 0) ?></strong></span>
                <?php if (!empty($dataset['metadata']['previousSnapshot'])): ?>
                    <span>Previous snapshot: <span class="path"><?= htmlspecialchars($dataset['metadata']['previousSnapshot']) ?></span></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <h2>Diff Summary</h2>
            <div class="stack">
                <span>Newly added titles: <strong><?= count($diff['added']) ?></strong></span>
                <span>Status moves: <strong><?= count($diff['moved']) ?></strong></span>
                <span>Removed from scrape: <strong><?= count($diff['removed']) ?></strong></span>
                <span class="danger-note">Removed titles are not changed unless you enable cleanup during import.</span>
            </div>
        </div>
    </div>

    <?php if (!empty($dataset['sources'])): ?>
    <div class="card">
        <h2>Source Registry Results</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Source</th><th>Lists</th><th>Counts</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($dataset['sources'] as $source): ?>
                    <tr>
                        <td>
                            <div class="stack">
                                <strong><?= htmlspecialchars($source['name'] ?? $source['key'] ?? 'Source') ?></strong>
                                <small><?= htmlspecialchars($source['base_url'] ?? '') ?></small>
                            </div>
                        </td>
                        <td>
                            <div class="stack">
                                <?php foreach (($source['lists'] ?? []) as $statusKey => $conf): ?>
                                    <small><?= htmlspecialchars($statusKey) ?>: <?= htmlspecialchars($conf['url'] ?? '') ?></small>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td>
                            <div class="stack">
                                <span>Now showing: <?= (int) ($source['counts']['now_showing'] ?? 0) ?></span>
                                <span>Coming soon: <?= (int) ($source['counts']['coming_soon'] ?? 0) ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($source['enabled'])): ?>
                                <span class="badge badge-green">Enabled</span>
                            <?php else: ?>
                                <span class="badge badge-yellow">Disabled</span>
                            <?php endif; ?>
                            <?php if (!empty($source['skip_reason'])): ?>
                                <div><small><?= htmlspecialchars($source['skip_reason']) ?></small></div>
                            <?php endif; ?>
                            <?php if (!empty($source['errors'])): ?>
                                <div class="stack">
                                    <?php foreach ($source['errors'] as $err): ?><small><?= htmlspecialchars($err) ?></small><?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($diff['moved']) || !empty($diff['removed'])): ?>
    <div class="section-grid">
        <?php if (!empty($diff['moved'])): ?>
        <div class="card">
            <h2>Status Moves</h2>
            <div class="stack">
                <?php foreach ($diff['moved'] as $move): ?>
                    <span><strong><?= htmlspecialchars($move['title']) ?></strong> moved from <?= htmlspecialchars($move['from']) ?> to <?= htmlspecialchars($move['to']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($diff['removed'])): ?>
        <div class="card">
            <h2>Removed Titles</h2>
            <div class="stack">
                <?php foreach ($diff['removed'] as $removed): ?>
                    <span><strong><?= htmlspecialchars($removed['title']) ?></strong> was in the previous dataset as <?= htmlspecialchars($removed['status']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="dataset_path" value="<?= htmlspecialchars($datasetPath) ?>">
        <div class="card">
            <div class="toolbar" style="justify-content:space-between;">
                <h2>Review and Import</h2>
                <button type="submit" name="import_catalog" class="btn btn-primary">Publish Selected Rows</button>
            </div>
            <div style="margin:12px 0 16px;">
                <label style="display:flex;gap:10px;align-items:center;">
                    <input type="checkbox" name="cleanup_removed" value="1">
                    <span>Mark titles removed from the latest scrape as <strong>ended</strong> for catalog-managed movies.</span>
                </label>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th class="checkbox-cell">Use</th>
                        <th>Movie</th>
                        <th>Status</th>
                        <th>Chains</th>
                        <th>Review</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reviewRows as $key => $row): ?>
                        <?php
                        $existing = $row['matches'][0] ?? null;
                        $badgeClass = $row['status'] === 'now_showing' ? 'badge-green' : 'badge-yellow';
                        $matchBadge = $row['match_state'] === 'matched' ? 'badge-blue' : ($row['match_state'] === 'new' ? 'badge-green' : 'badge-red');
                        ?>
                        <tr>
                            <td class="checkbox-cell">
                                <input type="checkbox" name="include[<?= htmlspecialchars($key) ?>]" value="1" <?= $row['selected_by_default'] ? 'checked' : '' ?>>
                            </td>
                            <td>
                                <div class="title-row">
                                    <strong><?= htmlspecialchars($row['title']) ?></strong>
                                    <small>Key: <?= htmlspecialchars($row['title_key']) ?></small>
                                    <?php if (!empty($row['source_detail_urls'])): ?>
                                        <a href="<?= htmlspecialchars($row['source_detail_urls'][0]) ?>" target="_blank" rel="noopener">Open source detail</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="stack">
                                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['status']) ?></span>
                                    <select name="status[<?= htmlspecialchars($key) ?>]">
                                        <option value="now_showing" <?= $row['status'] === 'now_showing' ? 'selected' : '' ?>>Now Showing</option>
                                        <option value="coming_soon" <?= $row['status'] === 'coming_soon' ? 'selected' : '' ?>>Coming Soon</option>
                                    </select>
                                    <?php if (count($row['statuses']) > 1): ?>
                                        <small>Observed as: <?= htmlspecialchars(implode(', ', $row['statuses'])) ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="list">
                                    <?php foreach ($row['chains'] as $chain): ?><span><?= htmlspecialchars($chain) ?></span><?php endforeach; ?>
                                </div>
                            </td>
                            <td>
                                <div class="stack">
                                    <span class="badge <?= $matchBadge ?>"><?= htmlspecialchars($row['match_state']) ?></span>
                                    <?php if ($existing): ?>
                                        <small>Existing: <?= htmlspecialchars($existing['title']) ?> (#<?= (int) $existing['id'] ?>)</small>
                                    <?php elseif ($row['match_state'] === 'ambiguous'): ?>
                                        <?php foreach ($row['matches'] as $match): ?>
                                            <small>Candidate: <?= htmlspecialchars($match['title']) ?> (#<?= (int) $match['id'] ?>)</small>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <small>Will create a new movie record.</small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="stack">
                                    <?php if ($existing): ?>
                                        <a href="<?= e_link('/admin?view=edit&id=' . (int) $existing['id']) ?>" target="_blank">Edit local movie</a>
                                    <?php endif; ?>
                                    <a href="<?= e_link('/admin?view=import') ?>" target="_blank">Open TMDb import</a>
                                    <a href="https://www.themoviedb.org/search/movie?query=<?= urlencode($row['title']) ?>" target="_blank" rel="noopener">Search TMDb externally</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
