<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TSL\Grammar\Generator;
use TSL\Grammar\GrammarDefinition;
use TSL\Grammar\Rule;
use TSL\Grammar\Validator;

header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body.']);
    exit;
}

$profile = $body['mcProfile'] ?? '';
if (!isset(GrammarDefinition::PROFILES[$profile])) {
    http_response_code(400);
    echo json_encode(['error' => 'mcProfile must be A or B.']);
    exit;
}

try {
    $rules = array_map(fn (array $r) => Rule::fromArray($r), $body['rules'] ?? []);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Malformed rule data.']);
    exit;
}

$tsl = Generator::generateRuleset($rules, $profile);
$warnings = Validator::validate($rules, $profile);

echo json_encode([
    'tsl' => $tsl,
    'warnings' => array_map(fn ($w) => $w->toArray(), $warnings),
]);
