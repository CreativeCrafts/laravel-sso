<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Core\UserLocator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Model;

final class DefaultUserLocator implements UserLocator
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {
    }

    public function findByEmail(string $guard, string $email): ?Authenticatable
    {
        $modelClass = $this->resolveModelClass($guard);

        if ($modelClass === null) {
            return null;
        }

        /** @var string $emailColumn */
        $emailColumn = $this->config->get(key: 'sso.provisioning.email_column', default: 'email');

        /** @var Model|null $user */
        $user = $modelClass::query()
          ->where($emailColumn, $email)
          ->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * @return class-string<Model>|null
     */
    private function resolveModelClass(string $guard): ?string
    {
        /** @var string|null $providerKey */
        $providerKey = $this->config->get(key: "auth.guards.{$guard}.provider");

        if (!is_string($providerKey) || $providerKey === '') {
            return null;
        }

        /** @var array<string, mixed> $providerConfig */
        $providerConfig = (array)$this->config->get(key: "auth.providers.{$providerKey}", default: []);

        $model = $providerConfig['model'] ?? null;

        if (!is_string($model) || $model === '') {
            return null;
        }

        if (!is_subclass_of($model, Model::class)) {
            return null;
        }

        return $model;
    }
}
