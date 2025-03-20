<?php


namespace Ahmedkhd\SyliusPaymobPlugin\Services;


use App\Entity\Payment\Payment;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Bundle\ShopBundle\EmailManager\OrderEmailManagerInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;

abstract class AbstractService
{
    /** @var ContainerInterface */
    protected $container;
    protected $eventDispatcher;
    private   $orderEmailManager;
    protected $paymentRepository;
    protected $customerRepository;
    protected $logger;
    /**
     * AbstractService constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container,
                                SymfonyEventDispatcherInterface $eventDispatcher,
                                OrderEmailManagerInterface $orderEmailManager,
                                PaymentRepositoryInterface $paymentRepository,
                                CustomerRepositoryInterface $customerRepository
    )
    {
        $this->container = $container;
        $this->eventDispatcher = $eventDispatcher;
        $this->orderEmailManager = $orderEmailManager;
        $this->paymentRepository = $paymentRepository;
        $this->customerRepository = $customerRepository;
        $this->logger = $this->container->get('monolog.logger.payment');
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
        /**@var $payment PaymentInterface|null */
        $payment = $this->paymentRepository->findOneBy(['paymentGatewayOrderId' => $payment_id]);
        if (null === $payment or $payment->getState() !== PaymentInterface::STATE_NEW) {
            throw new NotFoundHttpException('Order not have available payment');
        }
        return $payment;
    }

    public function getOrder($payment_id)
    {
        $em = $this->container->get('doctrine.orm.default_entity_manager');
        $paymentRepo = $em->getRepository(Payment::class);
        $order = $paymentRepo->findOneBy([ 'paymentGatewayOrderId' => $payment_id])->getOrder();
        return $order;
    }

    public function completeOrderById($payment_id)
    {
        $payment = $this->getPaymentById($payment_id);
        $this->competeOrder($payment);
    }
    public function competeOrder($payment)
    {
        $payment->setDetails(['status' => 'success', 'message' => "done"]);
        $order = $this->setPaymentState($payment,
            PaymentInterface::STATE_COMPLETED,
            OrderPaymentStates::STATE_PAID
        );
        $this->dispatch($order);
//        $this->orderEmailManager->sendConfirmationEmail($order);
    }
    private function dispatch($resource)  {
        $event = new ResourceControllerEvent($resource);
        $this->eventDispatcher->dispatch($event, 'sylius.payment.post_complete');
    }
}
