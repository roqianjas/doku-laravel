<?php

namespace DokuLaravel;

use DokuLaravel\Contracts\CheckoutService;
use DokuLaravel\Contracts\StatusService;
use DokuLaravel\Contracts\WebhookVerifier;
use DokuLaravel\Services\FakeCheckoutService;
use DokuLaravel\Services\FakeStatusService;
use DokuLaravel\Services\HttpCheckoutService;
use DokuLaravel\Services\HttpStatusService;
use DokuLaravel\Services\HttpWebhookVerifier;
use DokuLaravel\Support\DokuConfig;
use DokuLaravel\Support\SignatureGenerator;
use DokuLaravel\Support\StatusNormalizer;
use Illuminate\Support\ServiceProvider;

class DokuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/doku.php', 'doku');

        $this->app->singleton(DokuConfig::class);
        $this->app->singleton(SignatureGenerator::class);
        $this->app->singleton(StatusNormalizer::class);

        $this->app->singleton(CheckoutService::class, function ($app) {
            return $app->make(DokuConfig::class)->driver() === 'fake'
                ? $app->make(FakeCheckoutService::class)
                : $app->make(HttpCheckoutService::class);
        });

        $this->app->singleton(StatusService::class, function ($app) {
            return $app->make(DokuConfig::class)->driver() === 'fake'
                ? $app->make(FakeStatusService::class)
                : $app->make(HttpStatusService::class);
        });

        $this->app->singleton(WebhookVerifier::class, HttpWebhookVerifier::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/doku.php' => config_path('doku.php'),
        ], 'doku-config');
    }
}
