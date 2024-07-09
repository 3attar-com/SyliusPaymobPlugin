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
        try {
            $headers = [
                'Authorization' => 'Bearer OGFjN2E0Yzc5MDNhY2FjNTAxOTA0NTg4YTVkNTAyZDB8N0E2UWRHUWZza2NZUDIzYw==',
                'Accept' => 'application/json',
            ];
            $request = new GuzzleRequest('GET', 'https://eu-test.oppwa.com' . $resource['resourcePath'] . '?entityId=8ac7a4c7903acac50190458a299902d8', $headers);
            $res = ($this->client->send($request));
            return (json_decode($res->getBody()->getContents(), true));
        } catch (Exception $ex) {

        }

    }


}
// https://eu-test.oppwa.com/v1/checkouts/349F544E1BB73F6FBB555BC83C1B34B7.uat01-vm-tx03/payment?entityId=8ac7a4c7903acac50190458a299902d8
// https://eu-test.oppwa.com/v1/checkouts/349F544E1BB73F6FBB555BC83C1B34B7.uat01-vm-tx03/payment?entityId=8ac7a4c7903acac50190458a299902d8
