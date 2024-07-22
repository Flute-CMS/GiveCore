<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Contracts\DriverInterface;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\GiveDriverException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use xPaw\SourceQuery\SourceQuery;

class RconDriver implements DriverInterface
{
    public function deliver(User $user, Server $server, array $additional = [], ?int $timeId = null): bool
    {
        $this->validateServerAndCommand($server, $additional);

        $commands = explode(';', $additional['command']);
        $steam = $this->getSteamId($user, $additional['command']);

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
        if (preg_match('/{{steam32}}|{{steam64}}|{{accountId}}/i', $command)) {
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
            $command = trim($command);
            if (empty($command)) {
                continue;
            }
            $this->sendCommand($query, $this->replacePlaceholders($command, $steam, $user));
        }
    }

    private function replacePlaceholders(string $command, ?string $steam, User $user): string
    {
        $steamDetails = $this->getSteamDetails($steam);

        return str_replace(
            ['{{steam32}}', '{{steam64}}', '{{accountId}}', '{{login}}', '{{name}}', '{{email}}', '{{uri}}'],
            [$steamDetails['steam32'], $steamDetails['steam64'], $steamDetails['accountId'], $user->login, $user->name, $user->email, $user->uri],
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
