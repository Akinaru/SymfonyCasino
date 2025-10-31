<?php

namespace App\Form;

use App\Entity\MarketItem;
use App\Enum\ItemType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MarketItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Type d’objet',
                'choices' => array_combine(
                    array_map(fn($c)=>$c->label(), ItemType::cases()),
                    ItemType::cases()
                ),
                'choice_value' => fn (?ItemType $t) => $t?->value,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom affiché',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('price', IntegerType::class, [
                'label' => 'Prix',
                'attr' => ['class' => 'form-control', 'min' => 0],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MarketItem::class]);
    }
}
