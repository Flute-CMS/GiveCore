<?php

namespace Flute\Modules\GiveCore\Exceptions;

use Exception;

class BadConfigurationException extends Exception
{
    public function __construct(string $key, ?string $serverName = null, int $code = 0, ?\Throwable $previous = null)
    {
        $message = $serverName !== null
            ? __('givecore.errors.no_db_connection', ['mod' => $key, 'server' => $serverName])
            : $key;

        parent::__construct($message, $code, $previous);
    }

    public static function noGroup(?string $serverName = null): self
    {
        return new self(__('givecore.errors.no_group', ['server' => $serverName ?? '']), null);
    }

    public static function noRcon(string $serverName): self
    {
        return new self(__('givecore.errors.no_rcon', ['server' => $serverName]), null);
    }

    public static function noCommand(): self
    {
        return new self(__('givecore.errors.no_command'), null);
    }

    public static function noDbConnection(string $mod, string $serverName): self
    {
        return new self($mod, $serverName);
    }

    public static function missingParam(string $param, string $serverName = ''): self
    {
        return new self(__('givecore.errors.missing_param', ['param' => $param, 'server' => $serverName]), null);
    }

    public static function noSql(?string $serverName = null): self
    {
        return new self(__('givecore.errors.no_sql'), null);
    }
}
