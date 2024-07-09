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


    protected $paymentRepository;

    public function __construct(ContainerInterface $container, EntityRepository $paymentRepository)
    {
        parent::setContainer($container);

        $this->paymentRepository = $paymentRepository;
        $this->headers['headers'] = [
            'Accept' => '*/*',
            'Content-Type' => 'application/json',
        ];
        $this->container = $container;
    }

    public function getcheckoutId($orderId, $cost)
    {
        $client = new Client();
        $headers = [
            'Authorization' => 'Bearer OGFjN2E0Yzc5MDNhY2FjNTAxOTA0NTg4YTVkNTAyZDB8N0E2UWRHUWZza2NZUDIzYw==',
        ];
        $options = [
            'multipart' => [
            ]];
        $request = new GuzzleRequest('POST', "https://eu-test.oppwa.com/v1/checkouts?entityId=8ac7a4c7903acac50190458a299902d8&amount=$cost&currency=SAR&paymentType=DB&merchantTransactionId=$orderId", $headers);
        $res = $client->sendAsync($request, $options)->wait();
        return (json_decode($res->getBody()->getContents(), true));
    }

    public function execute($request, $client, $api)
    {
        $this->client = $client;
        $this->api = $api;

        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getModel();


        $order = $payment->getOrder();
        if ($order->getPromotionCoupon() != null) {
            if (in_array((string)$order->getPromotionCoupon()->getPromotion()->getId(), $ids) && $payment->getMethod()->getCode() != 'CIB') {
                return $this->redirectToRoute('sylius_shop_order_show', ['tokenValue' => $payment->getOrder()->getTokenValue()]);
            }
        }
        try {
            $payment->setDetails(['status' => PaymentInterface::STATE_PROCESSING]);
            $paymentToken = $this->getcheckoutId($order->getId(), number_format($payment->getOrder()->getTotal() / 100, 2));
            $payment->setPaymentGatewayOrderId($paymentToken['ndc']);
            $iframeURL = $this->api->getIframe() . "?payment_token={$paymentToken['id']}";
            $this->getDoctrine()->getManager()->flush();
            $this->api->doPayment($iframeURL);
        } catch (RequestException $exception) {
             $payment->setDetails(['status' => "failed", "message" => $exception->getMessage()]);
            return $this->redirectToRoute('sylius_shop_order_show', ['tokenValue' => $payment->getOrder()->getTokenValue()]);
        }

    }
}
