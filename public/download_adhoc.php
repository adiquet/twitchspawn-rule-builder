<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TSL\Grammar\Generator;
use TSL\Grammar\GrammarDefinition;
use TSL\Grammar\Rule;
use TSL\Support\Csrf;
use TSL\Support\Filenames;

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Your session expired — reload the builder page and try again.');
}

$payload = json_decode((string) ($_POST['payload'] ?? ''), true);
if (!is_array($payload)) {
    http_response_code(400);
    exit('Invalid request.');
}

$profile = $payload['mcProfile'] ?? '';
if (!isset(GrammarDefinition::PROFILES[$profile])) {
    http_response_code(400);
    exit('Invalid Minecraft profile.');
}

try {
    $rules = array_map(fn (array $r) => Rule::fromArray($r), $payload['rules'] ?? []);
} catch (\Throwable $e) {
    http_response_code(400);
    exit('Malformed rule data.');
}

$tsl = Generator::generateRuleset($rules, $profile);
$filename = Filenames::rulesFilename($payload['mcNick'] ?? null);

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($tsl));
echo $tsl;
