<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Psr\Log\LoggerInterface;

class TamaraService
{
    private $client;
    private $tamaraToken;
    private $baseUrl;
    private $logger;

    const SANDBOX_BASE_URL = 'https://api-sandbox.tamara.co';
    const LIVE_BASE_URL = 'https://api.tamara.co';

    public function __construct(Client $client, LoggerInterface $logger, string $tamaraToken, string $tamaraEnv)
    {
        $this->client = $client;
        $this->logger = $logger;
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

            // Check if the response status is 200 (OK)
            if ($response->getStatusCode() === 200) {
                $body = $response->getBody()->getContents();
                return json_decode($body, true);
            }

            // Log response status and body if not 200
            $this->logger->error("Tamara API Error", [
                'status_code' => $response->getStatusCode(),
                'body' => $response->getBody()->getContents()
            ]);

            return null;
        } catch (\Exception $e) {
            // Log the exception
            $this->logger->error("Error fetching Tamara order", [
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }
}
