<?php

declare (strict_types = 1);

namespace Ahmedkhd\SyliusPaymobPlugin\Payum\Action;

use Ahmedkhd\SyliusPaymobPlugin\Payum\SyliusApi;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Request\Capture;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class CaptureAction extends AbstractController implements ActionInterface, ApiAwareInterface
{
    /** @var Client */
    private $client;

    /** @var SyliusApi */
    private $api;

    /** @var array */
    private $headers;

    private $paymentAction;

    public function __construct(Client $client, ContainerInterface $container)
    {
        parent::setContainer($container);
        $this->client = $client;
        $this->headers['headers'] = [
            'Accept' => '*/*',
            'Content-Type' => 'application/json',
        ];
    }

    public function execute($request)
    {
        $this->paymentAction = $this->container->get("attar.sylius_paymob_plugin.service." .$request->getModel()->getMethod()->getCode());
        $this->paymentAction->execute($request , $this->client , $this->api);
    }

    public function supports($request): bool
    {
       return $request instanceof Capture &&
        $request->getModel() instanceof SyliusPaymentInterface;
    }

    public function setApi($api): void
    {
        if (!$api instanceof SyliusApi) {
            throw new UnsupportedApiException('Not supported. Expected an instance of ' . SyliusApi::class);
        }

        $this->api = $api;
    }

}
