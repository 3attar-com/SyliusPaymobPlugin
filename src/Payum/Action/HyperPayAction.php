<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Payum\Action;

use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
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

final class HyperPayAction extends AbstractController implements Action
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

    public function __construct(ContainerInterface $container ,EntityRepository $paymentRepository)
    {
      parent::setContainer($container);

      $this->paymentRepository = $paymentRepository;
        $this->headers['headers'] = [
            'Accept' => '*/*',
            'Content-Type' => 'application/json',
        ];
        $this->container = $container;

    }

    private function prepareConfig($api)
    {
      $this->token = $api->getApiKey();
      $this->url = $api->getIntegrationId();
      $this->entityId = $api->getMerchantId();
    }

    public function getcheckoutId($orderId , $cost)
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
            'billing.street1' => $order->getBillingAddress()->getStreet() ?? "NA",
            'billing.city' => $order->getBillingAddress()->getCity() ?? "NA",
            'billing.state' => $order->getBillingAddress()->getProvinceName() ?? "NA",
            'billing.country' => $order->getBillingAddress()->getCountryCode() ?? "NA",
            'billing.postcode' => $order->getBillingAddress()->getPostcode() ?? "NA",
            'customer.givenName' => $order->getCustomer()->getFullName() ?? "NA",
            'customer.mobile' => $order->getCustomer()->getPhoneNumber() ?? "NA"
        ];
        $urlWithParams = $this->url . '/v1/checkouts?' . http_build_query($queryParams);
        $request = new GuzzleRequest('POST', $urlWithParams, $headers);
        $res = $client->sendAsync($request, [])->wait();
        return (json_decode($res->getBody()->getContents() , true));
    }
    public function execute($request , $client , $api)
    {
        $this->prepareConfig($api);
        $this->client = $client;
        $this->api = $api;

        $payment = $request->getModel();
        $order = $payment->getOrder();

        try {
            $payment->setDetails(['status' => PaymentInterface::STATE_PROCESSING ]);
            $paymentToken = $this->getcheckoutId($order->getId() , number_format($payment->getOrder()->getTotal() / 100, 2));
            $payment->setPaymentGatewayOrderId($paymentToken['ndc']);
            $iframeURL = $this->api->getIframe() . "?payment_token={$paymentToken['id']}" ."&method={$payment->getMethod()->getCode()}";
            $this->getDoctrine()->getManager()->flush();
            $this->api->doPayment($iframeURL);
        } catch (RequestException $exception) {
            $payment->setDetails(['status' => "failed", "message" => $exception->getMessage()]);
            $payment->setState(PaymentInterface::STATE_NEW);
            return $this->redirectToRoute('sylius_shop_order_show', ['tokenValue' => $payment->getOrder()->getTokenValue()]);
        }

    }
}
