<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Exception;
use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Core\Rcon\RconService;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\GiveDriverException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;

class RconDriver extends AbstractDriver
{
    protected $time;

    // ── Metadata ───────────────────────────────────────────────────

    public function alias(): string
    {
        return 'rcon';
    }

    public function name(): string
    {
        return __('givecore.drivers.rcon.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.rcon.description');
    }

    public function icon(): string
    {
        return 'ph.bold.terminal-bold';
    }

    public function category(): string
    {
        return 'rcon';
    }

    public function deliverFields(): array
    {
        return [
            'command' => [
                'type' => 'textarea',
                'label' => __('givecore.fields.command'),
                'required' => true,
                'placeholder' => __('givecore.fields.command_placeholder'),
                'help' => __('givecore.fields.command_help'),
            ],
        ];
    }

    // ── Delivery ───────────────────────────────────────────────────

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

        $this->validateServerAndCommand($server, $additional);

        $commandLines = preg_split('/\r\n|\r|\n/', $additional['command']);
        $commands = [];

        foreach ($commandLines as $line) {
            if (strpos($line, ';') !== false) {
                $lineCommands = explode(';', $line);
                foreach ($lineCommands as $cmd) {
                    if (trim($cmd) !== '') {
                        $commands[] = trim($cmd);
                    }
                }
            } else {
                if (trim($line) !== '') {
                    $commands[] = trim($line);
                }
            }
        }

        $steam = $this->getSteamIdForCommand($user, $additional['command']);

        if ($timeId !== null) {
            $this->time = $timeId;
        }

        if ($simulate) {
            return false;
        }

        try {
            $rconService = app(RconService::class);
            $this->executeCommands($rconService, $server, $commands, $steam, $user, $additional);

            return true;
        } catch (Exception $e) {
            throw new GiveDriverException($e->getMessage());
        }
    }

    // ── Private helpers ────────────────────────────────────────────

    private function validateServerAndCommand(Server $server, array $additional): void
    {
        if (!$server->rcon) {
            throw new BadConfigurationException("Server {$server->name} rcon empty");
        }

        if (!isset($additional['command'])) {
            throw new BadConfigurationException('command');
        }
    }

    private function getSteamIdForCommand(User $user, string $command): ?string
    {
        if (preg_match('/{steam32}|{steam64}|{accountId}/i', $command)) {
            $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');

            if (!$steam) {
                throw new UserSocialException('Steam');
            }

            return $steam->value;
        }

        return null;
    }

    private function executeCommands(
        RconService $rconService,
        Server $server,
        array $commands,
        ?string $steam,
        User $user,
        array $additional,
    ): void {
        foreach ($commands as $command) {
            try {
                $rconService->execute($server, $this->replacePlaceholders($command, $steam, $user, $additional));
            } catch (Exception $e) {
                if (is_debug()) {
                    throw $e;
                }

                logs()->error($e);
            }
        }
    }

    private function replacePlaceholders(string $command, ?string $steam, User $user, array $additional): string
    {
        $steamDetails = $this->getSteamDetails($steam);

        if (!empty($this->time) && $this->time > 0) {
            $totalSeconds = (int) $this->time;

            $days = intdiv($totalSeconds, 86400);
            $hours = intdiv($totalSeconds % 86400, 3600);
            $minutes = intdiv($totalSeconds % 3600, 60);
            $seconds = $totalSeconds % 60;

            $unix = time() + $totalSeconds;
        } else {
            $days = $hours = $minutes = $seconds = $unix = 0;
        }

        $sanitize = static fn($v): string => str_replace([';', "\n", "\r", '"', "'"], '', (string) $v);

        $command = str_replace(
            [
                '{steam32}',
                '{steam64}',
                '{accountId}',
                '{login}',
                '{name}',
                '{email}',
                '{uri}',
                '{days}',
                '{hours}',
                '{minutes}',
                '{seconds}',
                '{unix}',
                '{nickname}',
            ],
            [
                $steamDetails['steam32'],
                $steamDetails['steam64'],
                $steamDetails['accountId'],
                $sanitize($user->login),
                $sanitize($user->name),
                $sanitize($user->email),
                $sanitize($user->uri),
                $days,
                $hours,
                $minutes,
                $seconds,
                $unix,
                $sanitize($additional['mc_nick'] ?? ''),
            ],
            $command,
        );

        foreach ($additional as $key => $value) {
            if (str_starts_with($key, 'cf_')) {
                $command = str_replace('{' . $key . '}', $sanitize((string) $value), $command);
            }
        }

        return $command;
    }

    private function getSteamDetails(?string $steam): array
    {
        if ($steam) {
            $steamClass = steam()->steamid($steam);

            return [
                'steam32' => $steamClass->RenderSteam2(),
                'steam64' => $steamClass->ConvertToUInt64(),
                'accountId' => $steamClass->GetAccountID(),
            ];
        }

        return ['steam32' => '', 'steam64' => '', 'accountId' => ''];
    }
}
