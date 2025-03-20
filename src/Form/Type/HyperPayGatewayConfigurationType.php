<?php

declare(strict_types=1);

namespace Ahmedkhd\SyliusPaymobPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class HyperPayGatewayConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('authToken', TextType::class);
        $builder->add('entityId', TextType::class);
        $builder->add('iframe_url', TextType::class);
        $builder->add('domain', TextType::class);
        $builder->add('logo', TextType::class, [
            'required' => true,
        ]);
    }
}
