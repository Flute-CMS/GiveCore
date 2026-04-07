<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Support\AbstractDriver;

/**
 * Dynamic SQL driver that executes user-defined SQL queries.
 * Each instance is created from a config array:
 * [
 *     'alias'       => 'my_driver',
 *     'name'        => 'My Plugin',
 *     'description' => 'Custom delivery driver',
 *     'icon'        => 'ph.bold.plug-bold',
 *     'category'    => 'Minecraft',
 *     'mod_key'     => 'MyPlugin',      // DB connection key for server
 *     'sql_deliver' => 'INSERT INTO ...',// SQL with placeholders
 *     'sql_check'   => 'SELECT ...',    // Optional check SQL
 *     'fields'      => [                 // Custom fields
 *         'group' => ['type' => 'text', 'label' => 'Group', 'required' => true],
 *     ],
 *     'identifier'  => 'steam',         // steam | minecraft | name
 * ]
 *
 * SQL placeholders: {steam_id}, {steam32}, {steam64}, {account_id},
 * {uuid}, {name}, {email}, {time}, {unix_expire}, {field:xxx}
 */
class CustomSqlDriver extends AbstractDriver
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function alias(): string
    {
        return $this->config['alias'] ?? 'custom_sql';
    }

    public function name(): string
    {
        return $this->config['name'] ?? 'Custom SQL';
    }

    public function description(): string
    {
        return $this->config['description'] ?? '';
    }

    public function icon(): string
    {
        return $this->config['icon'] ?? 'ph.bold.database-bold';
    }

    public function category(): string
    {
        return $this->config['category'] ?? 'other';
    }

    public function requiredSocial(array $config = []): ?string
    {
        $haystack = json_encode($this->config) . json_encode($config);
        if (preg_match('/\{steam32\}|\{steam64\}|\{accountId\}/i', $haystack)) {
            return 'Steam';
        }
        if (preg_match('/\{nick\}|\{minecraft\}|\{username\}/i', $haystack)) {
            return 'Minecraft';
        }
        return null;
    }

    public function dbConnectionKey(): ?string
    {
        return $this->config['mod_key'] ?? null;
    }

    public function deliverFields(): array
    {
        return $this->config['fields'] ?? [];
    }

    public function deliver(
        User $user,
        Server $server,
        array $additional = [],
        ?int $timeId = null,
        bool $ignoreErrors = false,
    ): bool {
        $sql = $this->config['sql_deliver'] ?? '';
        if (empty($sql)) {
            throw new BadConfigurationException('sql_deliver', $server->name);
        }

        $modKey = $this->dbConnectionKey();
        $dbConnection = $modKey ? $server->getDbConnection($modKey) : null;

        if ($modKey && !$dbConnection) {
            throw new BadConfigurationException($modKey, $server->name);
        }

        $dbname = $dbConnection?->dbname ?? 'default';
        $db = dbal()->database($dbname);
        $prefix = $this->getPrefix($dbname, '');

        $time = (int) ($timeId ?: ($additional['time'] ?? 0));
        $params = $this->buildParams($user, $additional, $time, $prefix);

        // Build parameterized query: replace {placeholder} with :placeholder bindings
        $bindings = [];
        $paramIndex = 0;
        foreach ($params as $placeholder => $value) {
            $bindName = 'p' . $paramIndex++;
            $sql = str_replace($placeholder, ':' . $bindName, $sql);
            $bindings[$bindName] = $value;
        }

        $db->execute($sql, $bindings);

        return true;
    }

    protected function buildParams(User $user, array $additional, int $time, string $prefix): array
    {
        $params = [
            '{name}' => $user->name ?? '',
            '{email}' => $user->email ?? '',
            '{user_id}' => $user->id ?? 0,
            '{time}' => $time,
            '{unix_expire}' => $time > 0 ? time() + $time : 0,
            '{unix_now}' => time(),
            '{prefix}' => $prefix,
        ];

        // Steam identifiers
        $identifier = $this->config['identifier'] ?? 'steam';
        if ($identifier === 'steam' || $identifier === 'both') {
            $steamValue = $this->getUserSteamId($user);
            if ($steamValue) {
                $steamObj = steam()->steamid($steamValue);
                $params['{steam_id}'] = $steamValue;
                $params['{steam32}'] = $steamObj->RenderSteam2();
                $params['{steam64}'] = $steamObj->ConvertToUInt64();
                $params['{account_id}'] = $steamObj->GetAccountID();
            }
        }

        // Minecraft UUID
        if ($identifier === 'minecraft' || $identifier === 'both') {
            $mc = $user->getSocialNetwork('Minecraft') ?? $user->getSocialNetwork('minecraft');
            $params['{uuid}'] = $mc?->value ?? '';
        }

        // Custom field values from deliver form
        foreach ($additional as $key => $value) {
            if ($key !== 'time' && $key !== '__simulate') {
                $params['{field:' . $key . '}'] = $value;
            }
        }

        return $params;
    }
}
