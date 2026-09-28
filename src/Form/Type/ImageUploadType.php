<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Image;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<Image>
 */
class ImageUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // constraint file NotBlank only for upload
            ->add('file', FileType::class, [
                'label' => 'image_upload.form.file.label',
                'help' => 'image_upload.form.file.helper',
                'attr' => ['accept' => Image::MIME_TYPE],
                'constraints' => [new NotBlank()],
            ])
            ->add('credit', TextType::class, [
                'label' => 'image_upload.form.credit.label',
                'attr' => ['autocomplete' => 'name'],
            ])
            ->add('watermarked', CheckboxType::class, [
                'label' => 'image_upload.form.watermark.label',
                'help' => 'image_upload.form.watermark.helper',
                'required' => false,
            ])
            ->add('copyrightAgreement', CheckboxType::class, [
                'label' => 'image_upload.form.copyright_agreement.label',
                'mapped' => false,
                'constraints' => [new NotBlank(message: 'image_upload.form.copyright_agreement.required')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Image::class]);
    }
}
