<?php
declare(strict_types=1);

namespace TSL\Grammar;

/**
 * Single source of truth for TSL vocabulary: events, predicate properties,
 * comparators, actions, slots/inventories, and the two version profiles.
 * Tokenizer/Parser/Validator/Generator and the public grammar.php JSON dump
 * all read from here so the form's dropdowns can never drift from what the
 * parser/generator actually accept.
 */
final class GrammarDefinition
{
    public const PROFILE_A = 'A'; // Minecraft 1.12.2
    public const PROFILE_B = 'B'; // Minecraft 1.14.x / 1.16.x / 1.17.x / 1.18.x

    public const PROFILES = [
        self::PROFILE_A => [
            'label' => 'Minecraft 1.12.2',
            'versions' => ['1.12.2'],
        ],
        self::PROFILE_B => [
            'label' => 'Minecraft 1.14.x, 1.16.x, 1.17.x, or 1.18.x',
            'versions' => ['1.14.x', '1.16.x', '1.17.x', '1.18.x'],
        ],
    ];

    /**
     * Canonical predicate property name => alias words TSL accepts + value type.
     * Type drives which comparators are offered/valid: numeric | string | bool | list.
     */
    public const PROPERTIES = [
        'actor'    => ['aliases' => ['actor'], 'type' => 'string'],
        'message'  => ['aliases' => ['message'], 'type' => 'string'],
        'amount'   => ['aliases' => ['amount', 'donation_amount'], 'type' => 'numeric'],
        'currency' => ['aliases' => ['currency', 'donation_currency'], 'type' => 'string'],
        'title'    => ['aliases' => ['title'], 'type' => 'string'],
        'months'   => ['aliases' => ['months', 'month_count', 'subscription_months'], 'type' => 'numeric'],
        'badges'   => ['aliases' => ['badges', 'chat_badges'], 'type' => 'list'],
        'tier'     => ['aliases' => ['tier', 'subscription_tier'], 'type' => 'numeric'],
        'gifted'   => ['aliases' => ['gifted'], 'type' => 'bool'],
        'viewers'  => ['aliases' => ['viewers', 'viewer_count'], 'type' => 'numeric'],
        'raiders'  => ['aliases' => ['raiders', 'raider_count'], 'type' => 'numeric'],
    ];

    /**
     * Event name => profiles it's available on + canonical property keys valid on it.
     */
    public const EVENTS = [
        'Donation' => [
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'properties' => ['actor', 'message', 'amount', 'currency'],
        ],
        'JustGiving Donation' => [
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'properties' => ['actor', 'message', 'amount'],
        ],
        'ExtraLife Donation' => [
            'profiles' => [self::PROFILE_B],
            'properties' => ['actor', 'message', 'amount'],
        ],
        'Patreon Pledge' => [
            'profiles' => [self::PROFILE_B],
            'properties' => ['actor', 'message', 'amount'],
        ],
        'Tiltify Donation' => [
            'profiles' => [self::PROFILE_B],
            'properties' => ['actor', 'message', 'amount'],
        ],
        'TreatStream Treat' => [
            'profiles' => [self::PROFILE_B],
            'properties' => ['actor', 'title', 'amount'],
        ],
        'Loyalty Point Redemption' => [
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'properties' => ['actor', 'message', 'title'],
        ],
        'Twitch Channel Point Reward' => [
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'properties' => ['actor', 'message', 'title'],
        ],
        'Twitch Chat Message' => [
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'properties' => ['actor', 'message', 'months', 'badges'],
        ],
        'Twitch Follow' => [
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'properties' => ['actor'],
        ],
        'Twitch Subscription Gift' => [
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'properties' => ['actor', 'amount', 'tier'],
        ],
        'Twitch Subscription' => [
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'properties' => ['actor', 'message', 'months', 'tier', 'gifted'],
        ],
        'Twitch Host' => [
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'properties' => ['actor', 'viewers'],
        ],
        'Twitch Raid' => [
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'properties' => ['actor', 'raiders'],
        ],
        'Twitch Bits' => [
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'properties' => ['actor', 'message', 'amount'],
        ],
    ];

    /**
     * Comparator token => value types it applies to + whether it takes a
     * bracketed [min,max] literal instead of a single word.
     */
    public const COMPARATORS = [
        '='        => ['types' => ['numeric'], 'bracketed' => false],
        '>'        => ['types' => ['numeric'], 'bracketed' => false],
        '<'        => ['types' => ['numeric'], 'bracketed' => false],
        '>='       => ['types' => ['numeric'], 'bracketed' => false],
        '<='       => ['types' => ['numeric'], 'bracketed' => false],
        'IS'       => ['types' => ['string', 'bool', 'numeric'], 'bracketed' => false],
        'PREFIX'   => ['types' => ['string'], 'bracketed' => false],
        'POSTFIX'  => ['types' => ['string'], 'bracketed' => false],
        'CONTAINS' => ['types' => ['string', 'list'], 'bracketed' => false],
        'IN RANGE' => ['types' => ['numeric'], 'bracketed' => true],
    ];

    /** Multi-word comparator tokens, longest-first, for the tokenizer/parser to try matching. */
    public const MULTIWORD_COMPARATORS = ['IN RANGE'];

    public const SLOT_NAMES = [
        'main-hand', 'off-hand', 'helmet', 'chestplate', 'leggings', 'boots',
        'hotbar', 'randomly', 'everything',
    ];

    public const INVENTORY_NAMES = ['inventory', 'hotbar', 'armors'];

    public const OS_RUN_SHELLS = ['CMD', 'POWERSHELL', 'BASH'];
    public const OS_RUN_TARGETS = ['LOCAL', 'REMOTE'];

