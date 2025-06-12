<?php

// Essential debugging prevention
arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

// Core architectural rules
arch('models should extend Eloquent Model')
    ->expect('Ahs12\Setanjo\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('exceptions should extend base Exception')
    ->expect('Ahs12\Setanjo\Exceptions')
    ->toExtend('Exception');

arch('contracts should be interfaces')
    ->expect('Ahs12\Setanjo\Contracts')
    ->toBeInterfaces();

arch('service provider should extend Laravel service provider')
    ->expect('Ahs12\Setanjo\SetanjoServiceProvider')
    ->toExtend('Spatie\LaravelPackageTools\PackageServiceProvider');

// Package isolation (essential for packages)
arch('package should not use deprecated Laravel features')
    ->expect('Ahs12\Setanjo')
    ->not->toUse([
        'Illuminate\Support\Facades\Schema',
        'Illuminate\Http\Request::get',
        'Illuminate\Database\Eloquent\Builder::paginate',
    ]);

arch('package should not use framework internals')
    ->expect('Ahs12\Setanjo')
    ->not->toUse([
        'Illuminate\Foundation\Application',
        'Illuminate\Container\Container',
    ]);

arch('package should not use Laravel testing helpers in production code')
    ->expect('Ahs12\Setanjo')
    ->not->toUse([
        'Illuminate\Foundation\Testing\TestCase',
        'PHPUnit\Framework\TestCase',
        'Orchestra\Testbench\TestCase',
    ]);

// Security and performance
arch('config files should not contain sensitive data')
    ->expect('Ahs12\Setanjo\Config')
    ->not->toUse(['password', 'secret', 'key', 'token']);

arch('package should not leak memory')
    ->expect('Ahs12\Setanjo')
    ->not->toUse(['ini_set', 'memory_limit', 'set_time_limit']);

// Value objects immutability (if you have value objects)
arch('value objects should be immutable')
    ->expect('Ahs12\Setanjo\ValueObjects')
    ->classes()
    ->toBeReadonly();
