<?php

declare(strict_types=1);

namespace Shlinkio\Shlink\Rest\ApiKey\Model;

use Cake\Chronos\Chronos;

final class ApiKeyMeta
{
    /**
     * @param RoleDefinition[] $roleDefinitions
     */
    private function __construct(
        public readonly ?string $name,
        public readonly ?Chronos $expirationDate,
        public readonly array $roleDefinitions,
    ) {
    }

    public static function withName(string $name): self
    {
        return new self($name, null, []);
    }

    public static function withExpirationDate(Chronos $expirationDate): self
    {
        return new self(null, $expirationDate, []);
    }

    public static function withNameAndExpirationDate(string $name, Chronos $expirationDate): self
    {
        return new self($name, $expirationDate, []);
    }

    public static function withRoles(RoleDefinition ...$roleDefinitions): self
    {
        return new self(null, null, $roleDefinitions);
    }
}
