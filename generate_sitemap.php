<?php
declare(strict_types=1);

$targetUrl = $argv[1] ?? getenv('SITE_URL_OVERRIDE') ?: 'https://lebanoncinema.com';
define('FORCE_SITE_URL', rtrim($targetUrl, '/'));

ob_start();
require __DIR__ . '/public/sitemap.php';
$xml = ob_get_clean();

if ($xml === false || trim($xml) === '') {
    fwrite(STDERR, "Failed to generate sitemap XML.\n");
    exit(1);
}

$targetPath = __DIR__ . '/public/sitemap.xml';
if (file_put_contents($targetPath, $xml) === false) {
    fwrite(STDERR, "Failed to write {$targetPath}.\n");
    exit(1);
}

fwrite(STDOUT, "Generated {$targetPath} for " . FORCE_SITE_URL . "\n");
