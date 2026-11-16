<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demo payment form (amount + order), a FormKit type on the `demo` profile (REQ-DEMO-015) rendered with
 * form_start / form_row (REQ-TWIG-005). Unprefixed names (block prefix '') so PaymentController::pay() keeps
 * reading `amount` / `order`. No CSRF token: the POST only builds the signed Redsys redirect form, it changes no
 * server state.
 *
 * @extends FormKitAbstractType<array{amount: string, order: string}>
 */
#[FormKitConfig('demo')]
final class PaymentFormType extends FormKitAbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', TextType::class, ['label' => 'Amount (cents)', 'attr' => ['inputmode' => 'numeric']])
            ->add('order', TextType::class, ['label' => 'Order (4–12 chars)', 'attr' => ['maxlength' => 12]])
            ->add('pay', SubmitType::class, ['label' => 'Pay with Redsys', 'attr' => ['class' => 'nowo-ui-btn nowo-ui-btn--primary', 'data-testid' => 'demo-pay']]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'          => 'POST',
            'csrf_protection' => false,
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
