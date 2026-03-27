<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Core\DriverRegistry;
use CreativeCrafts\LaravelSso\Contracts\Core\SsoDriver;
use CreativeCrafts\LaravelSso\Exceptions\UnsupportedSsoProtocol;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;

final readonly class ConfigDriverRegistry implements DriverRegistry
{
    public function __construct(
        private Container $container,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public function get(string $protocol): SsoDriver
    {
        $map = config('sso.drivers', []);

        if (!is_array($map)) {
            $map = [];
        }

        $driverClass = $map[$protocol] ?? null;

        if (!is_string($driverClass) || $driverClass === '') {
            throw UnsupportedSsoProtocol::for($protocol);
        }

        $driver = $this->container->make($driverClass);

        if (!$driver instanceof SsoDriver) {
            throw UnsupportedSsoProtocol::for($protocol);
        }

        return $driver;
    }
}
