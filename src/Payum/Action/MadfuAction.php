<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Payum\Action;

use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;
use Psr\Http\Message\ResponseInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ahmedkhd\SyliusPaymobPlugin\Services\AbstractService;
use Payum\Core\Exception\RequestNotSupportedException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Client;
use Ahmedkhd\SyliusPaymobPlugin\Payum\Action\CaptureAction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use GuzzleHttp\Exception\ClientException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MadfuAction implements Action
{
    /** @var Client */
    private $client;

    /** @var SyliusApi */
    private $api;

    /** @var array */
    private $headers;

    /** @var ContainerInterface */
    protected $container;



    protected $paymentRepository;
    protected $entityManager;
    protected $logger;

    protected $channelContext;
    protected $urlGenerator;

    public function __construct(ContainerInterface $container, EntityRepository $paymentRepository, UrlGeneratorInterface $urlGenerator)
    {
        $this->paymentRepository = $paymentRepository;
        $this->headers['headers'] = [
            'Accept' => '*/*',
            'Content-Type' => 'application/json',
        ];
        $this->container = $container;
        $this->entityManager = $this->container->get('doctrine')->getManager();
        $this->logger = $this->container->get('monolog.logger.payment');
        $this->channelContext = $channelContext;
        $this->urlGenerator = $urlGenerator;
    }

    public function initializeToken()
    {
        $uuid = Uuid::uuid4()->toString();
        try {
            $response = $this->client->request('POST', $this->api->getDomain().'/token/init', [
                'body' => json_encode([
                    'uuid' => $uuid,
                    'systemInfo' => 'web',
                ]),
                'headers' => [
                    'APIKey' => $this->api->getApiKey(),
                    'AppCode' => $this->api->getAppCode(),
                    'Authorization' => $this->api->getAuthrization(),
                    'PlatformTypeId' => '5',
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                ],
            ]);
        }
        catch (ClientException $exception) {
            $body = $exception->getResponse()->getBody()->getContents();
            $data = json_decode($body, true);
            $this->logger->error($data['message']);
        }

        return json_decode($response->getBody()->getContents(), true)['token'];
    }

    public function login($token)
    {
        try {
            $response = $this->client->request('POST', $this->api->getDomain().'/sign-in', [
                'body' => json_encode([
                    'userName' => $this->api->getUsername(),
                    'password' => $this->api->getPassword(),
                ]),
                'headers' => [
                    'Token' => $token,
                    'APIKey' => $this->api->getApiKey(),
                    'AppCode' => $this->api->getAppCode(),
                    'Authorization' => $this->api->getAuthrization(),
                    'PlatformTypeId' => '5',
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                ],
            ]);
        }
        catch (ClientException $exception) {
            $body = $exception->getResponse()->getBody()->getContents();
            $data = json_decode($body, true);
            $this->logger->error($data['message']);
        }
        $response = json_decode($response->getBody()->getContents(), true);
        if ($response['status'] != 200) {
            $this->logger->error($data['message']);
        }
        return $response['token'];
    }

    public function prepareOrderItems($order)
    {
       return array_map(fn($item) => [
           'SKU' => $item->getProduct()->getId(),
           'productName' => $item->getProduct()->getName(),
           'count' => $item->getQuantity(),
           'totalAmount' => number_format($item->getTotal() / 100, 2),
        ], $order->getItems()->toArray());
    }

    public function getOnlineCheckout($token, $order)
    {
        try {
            $uuid = Uuid::uuid4()->toString() ;
            $response = $this->client->request('POST', $this->api->getDomain().'/Checkout/CreateOrder', [
            'json' => [
                'Order' => [
                    'Taxes' => 0.0,
                    'ActualValue' => number_format($order->getTotal() / 100, 2),
                    'Amount' => number_format($order->getTotal() / 100, 2),
                    'MerchantReference' => $order->getId(),
                ],
                'GuestOrderData' => [
                    'Lang' => 'Ar',
                    'CustomerName' => $order->getCustomer()->getFirstName() ?? $order->getBillingAddress()->getFirstName() ?? "NA",
                    'CustomerMobile' =>  substr($order->getBillingAddress()->getPhoneNumber(),1,9),
                    'ShippingAddress' => $order->getBillingAddress()->getStreet() ?? "NA",
                ],
                'OrderDetails' => $this->prepareOrderItems($order),
                'MerchantUrls' => [
                    'Success' => $this->urlGenerator->generate('sylius_shop_order_thank_you', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    'Failure' => $this->urlGenerator->generate('payment_failure', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    'Cancel' => $this->urlGenerator->generate('payment_failure', [], UrlGeneratorInterface::ABSOLUTE_URL),
                ],
            ],
            'headers' => [
                'Token' => $token,
                'APIKey' => $this->api->getApiKey(),
                'AppCode' => $this->api->getAppCode(),
                'Authorization' => $this->api->getAuthrization(),
                'PlatformTypeId' => '5',
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
        ]);}
        catch (ClientException $exception) {
                $exceptionResponse = $exception->getResponse();
                $exceptionMessage = json_decode($exceptionResponse->getBody()->getContents(), true);
                $this->logger->error($data['errors']);
        }

        $response = json_decode($response->getBody()->getContents(), true);
        return $response;
    }
    public function execute($request, $client, $api)
    {
        try {
            $this->client = $client;
            $this->api = $api;
            $payment = $request->getModel();
            $order = $payment->getOrder();

            $initialToken = $this->initializeToken($api);
            $signinToken = $this->login($initialToken);
            $onlineCheckout= $this->getOnlineCheckout($signinToken, $order);

            $payment->setDetails(['status' => PaymentInterface::STATE_PROCESSING]);
            $payment->setPaymentGatewayOrderId($onlineCheckout['orderId']);
            $this->entityManager->flush();

            $this->api->doPayment($onlineCheckout['checkoutLink']);
        } catch (RequestException $exception) {
            $this->logger->error($exception->getMessage());
            $payment->setDetails(['status' => "failed", "message" => $exception->getMessage()]);
            $payment->setState(PaymentInterface::STATE_NEW);
            return;
        }

    }
}
