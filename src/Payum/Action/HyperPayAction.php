<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Payum\Action;

use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ahmedkhd\SyliusPaymobPlugin\Services\AbstractService;
use Payum\Core\Exception\RequestNotSupportedException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Client;
use Ahmedkhd\SyliusPaymobPlugin\Payum\Action\CaptureAction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use GuzzleHttp\Exception\ClientException;

final class HyperPayAction implements Action
{
    /** @var Client */
    private $client;

    /** @var SyliusApi */
    private $api;

    /** @var array */
    private $headers;

    /** @var ContainerInterface */
    protected $container;

    private $token;

    private $url;

    private $entityId;

    protected $paymentRepository;
    protected $entityManager;
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

    private function prepareConfig($api)
    {
        $this->token = $api->getAuthToken();
        $this->url = $api->getDomain();
        $this->entityId = $api->getEntityId();
    }

    public function prepareOrderAddress($order)
    {
        $address = [
            'billing.street1' => "٣٩٦٩ شارع عبدالله بن فيصل، حي المربع،",
            'billing.city' => "الرياض",
            'billing.state' => "الرياض",
            'billing.country' => "SA",
            'billing.postcode' => "12613"
        ];

        if ($order->getState() != 'wallet') {
            $address = [
                'billing.street1' => $order->getBillingAddress()->getStreet() ?? "NA",
                'billing.city' => $order->getBillingAddress()->getCity() ?? "NA",
                'billing.state' => $order->getBillingAddress()->getProvinceName() ?? "NA",
                'billing.country' => $order->getBillingAddress()->getCountryCode() ?? "NA",
                'billing.postcode' => $order->getBillingAddress()->getPostcode() ?? "NA"
            ];
        }
        return $address;
    }
    public function getcheckoutId($order, $cost)
    {
        $client = new Client();
        $headers = [
            'Authorization' => "Bearer $this->token",
        ];
        $queryParams = [
            'entityId' => $this->entityId,
            'amount' => $cost,
            'currency' => 'SAR',
            'paymentType' => 'DB',
            'merchantTransactionId' => $order->getId(),
            'customer.email' => $order->getCustomer()->getEmail() ?? "NA",
            'customer.givenName' => $order->getCustomer()->getFullName() ?? "NA",
            'customer.mobile' => $order->getCustomer()->getPhoneNumber() ?? "NA"
        ];
        $queryParams = array_merge($queryParams, $this->prepareOrderAddress($order));
        $urlWithParams = $this->url . '/v1/checkouts?' . http_build_query($queryParams);
        $request = new GuzzleRequest('POST', $urlWithParams, $headers);
        $res = $client->sendAsync($request, [])->wait();
        return (json_decode($res->getBody()->getContents(), true ));
    }
    public function getcheckoutTamaraId($order, $cost)
    {
        try {
            $client = new Client();
            $headers = [
                'Authorization' => "Bearer $this->token",
            ];

            $products = array_map(fn($item) => [
                'merchantItemId' => $item->getProduct()->getId(),
                'name' => $item->getProduct()->getName(),
                'quantity' => $item->getQuantity(),
                'sku' => $item->getProduct()->getCode(),
                'totalAmount' => number_format($item->getTotal() / 100, 2, '.', ''),
                'type' => 'PHYSICAL'
            ], $order->getItems()->toArray());
            $queryParams = [
                'entityId' => $this->entityId,
                'amount' => $cost,
                'currency' => 'SAR',
                'paymentType' => 'DB',
                'merchantTransactionId' => $order->getId(),
                'customer.email' => $order->getCustomer()->getEmail() ?? "NA",
                'customer.surname' => $order->getCustomer()->getLastName() ?? $order->getBillingAddress()->getLastName() ?? "NA",
                'customer.givenName' => $order->getCustomer()->getFirstName() ?? $order->getBillingAddress()->getFirstName() ?? "NA",
                'customParameters[instalments]' => 4,
                'customParameters[tamara_payment_type]' => 'PAY_BY_INSTALMENTS',
                'integrity'=> true,
            ];
            $queryParams = array_merge($queryParams, $this->prepareOrderAddress($order));
            foreach ($products as $index => $item) {
                foreach ($item as $key => $value) {
                    $queryParams["cart.items[$index].$key"] = $value;
                }
            }
            $res = $client->post($this->url . '/v1/checkouts', [
                'headers' => $headers,
                'form_params' => $queryParams
            ]);
        } catch (ClientException $exception) {
            $body = $exception->getResponse()->getBody()->getContents();
            $data = json_decode($body, true);
            $this->logger->error("Failed to Create Iframe" , [
                'data' => $data
            ]);
        }  catch (\Exception $exception)   {
            $this->logger->error($exception->getMessage());
        }
        return (json_decode($res->getBody()->getContents(), true ));
    }

    public function execute($request, $client, $api)
    {
        $this->prepareConfig($api);
        $this->client = $client;
        $this->api = $api;

        $payment = $request->getModel();
        $order = $payment->getOrder();

        try {
            $payment->setDetails(['status' => PaymentInterface::STATE_PROCESSING]);
            if (str_contains($payment->getMethod()->getCode(), 'tamara'))  {
                $paymentToken = $this->getcheckoutTamaraId($order, number_format($payment->getOrder()->getTotal() / 100, 2));
            }   else    {
                $paymentToken = $this->getcheckoutId($order, number_format($payment->getOrder()->getTotal() / 100, 2));
            }
            $payment->setPaymentGatewayOrderId($paymentToken['ndc']);

            $method = str_contains($payment->getMethod()->getCode(), 'tamara') ? 'tamara' : $payment->getMethod()->getCode();
            $iframeURL = $this->api->getIframeUrl() . "?payment_token={$paymentToken['id']}" . "&method={$method}";
            $this->entityManager->flush();

            $this->api->doPayment($iframeURL);
        } catch (RequestException $exception) {
            $payment->setDetails(['status' => "failed", "message" => $exception->getMessage()]);
            $payment->setState(PaymentInterface::STATE_NEW);
            return $this->redirectToRoute('sylius_shop_order_show', ['tokenValue' => $payment->getOrder()->getTokenValue()]);
        }

    }
}
