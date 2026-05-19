<?php

namespace App\Form\Account;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

class PasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Current Password',
                'mapped' => false,
                'constraints' => [
                    new UserPassword(message: 'Current password is incorrect.'),
                ],
                'attr' => ['class' => 'form-input w-full', 'autocomplete' => 'current-password'],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'New Password',
                    'attr' => ['class' => 'form-input w-full', 'autocomplete' => 'new-password'],
                    'constraints' => [
                        new Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters.'),
                        new PasswordStrength(minScore: PasswordStrength::STRENGTH_MEDIUM),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirm New Password',
                    'attr' => ['class' => 'form-input w-full', 'autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'The passwords do not match.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}