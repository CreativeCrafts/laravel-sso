<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('controllers stay thin and do not call HTTP clients directly')
    ->expect('CreativeCrafts\LaravelSso\Http\Controllers')
    ->not->toUse('Illuminate\Http\Client\Factory');

arch('drivers depend on contracts rather than repositories')
    ->expect('CreativeCrafts\LaravelSso\Drivers')
    ->not->toUse('CreativeCrafts\LaravelSso\Repositories');
