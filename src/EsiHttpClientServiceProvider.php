<?php

namespace CapsuleCmdr\SeatOsmm;

use Illuminate\Support\ServiceProvider;

// PSR-18
use Psr\Http\Client\ClientInterface as Psr18Client;

// PSR-17
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

// HTTPlug v1 (legacy) – optional but helpful for older deps
use Http\Client\HttpClient as HttplugClient;
use Http\Message\RequestFactory as HttplugRequestFactory;
use Http\Message\StreamFactory as HttplugStreamFactory;
use Http\Message\UriFactory as HttplugUriFactory;

// Concretes
use Http\Adapter\Guzzle7\Client as Guzzle7Adapter;
use GuzzleHttp\Client as GuzzleHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

class EsiHttpClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PSR-18 client
        if (! $this->app->bound(Psr18Client::class)) {
            $this->app->singleton(Psr18Client::class, function () {
                // Wrap Guzzle with HTTPlug adapter to provide PSR-18
                return new Guzzle7Adapter(new GuzzleHttpClient());
            });
        }

        // PSR-17 factories
        if (! $this->app->bound(RequestFactoryInterface::class)) {
            $this->app->singleton(RequestFactoryInterface::class, fn () => new Psr17Factory());
        }
        if (! $this->app->bound(ResponseFactoryInterface::class)) {
            $this->app->singleton(ResponseFactoryInterface::class, fn () => new Psr17Factory());
        }
        if (! $this->app->bound(StreamFactoryInterface::class)) {
            $this->app->singleton(StreamFactoryInterface::class, fn () => new Psr17Factory());
        }
        if (! $this->app->bound(UriFactoryInterface::class)) {
            $this->app->singleton(UriFactoryInterface::class, fn () => new Psr17Factory());
        }

        // OPTIONAL: HTTPlug v1 bindings (some libs still request these)
        if (! $this->app->bound(HttplugClient::class)) {
            $this->app->alias(Psr18Client::class, HttplugClient::class);
        }
        if (! $this->app->bound(HttplugRequestFactory::class)) {
            $this->app->alias(RequestFactoryInterface::class, HttplugRequestFactory::class);
        }
        if (! $this->app->bound(HttplugStreamFactory::class)) {
            $this->app->alias(StreamFactoryInterface::class, HttplugStreamFactory::class);
        }
        if (! $this->app->bound(HttplugUriFactory::class)) {
            $this->app->alias(UriFactoryInterface::class, HttplugUriFactory::class);
        }
    }
}
