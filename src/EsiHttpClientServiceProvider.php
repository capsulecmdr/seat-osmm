<?php

namespace CapsuleCmdr\SeatOsmm;

use Illuminate\Support\ServiceProvider;
use Psr\Http\Client\ClientInterface as Psr18Client;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Http\Adapter\Guzzle7\Client as Guzzle7Adapter;
use GuzzleHttp\Client as GuzzleHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

class EsiHttpClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PSR-18
        $this->app->singleton(Psr18Client::class, function () {
            // Wrap a standard Guzzle client with the HTTPlug adapter
            return new Guzzle7Adapter(new GuzzleHttpClient());
        });

        // PSR-17 factories
        $this->app->singleton(RequestFactoryInterface::class, fn () => new Psr17Factory());
        $this->app->singleton(StreamFactoryInterface::class,  fn () => new Psr17Factory());
        $this->app->singleton(UriFactoryInterface::class,     fn () => new Psr17Factory());
    }
}
