<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Entity;

trait PaymentGatewayOrderTrait
{
    /**
     * @ORM\Column(type="string" , name="pg_order_id")
     */
    protected $paymentGatewayOrderId;

    public function getPaymentGatewayOrderId(): ?string
    {
        return $this->paymentGatewayOrderId;
    }

    public function setPaymentGatewayOrderId(string $paymentGatewayOrderId): void
    {
        $this->paymentGatewayOrderId = $paymentGatewayOrderId;
    }
}
