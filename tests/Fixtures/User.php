<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class User extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = [
      'name',
      'email',
      'password',
    ];

    protected $hidden = [
      'password',
    ];
}
