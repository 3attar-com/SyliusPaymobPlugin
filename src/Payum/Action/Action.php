<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Payum\Action;

interface Action
{
    public function execute($request , $client , $api);
}
