<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TSL\Db\RulesetRepository;
use TSL\Db\Database;
use TSL\Grammar\GrammarDefinition;
use TSL\Grammar\Rule;
use TSL\Support\Csrf;

header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

if (!Csrf::verify($body['csrfToken'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Your session expired — reload the page and try again.']);
    exit;
}

$profile = $body['mcProfile'] ?? '';
if (!isset(GrammarDefinition::PROFILES[$profile])) {
    http_response_code(400);
    echo json_encode(['error' => 'mcProfile must be A or B.']);
    exit;
}

$versionLabel = (string) ($body['mcVersionLabel'] ?? GrammarDefinition::PROFILES[$profile]['versions'][0]);
$nick = trim((string) ($body['mcNick'] ?? '')) ?: null;
$title = trim((string) ($body['title'] ?? '')) ?: null;

try {
    $rules = array_map(fn (array $r) => Rule::fromArray($r), $body['rules'] ?? []);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Malformed rule data.']);
    exit;
}

if (empty($rules)) {
    http_response_code(400);
    echo json_encode(['error' => 'Add at least one rule before saving.']);
    exit;
}

try {
    $repo = new RulesetRepository();
} catch (\RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server is not configured yet (missing config/config.php).']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$throttle = Database::config()['save_throttle'];
if (!$repo->checkAndRecordThrottle($ip, $throttle['max_saves_per_window'], $throttle['window_seconds'])) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many saves from this connection recently — try again later.']);
    exit;
}

$existingSlug = $body['rulesetSlug'] ?? null;
$editToken = $body['editToken'] ?? null;

// Built from the currently-running script's own URL directory (not hardcoded to domain root) so
// links come out correct whether this app lives at the site root or in a subfolder like /twitchspawn.
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$appDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/save.php')), '/');
$baseUrl = $scheme . $host . $appDir;

if ($existingSlug && $editToken) {
    $row = $repo->findBySlug((string) $existingSlug);
    if (!$row || !$repo->verifyEditToken($row, (string) $editToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid edit link.']);
        exit;
    }
    $repo->update((int) $row['id'], $profile, $versionLabel, $nick, $title, $rules);
    echo json_encode([
        'viewUrl' => "{$baseUrl}/r.php?slug={$row['slug']}",
        'editUrl' => "{$baseUrl}/r.php?slug={$row['slug']}&token={$editToken}",
    ]);
    exit;
}

$created = $repo->create($profile, $versionLabel, $nick, $title, $rules);
echo json_encode([
    'viewUrl' => "{$baseUrl}/r.php?slug={$created['slug']}",
    'editUrl' => "{$baseUrl}/r.php?slug={$created['slug']}&token={$created['editToken']}",
]);
