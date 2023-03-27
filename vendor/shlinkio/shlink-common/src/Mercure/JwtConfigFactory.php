<?php

declare(strict_types=1);

namespace Shlinkio\Shlink\Common\Mercure;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Psr\Container\ContainerInterface;
use Shlinkio\Shlink\Common\Exception\MercureException;

use function is_string;

class JwtConfigFactory
{
    public function __invoke(ContainerInterface $container): Configuration
    {
        $jwtSecret = $container->get('config')['mercure']['jwt_secret'] ?? null;
        if (empty($jwtSecret) || ! is_string($jwtSecret)) {
            throw MercureException::missingJwtSecret();
        }

        return Configuration::forSymmetricSigner(new Sha256(), Key\InMemory::plainText($jwtSecret));
    }
}
