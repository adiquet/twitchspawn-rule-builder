<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TSL\Db\RulesetRepository;
use TSL\Support\Filenames;

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
if ($slug === '') {
    http_response_code(400);
    exit('Missing slug.');
}

try {
    $repo = new RulesetRepository();
} catch (\RuntimeException $e) {
    http_response_code(500);
    exit('Server is not configured yet.');
}

$row = $repo->findBySlug($slug);
if (!$row) {
    http_response_code(404);
    exit('Not found.');
}

$filename = Filenames::rulesFilename($row['mc_nick']);

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($row['raw_tsl']));
echo $row['raw_tsl'];
