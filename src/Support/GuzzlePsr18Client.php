<?php

namespace CapsuleCmdr\SeatOsmm\Support;

use Psr\Http\Client\ClientInterface as Psr18Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client as GuzzleClient;

class GuzzlePsr18Client implements Psr18Client
{
    private $client;

    public function __construct(GuzzleClient $client)
    {
        $this->client = $client;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        // Guzzle's request/response already implement PSR-7
        return $this->client->send($request);
    }
}
