<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Controller;

use Ahmedkhd\SyliusPaymobPlugin\Services\PaymobService;
use Ahmedkhd\SyliusPaymobPlugin\Services\PaymobServiceInterface;
use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use Monolog\Logger;
use Payum\Core\Payum;
use Sylius\Bundle\ShopBundle\EmailManager\OrderEmailManagerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

    public function __construct(
        Payum $payum,
        PaymobServiceInterface $paymobService,
        Logger $log,
        OrderEmailManagerInterface $orderEmailManager
    ) {
        $this->payum = $payum;
        $this->paymobService = $paymobService;
        $this->log = $log;
        $this->orderEmailManager = $orderEmailManager;
    }

     /**
     * @Route("/hyperpay", name="hyperpay")
     */
    // hyperpay iframe
    public function hyperpay(Request $request): Response
    {
        $hyperpayService = $this->container->get('ahmedkhd.sylius_paymob_plugin.service.hyperpay');
        $data = [
            'payment_id' => $request->query->all()['payment_token'],
            'url' => $request->getSchemeAndHttpHost() . "/payment/hyperpay/capture?method={$request->query->all()['method']}",
            'script_url' => "https://eu-test.oppwa.com"
        ];
        return $this->render('@AhmedkhdSyliusPaymobPlugin/hyperpay.html.twig', ['data' => $data]);
    }

    public function hyperpayAction(Request $request): Response
    {
        try {
            $query = $request->query->all();
            $hyperpayService = $this->container->get('ahmedkhd.sylius_paymob_plugin.service.hyperpay');
            $transactionStatus = $hyperpayService->getTransactionStatus($query);
            if($transactionStatus && $transactionStatus['result']['code'] === '000.100.110') {
                $payment = $this->paymobService->getPaymentById($transactionStatus['ndc']);
                $payment->setDetails(['status' => 'success', 'message' => "done"]);
                $order = $this->paymobService->setPaymentState($payment,
                    PaymentInterface::STATE_COMPLETED,
                    OrderPaymentStates::STATE_PAID
                );
                $this->orderEmailManager->sendConfirmationEmail($order);
                return $this->redirectToRoute('sylius_shop_order_thank_you');
            }
            return $this->redirectToRoute('payment_failure');
        } catch (\Exception $ex) {
            $this->log->emergency($ex);
            return $this->redirectToRoute('payment_failure');
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
}
