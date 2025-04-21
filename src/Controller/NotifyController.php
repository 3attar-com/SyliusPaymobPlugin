<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Controller;

use Ahmedkhd\SyliusPaymobPlugin\Services\PaymobService;
use Ahmedkhd\SyliusPaymobPlugin\Services\PaymobServiceInterface;
use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use GuzzleHttp\Client;
use Monolog\Logger;
use Payum\Core\Payum;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Bundle\ShopBundle\EmailManager\OrderEmailManagerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Ahmedkhd\SyliusPaymobPlugin\Services\TamaraService;

class NotifyController extends AbstractController
{
    /** @var Payum */
    private $payum;

    /** @var PaymobServiceInterface */
    private $paymobService;

    /**
     * @var Logger
     */
    private $log;

    /** @var OrderEmailManagerInterface */
    private $orderEmailManager;

    private $eventDispatcher;

    private $parameterBag;

    private $tamaraService;


    public function __construct(
        Payum $payum,
        PaymobServiceInterface $paymobService,
        Logger $log,
        OrderEmailManagerInterface $orderEmailManager,
        SymfonyEventDispatcherInterface $eventDispatcher,
        ParameterBagInterface $parameterBag,
        TamaraService $tamaraService
    ) {
        $this->payum = $payum;
        $this->paymobService = $paymobService;
        $this->log = $log;
        $this->orderEmailManager = $orderEmailManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->parameterBag = $parameterBag;
        $this->tamaraService = $tamaraService;
    }

    /**
     * @Route("/hyperpay", name="hyperpay")
     */
    // hyperpay iframe
    public function hyperpay(Request $request): Response
    {
        $method = $request->query->all()['method'];
        $hyperpayService = $this->container->get('ahmedkhd.sylius_paymob_plugin.service.hyperpay');
        $iframeData = $hyperpayService->getIframeData($request);
        return $this->render('@AhmedkhdSyliusPaymobPlugin/'.$method.'.html.twig', ['data' => $iframeData]);
    }

    // hyperpay website redirect
    public function hyperpayAction(Request $request): Response
    {
        try {
            $query = $request->query->all();
            $hyperpayService = $this->container->get('ahmedkhd.sylius_paymob_plugin.service.hyperpay');
            $transactionStatus = $hyperpayService->getTransactionStatus($query);
            if ($transactionStatus['paymentBrand'] == 'TAMARA') {
                $merchantTransactionId = $transactionStatus['merchantTransactionId'];
                $orderDetails = $this->tamaraService->getOrderByReferenceId($merchantTransactionId);
                if ($orderDetails && isset($orderDetails['status']) && $orderDetails['status'] == 'fully_captured') {
                    $this->paymobService->completeOrderById($transactionStatus['ndc']);
                    return $this->redirectToRoute('sylius_shop_order_thank_you');
                }
                return $this->redirectToRoute('payment_failure');
            }
            if($transactionStatus && ($transactionStatus['result']['code'] == '000.100.110')) {
                $this->paymobService->completeOrderById($transactionStatus['ndc']);
                return $this->redirectToRoute('sylius_shop_order_thank_you');
            }
            if($transactionStatus && ($transactionStatus['result']['code'] == '000.000.000')) {
                return $this->redirectToRoute('sylius_shop_order_thank_you');
            }

            return $this->redirectToRoute('payment_failure');
        } catch (\Exception $ex) {
            $this->log->emergency($ex->getMessage());
            return $this->redirectToRoute('payment_failure');
        }
    }

    // hande all webhooks by get param gateway on request param
    public function webhook(Request $request): Response
    {
        try {
            $params = $request->query->all();
            $paymentService = $this->container->get('ahmedkhd.sylius_paymob_plugin.service.'.$params['gateway']);
            $result = $paymentService->handelWebhook($request);
            if ($result)    {
                return new Response('success', 200, [
                    'Content-Type' => 'text/xml'
                ]);
            }
            return new Response('failed', 400, [
                'Content-Type' => 'text/xml'
            ]);
        }catch (\Exception $ex) {
            $this->log->error($ex->getMessage());
        }
    }

    public function hyperpayWebhookAction(Request $request)
    {
        $request->query->set('gateway', 'hyperpay');
        return $this->webhook($request);
    }

    public function doAction(Request $request)
    {
        try {
            $_GET_PARAMS = $request->query->all();

            $order = $this->paymobService->getOrder($_GET_PARAMS['order']);
            if (is_null($order)){
                return $this->redirectToRoute('sylius_shop_invoice_thank_you');
            }
            if (!empty($_GET_PARAMS) && $_GET_PARAMS['success'] == 'true') {
                return $this->redirectToRoute('sylius_shop_order_thank_you');
            }
        } catch (\Exception $ex) {
            $this->log->emergency($ex);
        }
        return $this->redirectToRoute('sylius_shop_order_show', ['tokenValue' => $order->getTokenValue()]);

    }



}
