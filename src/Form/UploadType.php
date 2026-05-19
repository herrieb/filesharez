<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class UploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'File',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new Assert\File(maxSize: '20G'),
                ],
                'attr' => [
                    'class' => 'hidden',
                    'id' => 'file-upload-input',
                ],
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