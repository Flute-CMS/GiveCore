<?php

namespace Flute\Modules\GiveCore\Services;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Rcon\RconService;

class AmxExpiredCleanupService
{
    protected const MOD_KEY = 'AmxModX';

    public function cleanup(): void
    {
        $servers = Server::query()->fetchAll();
        $processedDbs = [];

        foreach ($servers as $server) {
            $dbConnection = $server->getDbConnection(self::MOD_KEY);
            if (!$dbConnection) {
                continue;
            }

            $dbKey = $dbConnection->dbname;

            if (isset($processedDbs[$dbKey])) {
                continue;
            }

            $processedDbs[$dbKey] = true;

            try {
                $this->cleanupDatabase($dbConnection, $server);
            } catch (\Throwable $e) {
                logs('modules')->warning('AMX cleanup failed for DB: ' . $dbKey, [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function cleanupDatabase($dbConnection, Server $server): void
    {
        $prefix = $this->getPrefix($dbConnection->dbname, 'amx_');
        $db = dbal()->database($dbConnection->dbname);

        $expired = $db
            ->select()
            ->from($prefix . 'amxadmins')
            ->where('expired', '>', 0)
            ->andWhere('expired', '<', time())
            ->fetchAll();

        if (empty($expired)) {
            return;
        }

        $this->executeExpiryCommands($expired, $server);

        $ids = array_column($expired, 'id');

        try {
            $db->delete($prefix . 'admins_servers')
                ->where('admin_id', 'IN', $ids)
                ->run();
        } catch (\Throwable $e) {
            // admins_servers table may not exist in simpler setups
        }

        $db->delete($prefix . 'amxadmins')
            ->where('id', 'IN', $ids)
            ->run();

        logs('modules')->info('AMX cleanup: removed ' . count($ids) . ' expired admins from ' . $dbConnection->dbname);

        $this->sendRcon($server, 'amx_reloadadmins');
    }

    protected function executeExpiryCommands(array $expiredRecords, Server $server): void
    {
        $expiryCommand = $this->getExpiryCommand($server);

        if (!$expiryCommand) {
            return;
        }

        foreach ($expiredRecords as $record) {
            $command = str_replace(
                ['{steamid}', '{nickname}', '{ip}', '{access}'],
                [
                    $record['steamid'] ?? '',
                    $record['nickname'] ?? $record['username'] ?? '',
                    $record['steamid'] ?? '',
                    $record['access'] ?? '',
                ],
                $expiryCommand,
            );

            $this->sendRcon($server, $command);
        }
    }

    protected function getExpiryCommand(Server $server): ?string
    {
        if (!class_exists(\Flute\Modules\Shop\database\Entities\PurchaseHistory::class)) {
            return null;
        }

        $purchases = \Flute\Modules\Shop\database\Entities\PurchaseHistory::query()
            ->where('driver_type', 'amxmod')
            ->where('server_id', $server->id)
            ->orderBy('createdAt', 'DESC')
            ->limit(1)
            ->fetchAll();

        if (empty($purchases)) {
            return null;
        }

        $config = json_decode($purchases[0]->driver_config ?? '{}', true);

        return !empty($config['expiry_command']) ? $config['expiry_command'] : null;
    }

    protected function getPrefix(string $dbname, string $defaultPrefix = ''): string
    {
        $prefix = config("database.databases.{$dbname}.prefix");

        if ($prefix === null) {
            $prefix = config("database.connections.{$dbname}.prefix");
        }

        return empty($prefix) ? $defaultPrefix : '';
    }

    protected function sendRcon(Server $server, string $command): void
    {
        try {
            $rconService = app(RconService::class);

            if ($rconService->isAvailable($server)) {
                $rconService->execute($server, $command);
            }
        } catch (\Throwable $e) {
            // RCON is optional
        }
    }
}
