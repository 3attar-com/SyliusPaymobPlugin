<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Payum\Action;

use GuzzleHttp\Client;
use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ahmedkhd\SyliusPaymobPlugin\Services\AbstractService;
use Payum\Core\Exception\RequestNotSupportedException;
use Ahmedkhd\SyliusPaymobPlugin\Payum\Action\CaptureAction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class PaymobAction implements Action
{
    /** @var Client */
    private $client;
    /** @var SyliusApi */
    private $api;
    /** @var array */
    private $headers;
    protected $paymentRepository;
    protected $entityManager;
    protected $container;
    protected $logger;

    public function __construct(ContainerInterface $container, EntityRepository $paymentRepository)
    {
        $this->paymentRepository = $paymentRepository;
        $this->headers['headers'] = [
            'Accept' => '*/*',
            'Content-Type' => 'application/json',
        ];
        $this->container = $container;
        $this->entityManager = $this->container->get('doctrine')->getManager();
        $this->logger = $this->container->get('monolog.logger.payment');
    }

    public function execute($request, $client, $api)
    {
        $this->client = $client;
        $this->api = $api;
        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getModel();
        $order = $payment->getOrder();

        try {
            $response = $this->client->request('POST', "{$this->api->getDomain()}/intention/",
                $this->getBodyWithHeader([
                    'body' => \GuzzleHttp\json_encode([
                        'delivery_needed' => 'false',
                        'payment_methods' => [
                            intval($this->api->getIntegrationId())
                        ],
                        'amount_cents' => intval($payment->getOrder()->getTotal()),
                        'amount' => intval($payment->getOrder()->getTotal()),
                        'currency' => "SAR",
                        'merchant_id' => $this->api->getMerchantId(),
                        "special_reference" => $order->getId() . '1',
                        "notification_url" => $this->api->getNotificationUrl(),
                        "redirection_url" => $this->api->getRedirectionUrl(),
                        "billing_data" => [
                            "apartment" => "NA",
                            'email' => $payment->getOrder()->getCustomer()->getEmail() ?? "NA",
                            'phone_number' => $payment->getOrder()->getCustomer()->getPhoneNumber() ?? "NA",
                            "floor" => "NA",
                            'first_name' => $payment->getOrder()->getCustomer()->getFirstName() ?? "NA",
                            'last_name' => $payment->getOrder()->getCustomer()->getLastName() ?? "NA",
                            $payment->getOrder()->getBillingAddress()->getStreet() ?? "NA",
                            "building" => "NA",
                            'postal_code' => $payment->getOrder()->getBillingAddress()->getPostcode() ?? "NA",
                            'city' => $payment->getOrder()->getBillingAddress()->getCity() ?? "NA",
                            'country' => $payment->getOrder()->getBillingAddress()->getCountryCode() ?? "NA",
                            'state' => $payment->getOrder()->getBillingAddress()->getProvinceName() ?? "NA",
                        ],
                    ]),
                ])
            );
            $response = json_decode($response->getBody()->getContents(), true);
            $clientSecret = $response['client_secret'];
            $orderId = $response['intention_order_id'];

            $payment->setDetails(['status' => PaymentInterface::STATE_PROCESSING]);
            $payment->setPaymentGatewayOrderId((string)$orderId);
            $this->entityManager->flush();
            $this->api->doPayment($this->api->getIframe() . $clientSecret);
        } catch (\Exception $exception) {
            $this->logger->error('paymob execution error', [
                'message' => $exception->getMessage()
            ]);
            $payment->setDetails(['status' => "failed", "message" => $exception->getMessage()]);
            $payment->setState(PaymentInterface::STATE_NEW);
            return;
        }

    }


//    public function execute_old($request, $client, $api)
//    {
//        $this->client = $client;
//        $this->api = $api;
//        /** @var SyliusPaymentInterface $payment */
//        $payment = $request->getModel();
//        $order = $payment->getOrder();
//
//        try {
//            $authToken = $this->authenticate();
//            $orderId = $this->createOrderId($payment, $authToken);
//            $paymentToken = $this->getPaymentKey($payment, $authToken, strval($orderId));
//            $payment->setDetails(['status' => PaymentInterface::STATE_PROCESSING]);
//            $payment->setPaymentGatewayOrderId((string)$orderId);
//            $this->entityManager->flush();
//            $iframeURL = "https://accept.paymobsolutions.com/api/acceptance/iframes/{$this->api->getIframe()}?payment_token={$paymentToken}";
//        } catch (RequestException $exception) {
//            $payment->setDetails(['status' => "failed", "message" => $exception->getMessage()]);
//            # set state to new to allow the user to retry the payment
//            $payment->setState(PaymentInterface::STATE_NEW);
//            return;
//        }
//        $this->api->doPayment($iframeURL);
//    }

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof SyliusPaymentInterface;
    }

    public function setApi($api): void
    {
        if (!$api instanceof SyliusApi) {
            throw new UnsupportedApiException('Not supported. Expected an instance of ' . SyliusApi::class);
        }

        $this->api = $api;
    }

    /**
     * Get the Authentication Token from Paymob
     *
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function authenticate()
    {
        $response = $this->client->request('POST', 'https://accept.paymobsolutions.com/api/auth/tokens',
            $this->getBodyWithHeader([
                'body' => \GuzzleHttp\json_encode([
                    'api_key' => $this->api->getApiKey(),
                ]),
            ])
        );

        return \GuzzleHttp\json_decode($response->getBody()->getContents())->token ?? '';
    }

    /**
     * Get the OrderId from Paymob
     *
     * @param SyliusPaymentInterface $payment
     * @param string $token
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function createOrderId(SyliusPaymentInterface $payment, string $token)
    {
        $response = $this->client->request('POST', 'https://accept.paymobsolutions.com/api/ecommerce/orders',
            $this->getBodyWithHeader([
                'body' => \GuzzleHttp\json_encode([
                    'auth_token' => $token,
                    'delivery_needed' => 'false',
                    'payment_methods' => [
                        $this->api->getIntegrationId()
                    ],
                    'amount_cents' => intval($payment->getOrder()->getTotal()),
                    'amount' => intval($payment->getOrder()->getTotal()),
                    'currency' => "SAR",
                    'merchant_id' => $this->api->getMerchantId(),
                    "special_reference" => "phe4sjw211q-1122-2221",
                    "billing_data" => [
                        "apartment" => "NA",
                        'email' => $payment->getOrder()->getCustomer()->getEmail() ?? "NA",
                        'phone_number' => $payment->getOrder()->getCustomer()->getPhoneNumber() ?? "NA",
                        "floor" => "NA",
                        'first_name' => $payment->getOrder()->getCustomer()->getFirstName() ?? "NA",
                        'last_name' => $payment->getOrder()->getCustomer()->getLastName() ?? "NA",
                        $payment->getOrder()->getBillingAddress()->getStreet() ?? "NA",
                        "building" => "NA",
                        'postal_code' => $payment->getOrder()->getBillingAddress()->getPostcode() ?? "NA",
                        'city' => $payment->getOrder()->getBillingAddress()->getCity() ?? "NA",
                        'country' => $payment->getOrder()->getBillingAddress()->getCountryCode() ?? "NA",
                        'state' => $payment->getOrder()->getBillingAddress()->getProvinceName() ?? "NA",
                    ],
                ]),
            ])
        )->getBody()->getContents();

        return \GuzzleHttp\json_decode($response)->id ?? $response;
    }

    /**
     * Get th e iFrame token from Paymob
     * @param SyliusPaymentInterface $payment
     * @param string $token
     * @param string $orderId
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getPaymentKey(SyliusPaymentInterface $payment, string $token, string $orderId)
    {
        $response = $this->client->request('POST', 'https://accept.paymobsolutions.com/api/acceptance/payment_keys',
            $this->getBodyWithHeader([
                'body' => \GuzzleHttp\json_encode([
                    'auth_token' => $token,
                    'amount_cents' => intval($payment->getOrder()->getTotal()),
                    'amount' => intval($payment->getOrder()->getTotal()),
                    'expiration' => '3600',
                    'order_id' => $orderId,
                    'currency' => "EGP",
                    'merchant_id' => $this->api->getMerchantId(),
                    'integration_id' => $this->api->getIntegrationId(),
                    'billing_data' => [
                        'first_name' => $payment->getOrder()->getCustomer()->getFirstName() ?? "NA",
                        'last_name' => $payment->getOrder()->getCustomer()->getLastName() ?? "NA",
                        'email' => $payment->getOrder()->getCustomer()->getEmail() ?? "NA",
                        'phone_number' => $payment->getOrder()->getCustomer()->getPhoneNumber() ?? "NA",
                        'apartment' => "NA",
                        'floor' => 'NA',
                        'street' => $payment->getOrder()->getBillingAddress()->getStreet() ?? "NA",
                        'building' => 'NA',
                        'shipping_method' => $payment->getOrder()->getShipments()->toArray()[0]->getMethod()->getName() ?? "NA",
                        'postal_code' => $payment->getOrder()->getBillingAddress()->getPostcode() ?? "NA",
                        'city' => $payment->getOrder()->getBillingAddress()->getCity() ?? "NA",
                        'country' => $payment->getOrder()->getBillingAddress()->getCountryCode() ?? "NA",
                        'state' => $payment->getOrder()->getBillingAddress()->getProvinceName() ?? "NA",
                    ],
                ]),
            ])
        );

        return \GuzzleHttp\json_decode($response->getBody()->getContents())->token ?? $response;
    }

    public function getBodyWithHeader($body)
    {
        $this->headers['headers']['Authorization'] = "Token {$this->api->getApiKey()}";
        return array_merge($body, $this->headers);
    }
}
