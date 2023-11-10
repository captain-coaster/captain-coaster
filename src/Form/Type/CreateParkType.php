<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Country;
use App\Entity\Park;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreateParkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'name',
                TextType::class,
                [
                    'required' => true,
                    'label' => 'missing.step1.form.park_name',
                ]
            )
            ->add(
                'country',
                EntityType::class,
                [
                    'required' => true,
                    'class' => Country::class,
                    'choice_translation_domain' => 'database',
                    'label' => 'missing.step1.form.country',
                    'query_builder' => fn (EntityRepository $er) => $this->repository->createQueryBuilder('c')
                        ->orderBy('c.name', 'ASC'),
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => Park::class]);
    }
}
