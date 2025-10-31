<?php

namespace App\Form\Profile;

use App\Enum\TransactionType;
use App\Game\GameRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionFilterType extends AbstractType
{
    public function __construct(private GameRegistry $registry) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $gameKeys = $this->registry->keys();
        $gameChoices = array_combine($gameKeys, $gameKeys) ?: [];

        $typeChoices = [];
        foreach (TransactionType::cases() as $case) {
            $typeChoices[ucfirst($case->value)] = $case;
        }

        $builder
            ->setMethod('GET')
            ->add('gameKey', ChoiceType::class, [
                'label' => 'Jeu',
                'required' => false,
                'placeholder' => '— Tous —',
                'choices' => $gameChoices,
            ])
            ->add('types', ChoiceType::class, [
                'label' => 'Types',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => $typeChoices,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }
}
