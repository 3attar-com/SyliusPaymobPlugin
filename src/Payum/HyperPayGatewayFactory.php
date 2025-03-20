<?php

declare(strict_types=1);

namespace Ahmedkhd\SyliusPaymobPlugin\Payum;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;

final class HyperPayGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => 'hyperpay',
            'payum.factory_title' => 'Hyperpay',
            'payum.action.status' => new StatusAction(),
        ]);

        $config['payum.api'] = function (ArrayObject $config) {
            return new HyperPayApi(
                $config['authToken'],
                $config['entityId'],
                $config['iframe_url'],
                $config['domain'],
            );
        };
    }
}
