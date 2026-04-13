<?php

namespace Flute\Modules\GiveCore\Support;

use Flute\Modules\GiveCore\Contracts\DriverInterface;
use Flute\Modules\GiveCore\Exceptions\NeedToConfirmException;
use Flute\Modules\GiveCore\Exceptions\NeedToSelectException;

/**
 * Base class for GiveCore delivery drivers.
 * Subclasses MUST implement deliver() and alias().
 */
abstract class AbstractDriver implements DriverInterface
{
    use DriverHelpers;

    /**
     * DB connection key (override in subclass).
     */
    protected const MOD_KEY = null;

    public function confirm(string $message, ?string $id = null, array $extra = [])
    {
        $id ??= sha1($message);

        $validate = filter_var(request()->input($id, false), FILTER_VALIDATE_BOOLEAN);

        if (!$validate) {
            throw new NeedToConfirmException([
                'confirm' => array_merge([
                    'message' => $message,
                    'id' => $id,
                ], $extra),
            ]);
        }
    }

    public function select(array $values, string $message)
    {
        $id = sha1($message);

        $validate = request()->input($id, false);

        $found = false;

        foreach ($values as $key => $value) {
            if ($value['value'] === $validate) {
                $found = true;

                continue;
            }
        }

        if (!$validate || !$found) {
            throw new NeedToSelectException([
                'select' => [
                    'message' => $message,
                    'id' => $id,
                    'values' => $values,
                ],
            ]);
        }

        return $validate;
    }

    /**
     * Alias for getDbPrefix from trait (backward compatibility with old drivers).
     */
    public function getPrefix(string $dbname, string $defaultPrefix = ''): string
    {
        return $this->getDbPrefix($dbname, $defaultPrefix);
    }

    // ── Metadata defaults ──────────────────────────────────────────

    public function name(): string
    {
        return $this->alias();
    }

    public function description(): string
    {
        return '';
    }

    public function icon(): string
    {
        return 'ph.bold.plug-bold';
    }

    public function category(): string
    {
        return 'other';
    }

    public function deliverFields(): array
    {
        return [];
    }

    public function dbConnectionKey(): ?string
    {
        return static::MOD_KEY;
    }

    public function isAvailable(): bool
    {
        $key = $this->dbConnectionKey();
        if ($key === null) {
            return true;
        }

        return !empty($this->getServersWithConnection($key));
    }

    public function unavailableReason(): ?string
    {
        if ($this->isAvailable()) {
            return null;
        }

        return __('givecore.no_servers', ['key' => $this->dbConnectionKey() ?? '']);
    }

    public function sourceUrl(): ?string
    {
        return null;
    }

    public function supportedGames(): array
    {
        return [];
    }

    /**
     * Required social network alias for delivery (e.g. 'Steam', 'Minecraft'),
     * or null if delivery does not require any social linkage.
     *
     * Concrete drivers that resolve a Steam/Minecraft id during deliver()
     * should override this so callers can check beforehand.
     */
    public function requiredSocial(array $config = []): ?string
    {
        return null;
    }
}
