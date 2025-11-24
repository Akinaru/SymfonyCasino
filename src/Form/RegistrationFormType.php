<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pseudo', TextType::class, [
                'label' => 'Pseudo',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Merci de saisir un pseudo.']),
                    new Assert\Length([
                        'min' => 4,
                        'max' => 50,
                        'minMessage' => 'Le pseudo doit contenir au moins {{ limit }} caractères.',
                    ]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Merci de saisir une adresse e-mail.']),
                    new Assert\Email(['message' => 'Adresse e-mail invalide.']),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('avatar', TextType::class, [
                'label' => 'Avatar (pseudo Minecraft)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ex: Steve',
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Merci de saisir un mot de passe.']),
                    new Assert\Length([
                        'min' => 6,
                        'max' => 255,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                    ]),
                ],
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}
