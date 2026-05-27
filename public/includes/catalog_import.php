<?php

function catalogRootPath(): string {
    return dirname(__DIR__, 2);
}

function catalogStorageDir(): string {
    return catalogRootPath() . '/storage/catalog';
}

function catalogSnapshotsDir(): string {
    return catalogStorageDir() . '/snapshots';
}

function ensureCatalogStorageDirs(): void {
    foreach ([catalogStorageDir(), catalogSnapshotsDir()] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}

function catalogAliasMap(): array {
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $path = catalogRootPath() . '/scraper/catalog_title_overrides.json';
    if (!file_exists($path)) {
        $map = [];
        return $map;
    }

    $json = json_decode((string) file_get_contents($path), true);
    $map = is_array($json) ? $json : [];
    return $map;
}

function catalogNormalizeSourceTitle(string $title): string {
    $title = trim(preg_replace('/\s+/', ' ', html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($title === '') {
        return '';
    }

    $patterns = [
        '/\s*\((vip|studio\.?15|3d|imax|4dx|gold|standard|gc)\)\s*$/i',
        '/\s*[-:]\s*(vip|studio\.?15|3d|imax|4dx|gold|standard|gc)\s*$/i',
        '/\s+(vip|studio\.?15|3d|imax|4dx|gold|standard|gc)\s*$/i',
    ];

    $changed = true;
    while ($changed && $title !== '') {
        $changed = false;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title)) {
                $title = trim((string) preg_replace($pattern, '', $title));
                $changed = true;
            }
        }
    }

    return $title;
}

function catalogBaseTitleKey(string $title): string {
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
    if ($ascii !== false) {
        $title = $ascii;
    }
    $title = strtolower($title);
    $title = str_replace('&', ' and ', $title);
    $title = preg_replace('/[^a-z0-9]+/', ' ', $title);
    $title = preg_replace('/\s+/', ' ', $title);
    return trim((string) $title);
}

function catalogCanonicalizeTitle(string $title): array {
    $rawTitle = trim(preg_replace('/\s+/', ' ', html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $normalizedTitle = catalogNormalizeSourceTitle($rawTitle);
    $key = catalogBaseTitleKey($normalizedTitle);
    $aliases = catalogAliasMap();
    $canonicalTitle = $aliases[$key] ?? $normalizedTitle;
    return [
        'title' => $canonicalTitle,
        'title_key' => catalogBaseTitleKey($canonicalTitle),
        'raw_title' => $rawTitle,
        'alias_applied' => isset($aliases[$key]),
    ];
}

function catalogDatasetPath(string $dataset): ?string {
    ensureCatalogStorageDirs();
    $map = [
        'latest' => catalogStorageDir() . '/catalog_latest.json',
        'previous' => catalogStorageDir() . '/catalog_previous.json',
        'upload' => catalogStorageDir() . '/catalog_review_upload.json',
    ];
    $path = $map[$dataset] ?? $map['latest'];
    return file_exists($path) ? $path : null;
}

function catalogLoadDataset(string $path): array {
    if (!file_exists($path)) {
        throw new RuntimeException("Catalog dataset not found: $path");
    }

    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json)) {
        throw new RuntimeException('Catalog dataset is not valid JSON');
    }
    if (!isset($json['items']) || !is_array($json['items'])) {
        throw new RuntimeException('Catalog dataset is missing the items array');
    }
    return $json;
}

