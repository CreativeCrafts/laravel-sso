<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Core\UserProvisioner;
use CreativeCrafts\LaravelSso\Exceptions\UserEmailAlreadyExists;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use RuntimeException;

final class DefaultUserProvisioner implements UserProvisioner
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function provision(string $guard, string $email, ?string $displayName, array $claims): Authenticatable
    {
        $modelClass = $this->resolveModelClass($guard);

        if ($modelClass === null) {
            throw new RuntimeException("Cannot resolve authenticatable model for guard [{$guard}].");
        }

        /** @var string $emailColumn */
        $emailColumn = $this->config->get('sso.provisioning.email_column', 'email');

        /** @var string $nameColumn */
        $nameColumn = $this->config->get('sso.provisioning.name_column', 'name');

        /** @var Model $user */
        $user = new $modelClass();

        $user->setAttribute($emailColumn, $email);

        if (is_string($displayName) && $displayName !== '') {
            $user->setAttribute($nameColumn, $displayName);
        }

        try {
            $user->save();
        } catch (QueryException $exception) {
            if (!$this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            throw UserEmailAlreadyExists::forEmail($email);
        }

        if (!$user instanceof Authenticatable) {
            throw new RuntimeException('Provisioned model is not authenticatable.');
        }

        return $user;
    }

    /**
     * @return class-string<Model>|null
     */
    private function resolveModelClass(string $guard): ?string
    {
        /** @var string|null $providerKey */
        $providerKey = $this->config->get("auth.guards.{$guard}.provider");

        if (!is_string($providerKey) || $providerKey === '') {
            return null;
        }

        /** @var array<string, mixed> $providerConfig */
        $providerConfig = (array)$this->config->get("auth.providers.{$providerKey}", []);

        $model = $providerConfig['model'] ?? null;

        if (!is_string($model) || $model === '') {
            return null;
        }

        if (!is_subclass_of($model, Model::class)) {
            return null;
        }

        return $model;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();

        if ($code === '23000') {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique constraint failed')
            || str_contains($message, 'duplicate key value')
            || str_contains($message, 'unique violation');
    }
}
