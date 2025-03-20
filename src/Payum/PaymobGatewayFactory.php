<?php

declare(strict_types=1);

namespace Ahmedkhd\SyliusPaymobPlugin\Payum;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;

final class PaymobGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => 'paymob',
            'payum.factory_title' => 'Paymob',
            'payum.action.status' => new StatusAction(),
        ]);

        $config['payum.api'] = function (ArrayObject $config) {
            return new SyliusApi(
                $config['secret_key'],
                $config['hamc_security'],
                $config['merchant_id'],
                $config['iframe_url'],
                $config['integration_id'],
                $config['domain'],
                $config['notification_url'],
                $config['redirection_url']
            );
        };
    }
}
