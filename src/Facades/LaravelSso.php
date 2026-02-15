<?php

namespace CreativeCrafts\LaravelSso\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \CreativeCrafts\LaravelSso\LaravelSso
 */
class LaravelSso extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CreativeCrafts\LaravelSso\LaravelSso::class;
    }
}
