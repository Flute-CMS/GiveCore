<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\GiveDriverException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
use xPaw\SourceQuery\SourceQuery;

/**
 * Params:
 * 
 * command - command to execute
 * 
 * time - time in seconds
 */

class RconDriver extends AbstractDriver
{
    protected $time;

    public function deliver(User $user, Server $server, array $additional = [], ?int $timeId = null, bool $ignoreErrors = false): bool
    {
        // $this->validateServerAndCommand($server, $additional);

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

        $steam = $this->getSteamId($user, $additional['command']);

        if ($timeId !== null) {
            $this->time = $timeId;
        }

        $query = new SourceQuery();

        try {
            $this->connectToServer($query, $server);
            $this->executeCommands($query, $commands, $steam, $user);
            return true;
        } catch (\Exception $e) {
            throw new GiveDriverException($e->getMessage());
        } finally {
            $query->Disconnect();
        }
    }

    public function alias(): string
    {
        return 'rcon';
    }

    private function validateServerAndCommand(Server $server, array $additional): void
    {
        if (!$server->rcon) {
            throw new BadConfigurationException("Server $server->name rcon empty");
        }

        if (!isset($additional['command'])) {
            throw new BadConfigurationException('command');
        }
    }

    private function getSteamId(User $user, string $command): ?string
    {
        if (preg_match('/{steam32}|{steam64}|{accountId}/i', $command)) {
            $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');

            if (!$steam) {
                throw new UserSocialException("Steam");
            }

            return $steam->value;
        }

        return null;
    }

    private function connectToServer(SourceQuery $query, Server $server): void
    {
        $query->Connect(
            $server->ip,
            $server->port,
            3,
            ($server->mod == 10) ? SourceQuery::GOLDSOURCE : SourceQuery::SOURCE
        );
        $query->SetRconPassword($server->rcon);
    }

    private function executeCommands(SourceQuery $query, array $commands, ?string $steam, User $user): void
    {
        foreach ($commands as $command) {
            try {
                $this->sendCommand($query, $this->replacePlaceholders($command, $steam, $user));
            } catch (\Exception $e) {
                if (is_debug()) {
                    throw $e;
                }

                logs()->error($e);
            }
        }
    }

    private function replacePlaceholders(string $command, ?string $steam, User $user): string
    {
        $steamDetails = $this->getSteamDetails($steam);

        if (!empty($this->time) && $this->time > 0) {
            $totalSeconds = (int) $this->time;

            $days    = intdiv($totalSeconds, 86400);
            $hours   = intdiv($totalSeconds, 3600);
            $minutes = intdiv($totalSeconds, 60);
            $seconds = $totalSeconds;

            $unix = time() + $totalSeconds;
        } else {
            $days = $hours = $minutes = $seconds = $unix = 0;
        }

        return str_replace(
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
                '{unix}'
            ],
            [
                $steamDetails['steam32'],
                $steamDetails['steam64'],
                $steamDetails['accountId'],
                $user->login,
                $user->name,
                $user->email,
                $user->uri,
                $days,
                $hours,
                $minutes,
                $seconds,
                $unix
            ],
            $command
        );
    }

    private function getSteamDetails(?string $steam): array
    {
        if ($steam) {
            $steamClass = steam()->steamid($steam);
            return [
                'steam32' => $steamClass->RenderSteam2(),
                'steam64' => $steamClass->ConvertToUInt64(),
                'accountId' => $steamClass->GetAccountID()
            ];
        }

        return ['steam32' => '', 'steam64' => '', 'accountId' => ''];
    }

    private function sendCommand(SourceQuery $query, string $command): void
    {
        $query->Rcon($command);
    }
}
