<?php

namespace App\Form\Type;

use App\Entity\ListeCoaster;
use App\Form\DataTransformer\CoasterToIdTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ListeCoasterType
 * @package App\Form\Type
 */
class ListeCoasterType extends AbstractType
{
    private $transformer;

    /**
     * ListeCoasterType constructor.
     * @param CoasterToIdTransformer $transformer
     */
    public function __construct(CoasterToIdTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'coaster',
                HiddenType::class,
                [
                    'invalid_message' => 'That is not a valid coaster number',
                ]
            )
            ->add('position', HiddenType::class);

        $builder->get('coaster')->addModelTransformer($this->transformer);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => ListeCoaster::class,
            ]
        );
    }
}
