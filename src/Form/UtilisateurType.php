<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// types de champs
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class UtilisateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Édition : email, pseudo, balance (entier), avatar (string)
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('pseudo', TextType::class, [
                'label' => 'Pseudo',
                'required' => false,
            ])
            ->add('balance', IntegerType::class, [
                'label' => 'Balance (€) — entier',
                'required' => false,
                'empty_data' => '0',
                'attr' => [
                    'min' => 0,
                    'step' => 1,
                ],
            ])
            ->add('avatar', TextType::class, [
                'label' => 'Avatar (pseudo Minecraft)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: Steve',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'       => Utilisateur::class,
            'csrf_protection'  => true,
            'csrf_field_name'  => '_token',
            'csrf_token_id'    => 'utilisateur_edit',
        ]);
    }
}
