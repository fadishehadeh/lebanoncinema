<?php
/**
 * Run all cinema scrapers sequentially.
 * Usage: php scraper/run_all.php
 */
require_once __DIR__ . '/../config.php';

echo "============================================\n";
echo "  LebanonCinema — Run All Scrapers\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo "============================================\n\n";

$db = getDbConnection();
$start = microtime(true);

// Run schema migration first
runSchemaMigrations($db);

// 1. VOX Scraper (existing)
echo "--- VOX ---\n";
require_once __DIR__ . '/vox_scraper.php';
// VOX scraper runs independently

// 2. Grand
echo "\n--- Grand ---\n";
require_once __DIR__ . '/grand_scraper.php';
$GLOBALS['noRun'] = true;
$s = new GrandScraper($db);
$s->run();

// 3. Empire
echo "\n--- Empire ---\n";
require_once __DIR__ . '/empire_scraper.php';
$s = new EmpireScraper($db);
$s->run();

// 4. CinemaCity
echo "\n--- CinemaCity ---\n";
require_once __DIR__ . '/cinemacity_scraper.php';
$s = new CinemaCityScraper($db);
$s->run();

// 5. Cinemall
echo "\n--- Cinemall ---\n";
require_once __DIR__ . '/cinemall_scraper.php';
$s = new CinemallScraper($db);
$s->run();

$elapsed = round(microtime(true) - $start, 2);
echo "\n============================================\n";
echo "  All scrapers completed in {$elapsed}s\n";
echo "============================================\n";
