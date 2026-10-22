<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demo payment form (amount + order), rendered with form_start / form_row instead of raw markup (REQ-TWIG-005).
 * Unprefixed names (block prefix '') so PaymentController::pay() keeps reading `amount` / `order`. No CSRF token:
 * the POST only builds the signed Redsys redirect form, it changes no server state.
 */
final class PaymentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', TextType::class, ['label' => 'Amount (cents)', 'attr' => ['inputmode' => 'numeric']])
            ->add('order', TextType::class, ['label' => 'Order (4–12 chars)', 'attr' => ['maxlength' => 12]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'          => 'POST',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
