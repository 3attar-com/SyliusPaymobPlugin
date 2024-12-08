<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Controller;

use Ahmedkhd\SyliusPaymobPlugin\Services\PaymobService;
use Ahmedkhd\SyliusPaymobPlugin\Services\PaymobServiceInterface;
use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
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

    
    public function __construct(
        Payum $payum,
        PaymobServiceInterface $paymobService,
        Logger $log,
        OrderEmailManagerInterface $orderEmailManager,
        SymfonyEventDispatcherInterface $eventDispatcher,
        ParameterBagInterface $parameterBag
    ) {
        $this->payum = $payum;
        $this->paymobService = $paymobService;
        $this->log = $log;
        $this->orderEmailManager = $orderEmailManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->parameterBag = $parameterBag;
    }

     /**
     * @Route("/hyperpay", name="hyperpay")
     */
    // hyperpay iframe
    public function hyperpay(Request $request): Response
    {
        $method = $request->query->all()['method'];
        $payment_token = $request->query->get('payment_token');
        $hyperpayService = $this->container->get('ahmedkhd.sylius_paymob_plugin.service.hyperpay');
        $script_url = $hyperpayService->getHyperPayBaseUrl($method) . "/v1/paymentWidgets.js?checkoutId={$payment_token}";
        $data = [
            'payment_id' => $request->query->all()['payment_token'],
            'url' => $request->getSchemeAndHttpHost() . "/payment/hyperpay/capture?method={$method}",
            'script_url' => $script_url,
        ];
        return $this->render('@AhmedkhdSyliusPaymobPlugin/'.$method.'.html.twig', ['data' => $data]);
    }

    public function hyperpayAction(Request $request): Response
    {
        try {
            $query = $request->query->all();
            $hyperpayService = $this->container->get('ahmedkhd.sylius_paymob_plugin.service.hyperpay');
            $transactionStatus = $hyperpayService->getTransactionStatus($query);
            if($transactionStatus && $transactionStatus['payload']['result']['code'] === '000.000.000') {
                $payment = $this->paymobService->getPaymentById($transactionStatus['ndc']);
                $payment->setDetails(['status' => 'success', 'message' => "done"]);
                $order = $this->paymobService->setPaymentState($payment,
                    PaymentInterface::STATE_COMPLETED,
                    OrderPaymentStates::STATE_PAID
                );
                $this->dispatch($order);
                $this->orderEmailManager->sendConfirmationEmail($order);
                return $this->redirectToRoute('sylius_shop_order_thank_you');
            }
            return $this->redirectToRoute('payment_failure');
        } catch (\Exception $ex) {
            $this->log->emergency($ex);
            return $this->redirectToRoute('payment_failure');
        }
    }
    
    public function hyperpayWebhookAction(Request $request)
    {
        $http_body = file_get_contents('php://input');
        $key_from_configration = $this->parameterBag->get("HYPERPAY_SECRET_KEY");
        $headers = getallheaders();
        $iv_from_http_header = $headers['X-Initialization-Vector'];
        $auth_tag_from_http_header = $headers['X-Authentication-Tag'];
        $http=json_decode($http_body);
        $body = $http_body;
        $key = hex2bin($key_from_configration);
        $iv = hex2bin($iv_from_http_header);
        $auth_tag = hex2bin($auth_tag_from_http_header);
        $cipher_text = hex2bin($body);
        $result = openssl_decrypt($cipher_text, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $auth_tag);
        $result = json_decode($result, true);


        $this->log->emergency('Request details', [
            'method' => $request->getMethod(),
            'headers' => $request->headers->all(),
            'path' => $request->getPathInfo(),
            'query' => $request->query->all(),
            'body' => $request->request->all(),
            'file' => $http_body,
            'http' => $http,
            'iv_from_http_header' => $headers['X-Initialization-Vector'],
            'auth_tag_from_http_header' => $headers['X-Authentication-Tag'],
            'result' => $result
        ]);

        try {
            if($result['type'] === 'PAYMENT' && $result['payload']['result']['code'] === '000.000.000') {
                $payment = $this->paymobService->getPaymentById($result['payload']['ndc']);
                $payment->setDetails(['status' => 'success', 'message' => "done"]);
                $order = $this->paymobService->setPaymentState($payment,
                    PaymentInterface::STATE_COMPLETED,
                    OrderPaymentStates::STATE_PAID
                );
                $this->dispatch($order);
//                $this->orderEmailManager->sendConfirmationEmail($order);
                return new Response('success', 200, [
                    'Content-Type' => 'text/xml'
                ]);
            }
            return new Response('failed', 400, [
                'Content-Type' => 'text/xml'
            ]);
        } catch (\Exception $ex) {
            $this->log->emergency($ex);
            return new Response($ex->getMessage(), 400, [
                'Content-Type' => 'text/xml'
            ]);
        }
    }

    public function doAction(Request $request): Response
    {
        try {
            $_GET_PARAMS = $request->query->all();
            $order = $this->paymobService->getOrder($_GET_PARAMS['order']);
            if (is_null($order)){
                return $this->redirectToRoute('sylius_shop_invoice_thank_you');
            }
            if (!empty($_GET_PARAMS) && $_GET_PARAMS['success'] == 'true') {
                $this->orderEmailManager->sendConfirmationEmail($order);
                return $this->redirectToRoute('sylius_shop_order_thank_you');
            }
            return $this->redirectToRoute('sylius_shop_order_show', ['tokenValue' => $order->getTokenValue()]);
        } catch (\Exception $ex) {
            $this->log->emergency($ex);
        }
    }

    public function webhookAction(Request $request): Response
    {
        try {
            $paymobResponse = \GuzzleHttp\json_decode($request->getContent());
            $response = false;
            if ($paymobResponse->obj->api_source == "INVOICE" && $paymobResponse->obj->order->data->type =="credit" ) {
                try {
                    $walletService = $this->get('workouse_digital_wallet.wallet_service');

                    $credit = $paymobResponse->obj->order->amount_cents;
                    $email = $paymobResponse->obj->order->shipping_data->email;

                    $customerRepo = $this->getDoctrine()->getRepository(Customer::class);
                    $customer = $customerRepo->findOneBy(['email' => $email]);
                    $walletService->addCreditToCustomer($customer  ,"by Paymob", [ "wallet"=> $credit , "expiredAt" => "01/01/2099" ]);

                    $response = true;
                }catch (\Exception $exception){
                    $this->log->emergency($exception);
                }

            } else if (

                !empty($paymobResponse) &&
                isset($paymobResponse->obj->is_standalone_payment) &&
                isset($paymobResponse->obj->success) && $paymobResponse->obj->success &&
                isset($paymobResponse->type) && $paymobResponse->type == PaymobService::TRANSACTION_TYPE &&
                isset($paymobResponse->obj->order->paid_amount_cents) &&
                isset($paymobResponse->obj->order->id)
            ) {
                $payment = $this->paymobService->getPaymentById($paymobResponse->obj->order->id);

                $orderAmount = $paymobResponse->obj->order->paid_amount_cents;
                $amount = $payment->getAmount();

                if ($orderAmount === $amount) {
                    $payment->setDetails(['status' => 'success', 'message' => "amount: {$amount}"]);
                    $order = $this->paymobService->setPaymentState($payment,
                        PaymentInterface::STATE_COMPLETED,
                        OrderPaymentStates::STATE_PAID
                    );
                    $response = true;
                }
            } else if (isset($paymobResponse->obj->order->id)) {
                $paymentId = $paymobResponse->obj->order->id;
                $payment = $this->paymobService->getPaymentById($paymentId);
                $payment->setDetails(["status" => "failed", "message" => "payment_id: {$paymentId}"]);

                # create new payment so user can try to pay again
                $newPayment = clone $payment;
                $newPayment->setState(PaymentInterface::STATE_NEW);
                $payment->getOrder()->addPayment($newPayment);

                $order = $this->paymobService->setPaymentState($payment,
                    PaymentInterface::STATE_FAILED,
                    OrderPaymentStates::STATE_AWAITING_PAYMENT
                );
            }

            $this->log->emergency("paymob callback");
            $this->log->emergency($request);

        } catch (\Exception $ex) {
            $this->log->emergency($request);
            $this->log->emergency($ex);
        }

        return new Response(\GuzzleHttp\json_encode(['success' => $response]), $response ? 200 : 400);
    }

    private function dispatch($resource)  {
        $event = new ResourceControllerEvent($resource);
        $this->eventDispatcher->dispatch($event, 'sylius.payment.post_complete');
    }
}
