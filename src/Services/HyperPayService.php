<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Services;

use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use Exception;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Client;
use Sylius\Component\Core\Model\PaymentMethod as BasePaymentMethod;
use Payum\Core\ApiAwareInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig as BaseGatewayConfig;

final class HyperPayService extends AbstractService implements PaymobServiceInterface
{
    protected $paymentRepository;
    protected $client;

    public const TRANSACTION_TYPE = "TRANSACTION";

    public function __construct(ContainerInterface $container, EntityRepository $paymentRepository)
    {
        parent::__construct($container);
        $this->paymentRepository = $paymentRepository;
        $this->client = new Client();
    }

    public function getTransactionStatus($resource)
    {
        $em = $this->container->get('doctrine.orm.default_entity_manager');
        $paymentRepo = $em->getRepository(BaseGatewayConfig::class);
         $pay = $paymentRepo->findoneby([
            'gatewayName' => $resource['method']
         ]);
        try {
        $headers = [
            'Authorization' => 'Bearer ' . $pay->getConfig()['api_key'],
            'Accept' => 'application/json',
        ];
        $request = new GuzzleRequest('GET', $pay->getConfig()['integration_id'] . $resource['resourcePath'] . '?entityId='.$pay->getConfig()['merchant_id'], $headers);
           $res = ($this->client->send($request));
            return (json_decode($res->getBody()->getContents(), true));
        } catch (Exception $ex) {
            
        }
    }
    public function getHyperPayBaseUrl($method)
    {
        $em = $this->container->get('doctrine.orm.default_entity_manager');
        $paymentRepo = $em->getRepository(BaseGatewayConfig::class);
         $pay = $paymentRepo->findoneby([
            'gatewayName' => $method
         ]);
        return $pay->getConfig()['integration_id'];
    }
}