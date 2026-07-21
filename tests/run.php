<?php
declare(strict_types=1);

/**
 * Zero-dependency CLI test runner for the TSL grammar module.
 * Run: php tests/run.php
 */

require __DIR__ . '/../src/autoload.php';

use TSL\Grammar\Parser;
use TSL\Grammar\Generator;
use TSL\Grammar\Validator;

$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  ok  - {$label}\n";
    } else {
        $failed++;
        echo "FAIL  - {$label}" . ($detail !== '' ? " :: {$detail}" : '') . "\n";
    }
}

function profileFromFilename(string $path): string
{
    if (preg_match('/__([AB])\.tsl$/', $path, $m)) {
        return $m[1];
    }
    fwrite(STDERR, "Fixture {$path} is missing a __A/__B profile suffix\n");
    exit(1);
}

function codeFromFilename(string $path): string
{
    return preg_replace('/__[AB]\.tsl$/', '', basename($path));
}

// --- Round-trip fixtures: parse -> generate -> parse, structurally stable, zero errors.
echo "== Round-trip fixtures ==\n";
foreach (glob(__DIR__ . '/fixtures/roundtrip/*.tsl') as $path) {
    $name = basename($path);
    $profile = profileFromFilename($path);
    $raw = file_get_contents($path);

    $first = Parser::parse($raw, $profile);
    if (!empty($first->errors)) {
        check($name, false, 'first parse had errors: ' . json_encode(array_map(fn ($e) => $e->code, $first->errors)));
        continue;
    }
    check("{$name}: first parse produced >=1 rule", count($first->rules) > 0);

    $generated = Generator::generateRuleset($first->rules, $profile);
    $second = Parser::parse($generated, $profile);
    if (!empty($second->errors)) {
        check($name, false, 'second parse (post-generate) had errors: ' . json_encode(array_map(fn ($e) => $e->code, $second->errors)) . "\n---generated---\n{$generated}");
        continue;
    }

    $firstJson = json_encode(array_map(fn ($r) => $r->toArray(), $first->rules));
    $secondJson = json_encode(array_map(fn ($r) => $r->toArray(), $second->rules));
    check("{$name}: structurally stable across generate+reparse", $firstJson === $secondJson, "first={$firstJson}\nsecond={$secondJson}");
}

// --- Error-code fixtures: parsing must fail with exactly the expected code.
echo "\n== Error-code fixtures ==\n";
foreach (glob(__DIR__ . '/fixtures/errors/*.tsl') as $path) {
    $name = basename($path);
    $profile = profileFromFilename($path);
    $expectedCode = codeFromFilename($path);
    $raw = file_get_contents($path);

    $result = Parser::parse($raw, $profile);
    $codes = array_map(fn ($e) => $e->code, $result->errors);
    check($name, in_array($expectedCode, $codes, true), 'got codes: ' . json_encode($codes));
}

// --- Warning-code fixtures: parses cleanly but Parser+Validator must raise the expected warning code.
echo "\n== Warning-code fixtures ==\n";
foreach (glob(__DIR__ . '/fixtures/warnings/*.tsl') as $path) {
    $name = basename($path);
    $profile = profileFromFilename($path);
    $expectedCode = codeFromFilename($path);
    $raw = file_get_contents($path);

    $result = Parser::parse($raw, $profile);
    if (!empty($result->errors)) {
        check($name, false, 'unexpected parse errors: ' . json_encode(array_map(fn ($e) => $e->code, $result->errors)));
        continue;
    }
    $validatorWarnings = Validator::validate($result->rules, $profile);
    $allWarnings = array_merge($result->warnings, $validatorWarnings);
    $codes = array_map(fn ($w) => $w->code, $allWarnings);
    check($name, in_array($expectedCode, $codes, true), 'got codes: ' . json_encode($codes));
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
