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
        $bindType = $config['bind_type'] ?? 'steamid';

        if ($bindType === 'choice') {
            $chosen = $config['amx_bind_type'] ?? null;
            if (in_array($chosen, ['nick_password', 'ip', 'ip_password'], true)) {
                return null;
            }
            return 'Steam';
        }

        if (in_array($bindType, ['nick_password', 'ip', 'ip_password'], true)) {
            return null;
        }

        return 'Steam';
    }

    public function purchaseFields(array $config = []): array
    {
        $bindType = $config['bind_type'] ?? 'steamid';

        if ($bindType === 'steamid') {
            return [];
        }

        $fields = [];
        $isChoice = $bindType === 'choice';

        if ($isChoice) {
            $fields['amx_bind_type'] = [
                'type' => 'radio',
                'label' => __('givecore.fields.amx_choose_bind_type'),
                'required' => true,
                'options' => [
                    'nick_password' => __('givecore.fields.amx_bind_nick_password'),
                    'steamid' => __('givecore.fields.amx_bind_steamid'),
                    'steamid_password' => __('givecore.fields.amx_bind_steamid_password'),
                    'ip' => __('givecore.fields.amx_bind_ip'),
                    'ip_password' => __('givecore.fields.amx_bind_ip_password'),
                ],
                'default' => 'nick_password',
            ];
        }

        if ($isChoice || $bindType === 'nick_password') {
            $fields['amx_nickname'] = [
                'type' => 'text',
                'label' => __('givecore.fields.amx_nickname'),
                'required' => false,
                'placeholder' => __('givecore.fields.amx_nickname_placeholder'),
                'autofill' => 'username',
                'show_when' => $isChoice ? ['amx_bind_type' => 'nick_password'] : null,
            ];
        }

        if ($isChoice || in_array($bindType, ['ip', 'ip_password'], true)) {
            $fields['amx_ip'] = [
                'type' => 'text',
                'label' => __('givecore.fields.amx_ip'),
                'required' => false,
                'placeholder' => __('givecore.fields.amx_ip_placeholder'),
                'show_when' => $isChoice ? ['amx_bind_type' => ['ip', 'ip_password']] : null,
            ];
        }

        if ($isChoice || in_array($bindType, ['nick_password', 'steamid_password', 'ip_password'], true)) {
            $fields['amx_password'] = [
                'type' => 'password',
                'label' => __('givecore.fields.amx_password'),
                'required' => false,
                'placeholder' => __('givecore.fields.amx_password_placeholder'),
                'show_when' => $isChoice ? ['amx_bind_type' => ['nick_password', 'steamid_password', 'ip_password']] : null,
            ];
        }

        return $fields;
    }

    public function postPurchaseInfo(array $additional): ?string
    {
        $bindType = $additional['bind_type'] ?? 'steamid';
        if ($bindType === 'choice') {
            $bindType = $additional['amx_bind_type'] ?? 'steamid';
        }

        if (in_array($bindType, ['nick_password', 'steamid_password', 'ip_password'], true)) {
            return __('givecore.fields.amx_post_purchase_password_hint');
        }

        return null;
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
        $bindType = $additional['bind_type'] ?? 'steamid';
        if ($bindType === 'choice') {
            $bindType = $additional['amx_bind_type'] ?? 'steamid';
        }

        $needsSteam = in_array($bindType, ['steamid', 'steamid_password'], true);

        if ($needsSteam) {
            $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');
            if (!$steam?->value) {
                throw new UserSocialException('Steam');
            }
        }

        $dbConnection = $server->getDbConnection(static::MOD_KEY);
        if (!$dbConnection) {
            throw new BadConfigurationException(static::MOD_KEY, $server->name);
        }

        $prefix = $this->getPrefix($dbConnection->dbname, 'freshbans_');
        $access = $additional['access'] ?? 'bcdefg';

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

        $nickname = $user->name ?? 'Player';
        $authIdentifier = $this->resolveAuthIdentifier($user, $bindType, $additional);
        $password = $this->resolvePassword($bindType, $additional);

        $db = dbal()->database($dbConnection->dbname);

        $existing = $db
            ->select()
            ->from($prefix . 'amxadmins')
            ->where('steamid', $authIdentifier)
            ->fetchAll();

        if (!empty($existing)) {
            $record = $existing[0];
            $currentExpiry = (int) $record['expired'];

            $newExpiry = $expiry;
            if ($currentExpiry > 0 && $expiry > 0) {
                $newExpiry = max($currentExpiry, time()) + $time;
            } elseif ($expiry === 0) {
                $newExpiry = 0;
            }

            $updateData = [
                'access' => $access,
                'flags' => $authFlags,
                'expired' => $newExpiry,
                'days' => $newExpiry > 0 ? (int) ceil(($newExpiry - time()) / 86400) : 0,
                'nickname' => $nickname,
            ];
            if ($password !== '') {
                $updateData['password'] = $password;
            }

            $db
                ->update($prefix . 'amxadmins', $updateData)
                ->where('id', $record['id'])
                ->run();
        } else {
            $db
                ->insert($prefix . 'amxadmins')
                ->values([
                    'username' => $nickname,
                    'password' => $password,
                    'access' => $access,
                    'flags' => $authFlags,
                    'steamid' => $authIdentifier,
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

    protected function resolveAuthIdentifier(User $user, string $bindType, array $additional): string
    {
        if ($bindType === 'nick_password') {
            $nick = trim($additional['amx_nickname'] ?? '');
            if ($nick === '') {
                throw new \RuntimeException(__('givecore.fields.amx_nickname_required'));
            }
            return $nick;
        }

        if (in_array($bindType, ['ip', 'ip_password'], true)) {
            $ip = trim($additional['amx_ip'] ?? '');
            if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
                throw new \RuntimeException(__('givecore.fields.amx_ip_required'));
            }
            return $ip;
        }

        $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');
        return steam()->steamid($steam->value)->RenderSteam2();
    }

    protected function resolvePassword(string $bindType, array $additional): string
    {
        if (in_array($bindType, ['nick_password', 'steamid_password', 'ip_password'], true)) {
            $password = trim($additional['amx_password'] ?? '');
            if ($password === '') {
                throw new \RuntimeException(__('givecore.fields.amx_password_required'));
            }
            return $password;
        }
        return '';
    }
}
