<?php

namespace Flute\Modules\GiveCore\Support;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;

/**
 * Shared utilities for game-server drivers (Steam ID, DB prefix, server lookup, etc.).
 * Can be used by both delivery and condition-check drivers.
 */
trait DriverHelpers
{
    /**
     * Get user's Steam ID from social networks.
     */
    protected function getUserSteamId(User $user): ?string
    {
        $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');

        return $steam?->value ?: null;
    }

    /**
     * Get a server entity by ID.
     */
    protected function getServerById(int $id): ?Server
    {
        return rep(Server::class)->findByPK($id);
    }

    /**
     * Get all servers that have a DB connection for the given key.
     *
     * @return Server[]
     */
    protected function getServersWithConnection(string $connectionKey): array
    {
        $servers = Server::query()->fetchAll();
        $result = [];

        foreach ($servers as $server) {
            if ($server->getDbConnection($connectionKey)) {
                $result[] = $server;
            }
        }

        return $result;
    }

    /**
     * Build select options [serverId => serverName] for servers with given connection.
     */
    protected function getServerOptions(string $connectionKey): array
    {
        $options = [];

        foreach ($this->getServersWithConnection($connectionKey) as $server) {
            $options[$server->id] = $server->name;
        }

        return $options;
    }

    /**
     * Get DB table prefix. Returns defaultPrefix only if the connection has no prefix configured.
     */
    protected function getDbPrefix(string $dbname, string $defaultPrefix = ''): string
    {
        $prefix = config("database.databases.{$dbname}.prefix");

        if ($prefix === null) {
            $prefix = config("database.connections.{$dbname}.prefix");
        }

        return empty($prefix) ? $defaultPrefix : '';
    }

    /**
     * Compare two numeric values with an operator.
     */
    protected function compareValues(float $actual, string $operator, float $expected): bool
    {
        return match ($operator) {
            '>=' => $actual >= $expected,
            '>' => $actual > $expected,
            '=' => abs($actual - $expected) < 0.001,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            default => false,
        };
    }
}
