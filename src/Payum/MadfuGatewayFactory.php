<?php

declare(strict_types=1);

namespace Ahmedkhd\SyliusPaymobPlugin\Payum;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;

final class MadfuGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => 'madfu',
            'payum.factory_title' => 'Madfu',
            'payum.action.status' => new StatusAction(),
        ]);

        $config['payum.api'] = function (ArrayObject $config) {
            return new MadfuApi(
                $config['domain'],
                $config['api_key'],
                $config['app_code'],
                $config['password'],
                $config['username'],
                $config['authrization'],
            );
        };
    }
}
