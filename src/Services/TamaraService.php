<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Psr\Log\LoggerInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class TamaraService extends AbstractService implements PaymobServiceInterface
{
    public function handelWebhook($request)
    {
        $this->logger->info('Tamara webhook', ['request' => json_decode($request->getContent(), true)]);

        if ($request->get('event_type') === 'order_captured') {
            $orderId = $request->get('order_number');

            $method = $this->container->get('doctrine.orm.entity_manager')->getRepository(PaymentMethod::class)->findOneBy(['code' => 'tamara']);

            $payment = $this->paymentRepository->findOneBy(['method' => $method, 'order' => $orderId]);
            $this->competeOrder($payment);
        }
        return true;
    }

}
