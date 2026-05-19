<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;

class TextUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('filename', TextType::class, [
                'label' => 'Filename',
                'attr' => ['class' => 'form-input', 'placeholder' => 'notes.txt'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Content',
                'attr' => ['class' => 'form-input', 'rows' => 10, 'placeholder' => 'Enter your text content here...'],
            ])
            ->add('max_downloads', IntegerType::class, [
                'label' => 'Max Downloads',
                'data' => 1,
                'attr' => ['min' => 1, 'max' => 100, 'class' => 'form-input'],
            ])
            ->add('expiry_days', IntegerType::class, [
                'label' => 'Expires in (days)',
                'data' => 7,
                'attr' => ['min' => 1, 'max' => 30, 'class' => 'form-input'],
            ])
            ->add('password', TextType::class, [
                'label' => 'Password (optional)',
                'required' => false,
                'attr' => ['class' => 'form-input', 'placeholder' => 'Leave empty for no password'],
            ])
            ->add('recipient_email', EmailType::class, [
                'label' => 'Recipient Email (optional)',
                'required' => false,
                'attr' => ['class' => 'form-input', 'placeholder' => 'email@example.com'],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message (optional)',
                'required' => false,
                'attr' => ['class' => 'form-input', 'rows' => 3, 'placeholder' => 'Add a message for the recipient'],
            ]);
    }
}