<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Services;

use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use Monolog\Logger;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PaymobService extends AbstractService implements PaymobServiceInterface
{
    protected $paymentRepository;
    protected $customerRepository;

    public const TRANSACTION_TYPE = "TRANSACTION";

    public function handelWebhook($request)
    {
        try {
            $paymobResponse = \GuzzleHttp\json_decode($request->getContent());
            $response = false;
            if ($paymobResponse->obj->api_source == "INVOICE" && $paymobResponse->obj->order->data->type == "credit") {
                try {
                    $walletService = $this->get('workouse_digital_wallet.wallet_service');

                    $credit = $paymobResponse->obj->order->amount_cents;
                    $email = $paymobResponse->obj->order->shipping_data->email;

                    $customer = $this->customerRepository->findOneBy(['email' => $email]);
                    $walletService->addCreditToCustomer($customer, "by Paymob", ["wallet" => $credit, "expiredAt" => "01/01/2099"]);

                    $response = true;
                } catch (\Exception $exception) {
                    $this->logger->emergency($exception);
                }

            } else if (

                !empty($paymobResponse) &&
                isset($paymobResponse->obj->is_standalone_payment) &&
                isset($paymobResponse->obj->success) && $paymobResponse->obj->success &&
                isset($paymobResponse->type) && $paymobResponse->type == PaymobService::TRANSACTION_TYPE &&
                isset($paymobResponse->obj->order->paid_amount_cents) &&
                isset($paymobResponse->obj->order->id)
            ) {
                $payment = $this->getPaymentById($paymobResponse->obj->order->id);
                $orderAmount = $paymobResponse->obj->order->paid_amount_cents;
                $amount = $payment->getAmount();
                if ($orderAmount === $amount) {
                    $this->competeOrder($payment);
                }
            } else if (isset($paymobResponse->obj->order->id)) {
                $paymentId = $paymobResponse->obj->order->id;
                $payment = $this->getPaymentById($paymentId);
                $payment->setDetails(["status" => "failed", "message" => "payment_id: {$paymentId}"]);

                # create new payment so user can try to pay again
                $newPayment = clone $payment;
                $newPayment->setState(PaymentInterface::STATE_NEW);
                $payment->getOrder()->addPayment($newPayment);

                $order = $this->setPaymentState($payment,
                    PaymentInterface::STATE_FAILED,
                    OrderPaymentStates::STATE_AWAITING_PAYMENT
                );
            }
            $this->logger->emergency('"paymob callback"', [$paymobResponse]);
        } catch (\Exception $ex) {
            $this->logger->emergency($request);
            $this->logger->emergency($ex);
        }

        return new Response(\GuzzleHttp\json_encode(['success' => $response]), $response ? 200 : 400);
    }
}
