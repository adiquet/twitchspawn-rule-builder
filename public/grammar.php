<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TSL\Grammar\GrammarDefinition;
use TSL\Grammar\MinecraftIds;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode(GrammarDefinition::toArray() + ['minecraftIds' => MinecraftIds::toArray()]);
