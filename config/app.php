<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    // Nome da aplicação
    'name' => env('APP_NAME', 'Laravel'),

    // Ambiente (local, production etc.)
    'env' => env('APP_ENV', 'production'),

    // Depuração (erros detalhados quando true)
    'debug' => (bool) env('APP_DEBUG', false),

    // URL base
    'url' => env('APP_URL', 'http://localhost'),

    // Fuso horário e idioma
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    // Criptografia
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'previous_keys' => [
        ...array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),
    ],

    // Manutenção
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store'  => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    // Providers: carrega os padrão do framework e adiciona os seus
    'providers' => ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // Se existir no seu projeto, pode adicionar também:
        // App\Providers\RouteServiceProvider::class,
    ])->toArray(),

    // Aliases (facades)
    'aliases' => Facade::defaultAliases()->merge([
        // Coloque aliases customizados aqui, se precisar.
    ])->toArray(),
];
