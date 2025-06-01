<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Services;

use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Monolog\Logger;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Bundle\ShopBundle\EmailManager\OrderEmailManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


final class MadfuService extends AbstractService implements PaymobServiceInterface
{


    public function handelWebhook($request)
    {
        $this->logger->info('Madfu webhook', ['request' => $request->getContent()]);

        if ($request->get('orderStatus') === 125)    {
            $orderId = $request->get('orderId');
            $payment = $this->paymentRepository->findOneBy(['paymentGatewayOrderId' => $orderId]);
            $this->competeOrder($payment);
            return true;
        }
        return false;
    }

}
