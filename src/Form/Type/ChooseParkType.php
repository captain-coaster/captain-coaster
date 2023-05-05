<?php

namespace App\Form\Type;

use App\Entity\Park;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class ChooseParkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'existingPark',
                EntityType::class,
                [
                    'required' => true,
                    'class' => Park::class,
                    'mapped' => false,
                    'query_builder' => fn(EntityRepository $er) => $this->repository->createQueryBuilder('p')
                        ->orderBy('p.name', 'ASC'),
                ]
            )
            ->add(
                'chooseParkSubmit',
                SubmitType::class,
                [
                    'label' => 'missing.step1.form.choose_btn',
                ]
            );
    }
}
