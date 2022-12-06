<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Controller;

use Ahmedkhd\SyliusPaymobPlugin\Services\PaymobService;
use Ahmedkhd\SyliusPaymobPlugin\Services\PaymobServiceInterface;
use App\Entity\Order\Order;
use Monolog\Logger;
use Payum\Core\Payum;
use Sylius\Bundle\ShopBundle\EmailManager\OrderEmailManagerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

    public function doAction(Request $request): Response
    {
        try {
            $_GET_PARAMS = $request->query->all();

            $order = $this->paymobService->getOrder($_GET_PARAMS['merchant_order_id']);

            if (!empty($_GET_PARAMS) && $_GET_PARAMS['success'] == 'true') {
                $this->orderEmailManager->sendConfirmationEmail($order);
                if ($order->getChannel()->getCode() == '3attar_web') {
                    return $this->redirectToRoute('sylius_shop_order_thank_you');
                } else {
                    return $this->redirect('https://3attar.page.link?apn=com.attar.app&ibi=com.3attar.ios.app&link=https://3attar.com?payment=1');
                }
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
            if (
                !empty($paymobResponse) &&
                isset($paymobResponse->obj->is_standalone_payment) &&
                isset($paymobResponse->obj->success) && $paymobResponse->obj->success &&
                isset($paymobResponse->type) && $paymobResponse->type == PaymobService::TRANSACTION_TYPE &&
                isset($paymobResponse->obj->order->paid_amount_cents) &&
                isset($paymobResponse->obj->order->merchant_order_id)
            ) {
                $payment = $this->paymobService->getPaymentById($paymobResponse->obj->order->merchant_order_id);

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
            } else if (isset($paymobResponse->obj->order->merchant_order_id)) {
                $paymentId = $paymobResponse->obj->order->merchant_order_id;
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
