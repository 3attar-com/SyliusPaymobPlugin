<?php

declare(strict_types=1);

namespace Ahmedkhd\SyliusPaymobPlugin\Form\Type;

use BitBag\SyliusCmsPlugin\Entity\MediaInterface;
use BitBag\SyliusCmsPlugin\Form\Type\MediaAutocompleteChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

final class SyliusGatewayConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('secret_key', TextType::class);
        $builder->add('hamc_security', TextType::class);
        $builder->add('merchant_id', TextType::class);
        $builder->add('iframe_url', TextType::class);
        $builder->add('domain', TextType::class);
        $builder->add('integration_id', TextType::class);
        $builder->add('redirection_url', TextType::class);
        $builder->add('notification_url', TextType::class);
        $builder->add('logo', TextType::class, [
            'required' => true,
        ]);
    }
}
