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

final class MadfuGatewayConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('domain', TextType::class);
        $builder->add('authrization', TextType::class);
        $builder->add('app_code', TextType::class);
        $builder->add('api_key', TextType::class);
        $builder->add('username', TextType::class);
        $builder->add('password', TextType::class);
        $builder->add('logo', TextType::class, [
            'required' => true,
        ]);
    }
}
