<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// types de champs
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class UtilisateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Édition : seulement email + pseudo
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('pseudo', TextType::class, [
                'label' => 'Pseudo',
                'required' => false,
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
