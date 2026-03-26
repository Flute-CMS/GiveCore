<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Core\Rcon\RconService;
use Flute\Modules\GiveCore\Contracts\CheckableInterface;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
use Flute\Modules\GiveCore\Support\CheckableTrait;
use Nette\Utils\Json;

class AmxModDriver extends AbstractDriver implements CheckableInterface
{
    use CheckableTrait;

    protected const MOD_KEY = 'AmxModX';

    public function alias(): string
    {
        return 'amxmod';
    }

    public function name(): string
    {
        return __('givecore.drivers.amxmod.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.amxmod.description');
    }

    public function icon(): string
    {
        return 'ph.bold.shield-star-bold';
    }

    public function category(): string
    {
        return 'CS 1.6';
    }

    public function deliverFields(): array
    {
        return [
            'access' => [
                'type' => 'text',
                'label' => __('givecore.fields.amx_access'),
                'required' => true,
                'placeholder' => 'abcdefghijklmnopqrstu',
                'help' => __('givecore.fields.amx_access_help'),
            ],
            'flags' => [
                'type' => 'text',
                'label' => __('givecore.fields.amx_flags'),
                'required' => false,
                'placeholder' => 'ce',
                'default' => 'ce',
                'help' => __('givecore.fields.amx_flags_help'),
            ],
        ];
    }

    public function checkFields(): array
    {
        return [
            'server_id' => [
                'type' => 'select',
                'label' => __('givecore.fields.server'),
                'required' => true,
                'options' => $this->getServerOptions(static::MOD_KEY),
            ],
        ];
    }

    public function check(User $user, array $params = []): bool
    {
        $serverId = $params['server_id'] ?? null;
        if (!$serverId) {
            return false;
        }

        $server = $this->getServerById((int) $serverId);
        if (!$server) {
            return false;
        }

        $steamId = $this->getUserSteamId($user);
        if (!$steamId) {
            return false;
        }

        $dbConnection = $server->getDbConnection(static::MOD_KEY);
        if (!$dbConnection) {
            return false;
        }

        $prefix = $this->getPrefix($dbConnection->dbname, 'amx_');
        $steamId2 = steam()->steamid($steamId)->RenderSteam2();

        $db = dbal()->database($dbConnection->dbname);
        $results = $db
            ->select()
            ->from($prefix . 'amxadmins')
            ->where('steamid', $steamId2)
            ->fetchAll();

        if (empty($results)) {
            // Check VALVE_ prefix variant
            $valveId = 'VALVE_' . substr($steamId2, 6);
            $results = $db
                ->select()
                ->from($prefix . 'amxadmins')
                ->where('steamid', $valveId)
                ->fetchAll();
        }

        if (empty($results)) {
            return false;
        }

        $record = $results[0];
        $expired = (int) $record['expired'];

        return $expired === 0 || $expired > time();
    }

    public function deliver(
        User $user,
        Server $server,
        array $additional = [],
        ?int $timeId = null,
        bool $ignoreErrors = false,
    ): bool {
        $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');
        if (!$steam?->value) {
            throw new UserSocialException('Steam');
        }

        $dbConnection = $server->getDbConnection(static::MOD_KEY);
        if (!$dbConnection) {
            throw new BadConfigurationException(static::MOD_KEY, $server->name);
        }

        $prefix = $this->getPrefix($dbConnection->dbname, 'amx_');
        $access = $additional['access'] ?? 'bcdefg';
        $flags = $additional['flags'] ?? 'ce';
        $time = $timeId ?: $additional['time'] ?? 0;
        $expiry = $time > 0 ? time() + $time : 0;
        $days = $time > 0 ? (int) ceil($time / 86400) : 0;

        $steamId2 = steam()->steamid($steam->value)->RenderSteam2();
        $nickname = $user->name ?? 'Player';

        $db = dbal()->database($dbConnection->dbname);

        // Check if admin already exists
        $existing = $db
            ->select()
            ->from($prefix . 'amxadmins')
            ->where('steamid', $steamId2)
            ->fetchAll();

        if (!empty($existing)) {
            $record = $existing[0];
            $currentExpiry = (int) $record['expired'];

            if ($currentExpiry === 0 || $currentExpiry > time()) {
                if (!$ignoreErrors) {
                    // Extend time
                    $newExpiry = $expiry > 0 ? max($currentExpiry, time()) + $time : 0;

                    $db
                        ->update($prefix . 'amxadmins')
                        ->set('access', $access)
                        ->set('flags', $flags)
                        ->set('expired', $newExpiry)
                        ->set('days', $newExpiry > 0 ? (int) ceil(( $newExpiry - time() ) / 86400) : 0)
                        ->set('nickname', $nickname)
                        ->where('id', $record['id'])
                        ->run();

                    return true;
                }
            }

            // Expired — update in place
            $db
                ->update($prefix . 'amxadmins')
                ->set('access', $access)
                ->set('flags', $flags)
                ->set('expired', $expiry)
                ->set('days', $days)
                ->set('nickname', $nickname)
                ->set('created', time())
                ->where('id', $record['id'])
                ->run();

            return true;
        }

        // Insert new admin
        $db
            ->insert($prefix . 'amxadmins')
            ->values([
                'username' => $nickname,
                'password' => '',
                'access' => $access,
                'flags' => $flags,
                'steamid' => $steamId2,
                'nickname' => $nickname,
                'ashow' => 1,
                'created' => time(),
                'expired' => $expiry,
                'days' => $days,
            ])
            ->run();

        // Assign to server
        $dbParams = Json::decode($dbConnection->additional ?? '{}');
        $serverId = (int) ( $dbParams->sid ?? $dbParams->server_id ?? 0 );

        if ($serverId > 0) {
            $adminId = $db
                ->select('MAX(id) as id')
                ->from($prefix . 'amxadmins')
                ->where('steamid', $steamId2)
                ->fetchAll();

            if (!empty($adminId)) {
                $db
                    ->insert($prefix . 'admins_servers')
                    ->values([
                        'admin_id' => $adminId[0]['id'],
                        'server_id' => $serverId,
                        'custom_flags' => '',
                        'use_static_bantime' => 'yes',
                    ])
                    ->run();
            }
        }

        // RCON reload
        $this->sendRcon($server, 'amx_reloadadmins');

        return true;
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
