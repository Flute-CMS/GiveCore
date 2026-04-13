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

    public const BIND_STEAMID = 'steamid';
    public const BIND_STEAMID_MANUAL = 'steamid_manual';
    public const BIND_NICK_PASSWORD = 'nick_password';
    public const BIND_STEAMID_PASSWORD = 'steamid_password';
    public const BIND_IP = 'ip';
    public const BIND_IP_PASSWORD = 'ip_password';

    protected const AUTH_FLAGS = [
        self::BIND_STEAMID => 'ce',
        self::BIND_STEAMID_MANUAL => 'ce',
        self::BIND_NICK_PASSWORD => 'a',
        self::BIND_STEAMID_PASSWORD => 'c',
        self::BIND_IP => 'de',
        self::BIND_IP_PASSWORD => 'd',
    ];

    protected const ALL_BIND_TYPES = [
        self::BIND_STEAMID,
        self::BIND_STEAMID_MANUAL,
        self::BIND_NICK_PASSWORD,
        self::BIND_STEAMID_PASSWORD,
        self::BIND_IP,
        self::BIND_IP_PASSWORD,
    ];

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
        $bindType = $config['bind_type'] ?? self::BIND_STEAMID;

        $noOAuthTypes = [self::BIND_STEAMID_MANUAL, self::BIND_NICK_PASSWORD, self::BIND_IP, self::BIND_IP_PASSWORD];

        if ($bindType === 'choice') {
            $chosen = $config['amx_bind_type'] ?? null;

            if (in_array($chosen, $noOAuthTypes, true)) {
                return null;
            }

            return 'Steam';
        }

        if (in_array($bindType, $noOAuthTypes, true)) {
            return null;
        }

        return 'Steam';
    }

    public function purchaseFields(array $config = []): array
    {
        $bindType = $config['bind_type'] ?? self::BIND_STEAMID;

        if ($bindType === self::BIND_STEAMID) {
            return [];
        }

        $fields = [];
        $isChoice = $bindType === 'choice';
        $needsManualSteam = in_array($bindType, [self::BIND_STEAMID_MANUAL, 'choice'], true);
        $needsNick = in_array($bindType, [self::BIND_NICK_PASSWORD, 'choice'], true);
        $needsPassword = in_array($bindType, [self::BIND_NICK_PASSWORD, self::BIND_STEAMID_PASSWORD, self::BIND_IP_PASSWORD, 'choice'], true);
        $needsIp = in_array($bindType, [self::BIND_IP, self::BIND_IP_PASSWORD, 'choice'], true);

        if ($isChoice) {
            $fields['amx_bind_type'] = [
                'type' => 'radio',
                'label' => __('givecore.fields.amx_choose_bind_type'),
                'required' => true,
                'options' => [
                    self::BIND_NICK_PASSWORD => __('givecore.fields.amx_bind_nick_password'),
                    self::BIND_STEAMID => __('givecore.fields.amx_bind_steamid'),
                    self::BIND_STEAMID_MANUAL => __('givecore.fields.amx_bind_steamid_manual'),
                    self::BIND_STEAMID_PASSWORD => __('givecore.fields.amx_bind_steamid_password'),
                    self::BIND_IP => __('givecore.fields.amx_bind_ip'),
                    self::BIND_IP_PASSWORD => __('givecore.fields.amx_bind_ip_password'),
                ],
                'default' => self::BIND_NICK_PASSWORD,
            ];
        }

        if ($needsManualSteam) {
            $fields['amx_steamid'] = [
                'type' => 'text',
                'label' => __('givecore.fields.amx_steamid'),
                'required' => false,
                'placeholder' => __('givecore.fields.amx_steamid_placeholder'),
                'show_when' => $isChoice ? ['amx_bind_type' => self::BIND_STEAMID_MANUAL] : null,
            ];
        }

        if ($needsNick) {
            $fields['amx_nickname'] = [
                'type' => 'text',
                'label' => __('givecore.fields.amx_nickname'),
                'required' => false,
                'placeholder' => __('givecore.fields.amx_nickname_placeholder'),
                'autofill' => 'username',
                'show_when' => $isChoice ? ['amx_bind_type' => self::BIND_NICK_PASSWORD] : null,
            ];
        }

        if ($needsIp) {
            $fields['amx_ip'] = [
                'type' => 'text',
                'label' => __('givecore.fields.amx_ip'),
                'required' => false,
                'placeholder' => __('givecore.fields.amx_ip_placeholder'),
                'show_when' => $isChoice ? ['amx_bind_type' => [self::BIND_IP, self::BIND_IP_PASSWORD]] : null,
            ];
        }

        if ($needsPassword) {
            $passwordBindTypes = [self::BIND_NICK_PASSWORD, self::BIND_STEAMID_PASSWORD, self::BIND_IP_PASSWORD];
            $fields['amx_password'] = [
                'type' => 'password',
                'label' => __('givecore.fields.amx_password'),
                'required' => false,
                'placeholder' => __('givecore.fields.amx_password_placeholder'),
                'show_when' => $isChoice ? ['amx_bind_type' => $passwordBindTypes] : null,
            ];
        }

        return $fields;
    }

    public function postPurchaseInfo(array $additional): ?string
    {
        $bindType = $this->resolveBindType($additional);

        $hasPassword = in_array($bindType, [self::BIND_NICK_PASSWORD, self::BIND_STEAMID_PASSWORD, self::BIND_IP_PASSWORD], true);

        if (!$hasPassword) {
            return null;
        }

        return __('givecore.fields.amx_post_purchase_password_hint');
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
                'default' => self::BIND_STEAMID,
                'options' => [
                    self::BIND_STEAMID => __('givecore.fields.amx_bind_steamid'),
                    self::BIND_STEAMID_MANUAL => __('givecore.fields.amx_bind_steamid_manual'),
                    self::BIND_NICK_PASSWORD => __('givecore.fields.amx_bind_nick_password'),
                    self::BIND_STEAMID_PASSWORD => __('givecore.fields.amx_bind_steamid_password'),
                    self::BIND_IP => __('givecore.fields.amx_bind_ip'),
                    self::BIND_IP_PASSWORD => __('givecore.fields.amx_bind_ip_password'),
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
        $simulate = false;
        if (array_key_exists('__simulate', $additional)) {
            $simulate = (bool) $additional['__simulate'];
            unset($additional['__simulate']);
        }

        $bindType = $this->resolveBindType($additional);
        $needsOAuthSteam = in_array($bindType, [self::BIND_STEAMID, self::BIND_STEAMID_PASSWORD], true);

        if ($needsOAuthSteam) {
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
        $authFlags = self::AUTH_FLAGS[$bindType] ?? 'ce';

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

        if (!$simulate) {
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
        }

        return !$simulate;
    }

    protected function resolveBindType(array $additional): string
    {
        $bindType = $additional['bind_type'] ?? self::BIND_STEAMID;

        if ($bindType === 'choice') {
            $chosen = $additional['amx_bind_type'] ?? self::BIND_STEAMID;

            return in_array($chosen, self::ALL_BIND_TYPES, true) ? $chosen : self::BIND_STEAMID;
        }

        return in_array($bindType, self::ALL_BIND_TYPES, true) ? $bindType : self::BIND_STEAMID;
    }

    protected function resolveAuthIdentifier(User $user, string $bindType, array $additional): string
    {
        if ($bindType === self::BIND_NICK_PASSWORD) {
            $nick = trim($additional['amx_nickname'] ?? '');
            if ($nick === '') {
                throw new \RuntimeException(__('givecore.fields.amx_nickname_required'));
            }
            return $nick;
        }

        if (in_array($bindType, [self::BIND_IP, self::BIND_IP_PASSWORD], true)) {
            $ip = trim($additional['amx_ip'] ?? '');
            if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
                throw new \RuntimeException(__('givecore.fields.amx_ip_required'));
            }
            return $ip;
        }

        if ($bindType === self::BIND_STEAMID_MANUAL) {
            $rawSteamId = trim($additional['amx_steamid'] ?? '');

            if ($rawSteamId === '') {
                throw new \RuntimeException(__('givecore.fields.amx_steamid_required'));
            }

            try {
                return steam()->steamid($rawSteamId)->RenderSteam2();
            } catch (\Throwable) {
                throw new \RuntimeException(__('givecore.fields.amx_steamid_invalid'));
            }
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