    public const WAIT_UNITS = ['minutes', 'seconds', 'milliseconds'];

    /**
     * Action name => shape metadata used by the parser/generator/UI.
     * 'meta' actions wrap one or more nested ActionInvocations.
     * 'profiles' restricts availability; 'metadataParam' flags the
     * Profile-A-only trailing numeric param on DROP/CHANGE.
     */
    /**
     * 'wraps' says how many nested ActionInvocations this action carries:
     * 'none' | 'single' (FOR, REFLECT) | 'multiple' (EITHER via OR, BOTH via AND).
     */
    public const ACTIONS = [
        'DROP' => [
            'wraps' => 'none',
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'params' => ['item_id', 'amount?', 'nbt?'],
            'metadataParam' => true,
            'summary' => 'Drops an item in the direction the streamer is facing.',
        ],
        'SUMMON' => [
            'wraps' => 'none',
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'params' => ['entity_id', 'coords?', 'nbt?'],
            'metadataParam' => false,
            'summary' => 'Summons an entity near the streamer.',
        ],
        'EXECUTE' => [
            'wraps' => 'none',
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'params' => ['commands'],
            'metadataParam' => false,
            'summary' => 'Runs one or more Minecraft commands as the streamer.',
        ],
        'THROW' => [
            'wraps' => 'none',
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'params' => ['slot'],
            'metadataParam' => false,
            'summary' => 'Drops the item from a slot (destroys nothing).',
        ],
        'CLEAR' => [
            'wraps' => 'none',
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'params' => ['slot'],
            'metadataParam' => false,
            'summary' => 'Destroys the item in a slot.',
        ],
        'SHUFFLE' => [
            'wraps' => 'none',
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'params' => ['inventoryOrRange'],
            'metadataParam' => false,
            'summary' => 'Shuffles items within an inventory/slot range.',
        ],
        'CHANGE' => [
            'wraps' => 'none',
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'params' => ['slot', 'into_item_id', 'amount?', 'nbt?'],
            'metadataParam' => true,
            'summary' => 'Replaces the item in a slot with another item.',
        ],
        'EITHER' => [
            'wraps' => 'multiple',
            'joiner' => 'OR',
            'supportsChance' => true,
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'params' => [],
            'metadataParam' => false,
            'summary' => 'Randomly runs one of several actions.',
        ],
        'BOTH' => [
            'wraps' => 'multiple',
            'joiner' => 'AND',
            'supportsInstantly' => true,
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'params' => [],
            'metadataParam' => false,
            'summary' => 'Runs several actions in sequence.',
        ],
        'NOTHING' => [
            'wraps' => 'none',
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'params' => [],
            'metadataParam' => false,
            'summary' => 'Does nothing (optionally still shows a message).',
        ],
        'FOR' => [
            'wraps' => 'single',
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'params' => ['count'],
            'metadataParam' => false,
            'summary' => 'Repeats an action N times.',
        ],
        'WAIT' => [
            'wraps' => 'none',
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'params' => ['amount', 'unit'],
            'metadataParam' => false,
            'summary' => 'Pauses before continuing (pair with BOTH/FOR).',
        ],
        'REFLECT' => [
            'wraps' => 'single',
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'params' => ['targets'],
            'metadataParam' => false,
            'summary' => 'Mirrors an action to other players.',
        ],
        'OS_RUN' => [
            'wraps' => 'none',
            'profiles' => [self::PROFILE_A, self::PROFILE_B],
            'params' => ['target', 'shell', 'script'],
            'metadataParam' => false,
            'summary' => 'Runs a shell script locally or on the streamer machine.',
        ],
    ];

    /** Action names that wrap nested action(s) — used by parser/generator/UI recursion. */
    public const META_ACTIONS = ['EITHER', 'BOTH', 'FOR', 'REFLECT'];

    /** Action name reachable as a *nested* child in the v1 builder UI (one level deep, non-meta only). */
    public static function isSimpleAction(string $action): bool
    {
        return isset(self::ACTIONS[$action]) && !in_array($action, self::META_ACTIONS, true);
    }

    public static function resolveProperty(string $word): ?string
    {
        $needle = strtolower($word);
        foreach (self::PROPERTIES as $canonical => $def) {
            foreach ($def['aliases'] as $alias) {
                if (strtolower($alias) === $needle) {
                    return $canonical;
                }
            }
        }
        return null;
    }

    /** Case-insensitive event name lookup — returns the canonically-cased key, or null if unrecognized. */
    public static function resolveEventName(string $word): ?string
    {
        foreach (self::EVENTS as $canonical => $def) {
            if (strcasecmp($canonical, $word) === 0) {
                return $canonical;
            }
        }
        return null;
    }

    public static function isEventInProfile(string $event, string $profile): bool
    {
        return isset(self::EVENTS[$event]) && in_array($profile, self::EVENTS[$event]['profiles'], true);
    }

    public static function eventAllowsProperty(string $event, string $canonicalProperty): bool
    {
        return isset(self::EVENTS[$event]) && in_array($canonicalProperty, self::EVENTS[$event]['properties'], true);
    }

    public static function toArray(): array
    {
        return [
            'profiles' => self::PROFILES,
            'properties' => self::PROPERTIES,
            'events' => self::EVENTS,
            'comparators' => self::COMPARATORS,
            'slotNames' => self::SLOT_NAMES,
            'inventoryNames' => self::INVENTORY_NAMES,
            'osRunShells' => self::OS_RUN_SHELLS,
            'osRunTargets' => self::OS_RUN_TARGETS,
            'waitUnits' => self::WAIT_UNITS,
            'actions' => self::ACTIONS,
        ];
    }
}
