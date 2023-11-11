<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\TopCoaster;
use App\Form\DataTransformer\CoasterToIdTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TopCoasterType extends AbstractType
{
    public function __construct(private readonly CoasterToIdTransformer $transformer)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('coaster', HiddenType::class, [
                'invalid_message' => 'That is not a valid coaster number',
            ])
            ->add('position', HiddenType::class);

        $builder->get('coaster')->addModelTransformer($this->transformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TopCoaster::class,
        ]);
    }
}
