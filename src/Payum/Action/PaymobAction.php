<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Payum\Action;

use GuzzleHttp\Client;
use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ahmedkhd\SyliusPaymobPlugin\Services\AbstractService;
use Payum\Core\Exception\RequestNotSupportedException;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Ahmedkhd\SyliusPaymobPlugin\Payum\Action\CaptureAction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class PaymobAction extends AbstractController implements Action
{
  /** @var Client */
  private $client;

  /** @var SyliusApi */
  private $api;

  /** @var array */
  private $headers;

  protected $paymentRepository;

  public function __construct(ContainerInterface $container ,EntityRepository $paymentRepository)
  {
    $this->paymentRepository = $paymentRepository;
    parent::__construct($container);
      $this->headers['headers'] = [
          'Accept' => '*/*',
          'Content-Type' => 'application/json',
      ];
  }

  public function execute($request , $client , $api)
  {
      $this->client = $client;
      $this->api = $api;
      /** @var SyliusPaymentInterface $payment */
      $payment = $request->getModel();
      $order = $payment->getOrder();
     
      try {
          $authToken = $this->authenticate();
          $orderId = $this->createOrderId($payment, $authToken);
          $paymentToken = $this->getPaymentKey($payment, $authToken, strval($orderId));
          $payment->setDetails(['status' => PaymentInterface::STATE_PROCESSING ]);
          $payment->setPaymentGatewayOrderId((string)$orderId);
          $this->getDoctrine()->getManager()->flush();
          $iframeURL = "https://accept.paymobsolutions.com/api/acceptance/iframes/{$this->api->getIframe()}?payment_token={$paymentToken}";
      } catch (RequestException $exception) {
          $payment->setDetails(['status' => "failed", "message" => $exception->getMessage()]);
          # set state to new to allow the user to retry the payment
          $payment->setState(PaymentInterface::STATE_NEW);
          return;
      }

      $this->api->doPayment($iframeURL);
  }

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
                  'amount_cents' => intval($payment->getOrder()->getTotal()),
                  'currency' => "EGP",
                  'merchant_id' => $this->api->getMerchantId(),
                  "shipping_data" => [
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
      return array_merge($body, $this->headers);
  }
}
