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

class FreshBansAdminDriver extends AbstractDriver implements CheckableInterface
{
    use CheckableTrait;

    protected const MOD_KEY = 'FreshBans';

    public function alias(): string
    {
        return 'freshbans_admin';
    }

    public function name(): string
    {
        return __('givecore.drivers.freshbans_admin.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.freshbans_admin.description');
    }

    public function icon(): string
    {
        return 'ph.bold.shield-star-bold';
    }

    public function category(): string
    {
        return 'CS 1.6';
    }

    public function sourceUrl(): ?string
    {
        return 'https://dev-cs.ru/resources/freshbans.90/';
    }

    public function supportedGames(): array
    {
        return ['CS 1.6', 'Half-Life'];
    }

    public function requiredSocial(array $config = []): ?string
    {
        return 'Steam';
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
            'expiry_command' => [
                'type' => 'text',
                'label' => __('givecore.fields.amx_expiry_command'),
                'required' => false,
                'placeholder' => __('givecore.fields.amx_expiry_command_placeholder'),
                'help' => __('givecore.fields.amx_expiry_command_help'),
            ],
            'bind_type' => [
                'type' => 'select',
                'label' => __('givecore.fields.amx_bind_type'),
                'required' => false,
                'default' => 'steamid',
                'options' => [
                    'steamid' => __('givecore.fields.amx_bind_steamid'),
                    'nick_password' => __('givecore.fields.amx_bind_nick_password'),
                    'steamid_password' => __('givecore.fields.amx_bind_steamid_password'),
                    'ip' => __('givecore.fields.amx_bind_ip'),
                    'ip_password' => __('givecore.fields.amx_bind_ip_password'),
                    'choice' => __('givecore.fields.amx_bind_choice'),
                ],
                'help' => __('givecore.fields.amx_bind_type_help'),
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

        $dbConnection = $server->getDbConnection(static::MOD_KEY);
        if (!$dbConnection) {
            return false;
        }

        $prefix = $this->getPrefix($dbConnection->dbname, 'freshbans_');
        $db = dbal()->database($dbConnection->dbname);

        $steamId = $this->getUserSteamId($user);
        if (!$steamId) {
            return false;
        }

        $steamId2 = steam()->steamid($steamId)->RenderSteam2();

        $results = $db
            ->select()
            ->from($prefix . 'amxadmins')
            ->where('steamid', $steamId2)
            ->fetchAll();

        if (empty($results)) {
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

        $prefix = $this->getPrefix($dbConnection->dbname, 'freshbans_');
        $access = $additional['access'] ?? 'bcdefg';
        $bindType = $additional['bind_type'] ?? 'steamid';

        $authFlags = match ($bindType) {
            'nick_password' => 'a',
            'steamid_password' => 'c',
            'ip' => 'de',
            'ip_password' => 'd',
            default => 'ce',
        };

        $time = (int) ($timeId ?: ($additional['time'] ?? 0));
        $expiry = $time > 0 ? time() + $time : 0;
        $days = $time > 0 ? (int) ceil($time / 86400) : 0;

        $steamId2 = steam()->steamid($steam->value)->RenderSteam2();
        $nickname = $user->name ?? 'Player';

        $db = dbal()->database($dbConnection->dbname);

        $existing = $db
            ->select()
            ->from($prefix . 'amxadmins')
            ->where('steamid', $steamId2)
            ->fetchAll();

        if (empty($existing)) {
            $valveId = 'VALVE_' . substr($steamId2, 6);
            $existing = $db
                ->select()
                ->from($prefix . 'amxadmins')
                ->where('steamid', $valveId)
                ->fetchAll();
        }

        if (!empty($existing)) {
            $record = $existing[0];
            $currentExpiry = (int) $record['expired'];

            $newExpiry = $expiry;
            if ($currentExpiry > 0 && $expiry > 0) {
                $newExpiry = max($currentExpiry, time()) + $time;
            } elseif ($expiry === 0) {
                $newExpiry = 0;
            }

            $db
                ->update($prefix . 'amxadmins', [
                    'access' => $access,
                    'flags' => $authFlags,
                    'expired' => $newExpiry,
                    'days' => $newExpiry > 0 ? (int) ceil(($newExpiry - time()) / 86400) : 0,
                    'nickname' => $nickname,
                ])
                ->where('id', $record['id'])
                ->run();
        } else {
            $db
                ->insert($prefix . 'amxadmins')
                ->values([
                    'username' => $nickname,
                    'password' => '',
                    'access' => $access,
                    'flags' => $authFlags,
                    'steamid' => $steamId2,
                    'nickname' => $nickname,
                    'ashow' => 1,
                    'created' => time(),
                    'expired' => $expiry,
                    'days' => $days,
                ])
                ->run();
        }

        try {
            $rconService = app(RconService::class);
            if ($rconService->isAvailable($server)) {
                $rconService->execute($server, 'amx_reloadadmins');
            }
        } catch (\Throwable $e) {
        }

        return true;
    }
}
