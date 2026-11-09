<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Laravel;

use Mfonte\Base62x\Base62x;
use Illuminate\Support\ServiceProvider;
use Mfonte\Base62x\Laravel\Casts\Base62xCast;
use Mfonte\Base62x\Laravel\Rules\Base62xRule;

/**
 * Laravel integration: registers the "base62x" factory, the configuration and the "base62x" validation rule.
 * Auto-discovered by Laravel through composer.json "extra.laravel".
 */
class Base62xServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/base62x.php', 'base62x');

        $this->app->singleton(Base62xFactory::class, static function ($app): Base62xFactory {
            $config = $app->make('config');

            $key = $config->get('base62x.key');
            $cipher = $config->get('base62x.cipher');
            $compression = $config->get('base62x.compression');

            return new Base62xFactory(
                \is_string($key) ? $key : null,
                \is_string($cipher) && $cipher !== '' ? $cipher : Base62x::CIPHER_XCHACHA20_POLY1305,
                \is_string($compression) ? $compression : null
            );
        });
        $this->app->alias(Base62xFactory::class, 'base62x');
    }

    public function boot(): void
    {
        $app = $this->app;
        Base62xCast::$factoryResolver = static function () use ($app): Base62xFactory {
            return $app->make(Base62xFactory::class);
        };

        if (method_exists($app, 'runningInConsole') && $app->runningInConsole()) {
            $this->publishes([__DIR__.'/../../config/base62x.php' => $this->configPath()], 'base62x-config');
        }

        $registerRule = static function ($validator): void {
            $validator->extend('base62x', static function ($attribute, $value): bool {
                return (new Base62xRule())->passes($attribute, $value);
            }, (new Base62xRule())->message());
        };
        if ($app->resolved('validator')) {
            $registerRule($app->make('validator'));
        } else {
            $app->afterResolving('validator', $registerRule);
        }
    }

    private function configPath(): string
    {
        return method_exists($this->app, 'configPath') ? $this->app->configPath('base62x.php') : 'config/base62x.php';
    }
}
