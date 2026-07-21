<?php
declare(strict_types=1);

namespace TSL\Db;

use TSL\Grammar\Generator;
use TSL\Grammar\Rule;
use TSL\Slug\EditSecret;
use TSL\Slug\SlugGenerator;

/**
 * Persists rulesets keyed by a shareable slug. rules_json (structured) is
 * canonical; raw_tsl is a regenerated cache stored alongside it so view/
 * download requests don't need to re-run the generator every time.
 */
final class RulesetRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * @param Rule[] $rules
     * @return array{slug:string,editToken:string}
     */
    public function create(
        string $mcProfile,
        string $mcVersionLabel,
        ?string $mcNick,
        ?string $title,
        array $rules,
        ?string $importSourceTsl = null
    ): array {
        $rulesJson = json_encode(array_map(fn (Rule $r) => $r->toArray(), $rules));
        $rawTsl = Generator::generateRuleset($rules, $mcProfile);
        $editToken = EditSecret::generateToken();
        $editHash = EditSecret::hash($editToken);

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $slug = SlugGenerator::generate();
            $stmt = $this->pdo->prepare(
                'INSERT INTO rulesets
                    (slug, edit_secret_hash, mc_profile, mc_version_label, mc_nick, title, rules_json, raw_tsl, import_source_tsl)
                 VALUES (:slug, :edit_hash, :profile, :version_label, :nick, :title, :rules_json, :raw_tsl, :import_source)'
            );
            try {
                $stmt->execute([
                    ':slug' => $slug,
                    ':edit_hash' => $editHash,
                    ':profile' => $mcProfile,
                    ':version_label' => $mcVersionLabel,
                    ':nick' => $mcNick,
                    ':title' => $title,
                    ':rules_json' => $rulesJson,
                    ':raw_tsl' => $rawTsl,
                    ':import_source' => $importSourceTsl,
                ]);
                return ['slug' => $slug, 'editToken' => $editToken];
            } catch (\PDOException $e) {
                // 23000 = integrity constraint violation (slug collision) — retry with a fresh slug.
                if ($e->getCode() !== '23000' || $attempt === 7) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Could not generate a unique slug.');
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rulesets WHERE slug = :slug');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function verifyEditToken(array $row, string $token): bool
    {
        return EditSecret::verify($token, $row['edit_secret_hash']);
    }

    /** @param Rule[] $rules */
    public function update(int $id, string $mcProfile, string $mcVersionLabel, ?string $mcNick, ?string $title, array $rules): void
    {
        $rulesJson = json_encode(array_map(fn (Rule $r) => $r->toArray(), $rules));
        $rawTsl = Generator::generateRuleset($rules, $mcProfile);

        $this->pdo->beginTransaction();
        try {
            $current = $this->pdo->prepare('SELECT rules_json, raw_tsl FROM rulesets WHERE id = :id');
            $current->execute([':id' => $id]);
            $existing = $current->fetch();
            if ($existing) {
                $rev = $this->pdo->prepare(
                    'INSERT INTO ruleset_revisions (ruleset_id, rules_json, raw_tsl) VALUES (:id, :rules_json, :raw_tsl)'
                );
                $rev->execute([':id' => $id, ':rules_json' => $existing['rules_json'], ':raw_tsl' => $existing['raw_tsl']]);
            }

            $update = $this->pdo->prepare(
                'UPDATE rulesets SET mc_profile = :profile, mc_version_label = :version_label, mc_nick = :nick,
                    title = :title, rules_json = :rules_json, raw_tsl = :raw_tsl
                 WHERE id = :id'
            );
            $update->execute([
                ':profile' => $mcProfile,
                ':version_label' => $mcVersionLabel,
                ':nick' => $mcNick,
                ':title' => $title,
                ':rules_json' => $rulesJson,
                ':raw_tsl' => $rawTsl,
                ':id' => $id,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function incrementViewCount(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE rulesets SET view_count = view_count + 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /** Returns true if the save is allowed and records it; false if the caller is over the throttle limit. */
    public function checkAndRecordThrottle(string $ip, int $maxPerWindow, int $windowSeconds): bool
    {
        $ipHash = hash('sha256', $ip);
        $windowStart = date('Y-m-d H:i:s', intdiv(time(), $windowSeconds) * $windowSeconds);

        $this->pdo->prepare(
            'INSERT INTO save_throttle (ip_hash, window_start, save_count) VALUES (:h, :w, 1)
             ON DUPLICATE KEY UPDATE save_count = save_count + 1'
        )->execute([':h' => $ipHash, ':w' => $windowStart]);

        $stmt = $this->pdo->prepare('SELECT save_count FROM save_throttle WHERE ip_hash = :h AND window_start = :w');
        $stmt->execute([':h' => $ipHash, ':w' => $windowStart]);
        $count = (int) ($stmt->fetchColumn() ?: 0);

        return $count <= $maxPerWindow;
    }
}
