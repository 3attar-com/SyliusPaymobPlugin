<?php


namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as GuzzleRequest;

class TamaraService
{
    private $client;
    private $tamaraToken;
    private $baseUrl;

    const SANDBOX_BASE_URL = 'https://api-sandbox.tamara.co';
    const LIVE_BASE_URL = 'https://api.tamara.co';

    public function __construct(Client $client, string $tamaraToken, string $tamaraEnv)
    {
        $this->client = $client;
        $this->tamaraToken = $tamaraToken;
        $this->baseUrl = $tamaraEnv === 'sandbox' ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;
    }

    public function getOrderByReferenceId(string $referenceId): ?array
    {
        try {
            $request = new GuzzleRequest(
                'GET',
                $this->baseUrl . '/merchants/orders/reference-id/' . $referenceId,
                [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->tamaraToken,
                ]
            );

            $response = $this->client->send($request);
            $body = $response->getBody()->getContents();

            return json_decode($body, true);
        } catch (\Exception $e) {
            return null;
        }
    }
}
