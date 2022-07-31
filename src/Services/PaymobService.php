<?php


namespace Ahmedkhd\SyliusPaymobPlugin\Services;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Entity\Order\Order;

final class PaymobService extends AbstractService implements PaymobServiceInterface
{
    protected $paymentRepository;

    public const TRANSACTION_TYPE = "TRANSACTION";

    public function __construct(ContainerInterface $container, EntityRepository $paymentRepository)
    {
        parent::__construct($container);
        $this->paymentRepository = $paymentRepository;
    }

    public function setPaymentState($payment, $paymentState, $orderPaymentState)
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $payment->setState($paymentState);
        $order->setPaymentState($orderPaymentState);
        $this->flushPaymentAndOrder($payment, $order);

        return $order;
    }

    public function flushPaymentAndOrder($payment, $order)
    {
        $em = $this->container->get('doctrine.orm.default_entity_manager');
        $em->persist($payment);
        $em->persist($order);
        $em->flush();
    }

    /**
     * @param $payment_id
     * @return PaymentInterface
     */
    public function getPaymentById($payment_id): PaymentInterface
    {
        $em = $this->container->get('doctrine.orm.default_entity_manager');
        $orderRepo = $em->getRepository(Order::class);
        /**@var $payment PaymentInterface|null */
        $payment =$orderRepo->find($payment_id)->getLastPayment();
        
        if (null === $payment OR $payment->getState() !== PaymentInterface::STATE_NEW) {
            throw new NotFoundHttpException('Order not have available payment');
        }
        return $payment;
    }
}
