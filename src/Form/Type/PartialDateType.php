<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\DTO\PartialDate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A date typed with the precision that is known: `2026`, `2026-05` or `2026-05-17`.
 *
 * @extends AbstractType<PartialDate|null>
 */
class PartialDateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?PartialDate $date): string => (string) $date,
            static function (?string $text): ?PartialDate {
                if (null === $text || '' === trim($text)) {
                    return null;
                }

                try {
                    return PartialDate::fromString($text);
                } catch (\InvalidArgumentException $e) {
                    throw new TransformationFailedException($e->getMessage(), previous: $e);
                }
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // The model is a PartialDate, the view a string.
            'data_class' => null,
            'invalid_message' => 'Use YYYY, YYYY-MM or YYYY-MM-DD.',
            'help' => 'Only what is known: 2026, 2026-05 or 2026-05-17.',
            'attr' => ['placeholder' => 'YYYY-MM-DD', 'inputmode' => 'numeric'],
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
