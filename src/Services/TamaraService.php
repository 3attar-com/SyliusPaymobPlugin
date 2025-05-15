<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Psr\Log\LoggerInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Bundle\ShopBundle\EmailManager\OrderEmailManagerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;

class TamaraService extends AbstractService implements PaymobServiceInterface
{

    private $client;
    private $tamaraToken;
    private $baseUrl;

    const SANDBOX_BASE_URL = 'https://api-sandbox.tamara.co';
    const LIVE_BASE_URL = 'https://api.tamara.co';

    public function __construct(
        ContainerInterface $container,
        SymfonyEventDispatcherInterface $eventDispatcher,
        OrderEmailManagerInterface $orderEmailManager,
        PaymentRepositoryInterface $paymentRepository,
        CustomerRepositoryInterface $customerRepository,
        ParameterBagInterface $parameterBag,
        Client $client,
        $tamaraToken,
        $tamaraEnv
    ) {
        parent::__construct($container, $eventDispatcher, $orderEmailManager, $paymentRepository, $customerRepository, $parameterBag);
        $this->client = $client;
        $this->tamaraToken = $tamaraToken;
        $this->baseUrl = $tamaraEnv !== 'sandbox' ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;
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
    }    public function handelWebhook($request)
    {
        $this->logger->info('Tamara webhook', ['request' => $request->getContent()]);

        if ($request->get('event_type') === 'order_captured') {
            $orderId = $request->get('order_number');

            $method = $this->container->get('doctrine.orm.entity_manager')->getRepository(PaymentMethod::class)->findOneBy(['code' => 'tamara']);

            $payment = $this->paymentRepository->findOneBy(['method' => $method, 'order' => $orderId]);
            $this->competeOrder($payment);
        }
        return true;
    }

}