function catalogBuildMovieIndex(PDO $db): array {
    $rows = $db->query("
        SELECT id, tmdb_id, title, slug, status, is_showing, is_coming_soon,
               catalog_title_key, catalog_managed, catalog_last_seen_at
        FROM movies
    ")->fetchAll();

    $byKey = [];
    foreach ($rows as $row) {
        $key = !empty($row['catalog_title_key'])
            ? $row['catalog_title_key']
            : catalogCanonicalizeTitle($row['title'])['title_key'];
        $row['resolved_title_key'] = $key;
        $byKey[$key][] = $row;
    }

    return ['rows' => $rows, 'by_key' => $byKey];
}

function catalogBuildReviewRows(PDO $db, array $dataset): array {
    $movieIndex = catalogBuildMovieIndex($db);
    $rows = [];

    foreach ($dataset['items'] as $item) {
        $canonical = catalogCanonicalizeTitle((string) ($item['title'] ?? ''));
        $key = $item['title_key'] ?? $canonical['title_key'];
        $matches = $movieIndex['by_key'][$key] ?? [];
        $matchState = count($matches) === 0 ? 'new' : (count($matches) === 1 ? 'matched' : 'ambiguous');

        $rows[$key] = [
            'title' => $item['title'] ?? $canonical['title'],
            'title_key' => $key,
            'status' => ($item['status'] ?? 'coming_soon') === 'now_showing' ? 'now_showing' : 'coming_soon',
            'statuses' => array_values(array_unique(array_filter((array) ($item['statuses'] ?? [$item['status'] ?? 'coming_soon'])))),
            'chains' => array_values(array_unique(array_filter((array) ($item['chains'] ?? (($item['chain'] ?? '') ? [$item['chain']] : []))))),
            'source_list_urls' => array_values(array_unique(array_filter((array) ($item['source_list_urls'] ?? (($item['source_list_url'] ?? '') ? [$item['source_list_url']] : []))))),
            'source_detail_urls' => array_values(array_unique(array_filter((array) ($item['source_detail_urls'] ?? (($item['source_detail_url'] ?? '') ? [$item['source_detail_url']] : []))))),
            'poster_url' => (string) ($item['poster_url'] ?? ''),
            'scraped_at' => (string) ($item['scraped_at'] ?? ($dataset['metadata']['scrapedAt'] ?? '')),
            'raw_titles' => array_values(array_unique(array_filter((array) ($item['raw_titles'] ?? [$canonical['raw_title']])))),
            'match_state' => $matchState,
            'matches' => $matches,
            'selected_by_default' => $matchState !== 'ambiguous',
        ];
    }

    uasort($rows, fn($a, $b) => strcmp($a['title'], $b['title']));
    return $rows;
}

function catalogBuildDiff(array $currentRows, ?array $referenceDataset): array {
    $referenceRows = [];
    if ($referenceDataset && !empty($referenceDataset['items'])) {
        foreach ($referenceDataset['items'] as $item) {
            $title = (string) ($item['title'] ?? '');
            if ($title === '') {
                continue;
            }
            $canonical = catalogCanonicalizeTitle($title);
            $key = $item['title_key'] ?? $canonical['title_key'];
            $referenceRows[$key] = [
                'title' => $item['title'] ?? $canonical['title'],
                'status' => ($item['status'] ?? 'coming_soon') === 'now_showing' ? 'now_showing' : 'coming_soon',
                'chains' => array_values(array_unique(array_filter((array) ($item['chains'] ?? (($item['chain'] ?? '') ? [$item['chain']] : []))))),
            ];
        }
    }

    $added = [];
    $moved = [];
    foreach ($currentRows as $key => $row) {
        if (!isset($referenceRows[$key])) {
            $added[$key] = $row;
            continue;
        }
        if ($referenceRows[$key]['status'] !== $row['status']) {
            $moved[$key] = [
                'title' => $row['title'],
                'from' => $referenceRows[$key]['status'],
                'to' => $row['status'],
                'chains' => $row['chains'],
            ];
        }
    }

    $removed = [];
    foreach ($referenceRows as $key => $row) {
        if (!isset($currentRows[$key])) {
            $removed[$key] = $row;
        }
    }

    return [
        'added' => $added,
        'moved' => $moved,
        'removed' => $removed,
        'reference_count' => count($referenceRows),
    ];
}

function catalogCreateSlug(PDO $db, string $title): string {
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
    $base = strtolower(trim((string) ($ascii !== false ? $ascii : $title)));
    $base = preg_replace('/[^a-z0-9\s-]/', '', $base);
    $base = preg_replace('/[\s-]+/', '-', $base);
    $base = trim((string) $base, '-');
    if ($base === '') {
        $base = 'movie';
    }

    $slug = $base;
    $counter = 1;
    $stmt = $db->prepare("SELECT COUNT(*) FROM movies WHERE slug = ?");
    while (true) {
        $stmt->execute([$slug]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $counter++;
    }
}

function catalogMetaPayload(array $row): string {
    return json_encode([
        'chains' => $row['chains'],
        'source_list_urls' => $row['source_list_urls'],
        'source_detail_urls' => $row['source_detail_urls'],
        'poster_url' => $row['poster_url'],
        'raw_titles' => $row['raw_titles'],
        'scraped_at' => $row['scraped_at'],
        'statuses' => $row['statuses'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function catalogImportRows(PDO $db, array $reviewRows, array $selectedKeys, array $statusOverrides, bool $cleanupRemoved, array $removedRows): array {
    $selectedKeys = array_values(array_unique(array_filter($selectedKeys)));
    $result = [
        'updated' => 0,
        'created' => 0,
        'skipped' => 0,
        'ended' => 0,
    ];

    $db->beginTransaction();
    try {
        foreach ($selectedKeys as $key) {
            if (empty($reviewRows[$key])) {
                $result['skipped']++;
                continue;
            }

            $row = $reviewRows[$key];
            if ($row['match_state'] === 'ambiguous') {
                $result['skipped']++;
                continue;
            }

            $status = ($statusOverrides[$key] ?? $row['status']) === 'now_showing' ? 'now_showing' : 'coming_soon';
            $isShowing = $status === 'now_showing' ? 1 : 0;
            $isComingSoon = $status === 'coming_soon' ? 1 : 0;
            $metaJson = catalogMetaPayload($row);

            if ($row['match_state'] === 'matched') {
                $movieId = (int) $row['matches'][0]['id'];
                $stmt = $db->prepare("
                    UPDATE movies
                    SET status = ?,
                        is_showing = ?,
                        is_coming_soon = ?,
                        catalog_managed = 1,
                        catalog_title_key = ?,
                        catalog_last_seen_at = NOW(),
                        catalog_meta_json = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$status, $isShowing, $isComingSoon, $row['title_key'], $metaJson, $movieId]);
                $result['updated']++;
                continue;
            }

            $slug = catalogCreateSlug($db, $row['title']);
            $stmt = $db->prepare("
                INSERT INTO movies (
                    title, slug, poster_url, status, is_showing, is_coming_soon,
                    catalog_managed, catalog_title_key, catalog_last_seen_at, catalog_meta_json,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW(), ?, NOW(), NOW())
            ");
            $stmt->execute([
                $row['title'],
                $slug,
                $row['poster_url'] ?: null,
                $status,
                $isShowing,
                $isComingSoon,
                $row['title_key'],
                $metaJson,
            ]);
            $result['created']++;
        }

        if ($cleanupRemoved) {
            foreach ($removedRows as $key => $row) {
                $canonical = catalogCanonicalizeTitle($row['title']);
                $stmt = $db->prepare("
                    UPDATE movies
                    SET status = 'ended',
                        is_showing = 0,
                        is_coming_soon = 0,
                        catalog_managed = 1,
                        catalog_title_key = ?,
                        catalog_last_seen_at = NOW(),
                        updated_at = NOW()
                    WHERE catalog_title_key = ?
                       OR title = ?
                ");
                $stmt->execute([$canonical['title_key'], $canonical['title_key'], $row['title']]);
                $result['ended'] += $stmt->rowCount();
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    return $result;
}

function catalogPromoteDataset(string $datasetPath): void {
    ensureCatalogStorageDirs();
    $latestPath = catalogStorageDir() . '/catalog_latest.json';
    $previousPath = catalogStorageDir() . '/catalog_previous.json';

    if (!file_exists($datasetPath)) {
        return;
    }

    if (realpath($datasetPath) !== realpath($latestPath) && file_exists($latestPath)) {
        copy($latestPath, $previousPath);
    }

    if (realpath($datasetPath) !== realpath($latestPath)) {
        copy($datasetPath, $latestPath);
    }

    $stamp = date('YmdHis');
    copy($latestPath, catalogSnapshotsDir() . '/catalog-imported-' . $stamp . '.json');
}
