<?php declare(strict_types=1);

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Image;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class ImageUploadType.
 */
class ImageUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'file',
                FileType::class,
                // constraint file NotBlank only for upload
                ['label' => 'image_upload.form.file.label', 'required' => true, 'constraints' => [new NotBlank()]]
            )
            ->add(
                'watermarked',
                CheckboxType::class,
                ['label' => 'image_upload.form.watermark.label', 'required' => false]
            )
            ->add(
                'credit',
                TextType::class,
                ['label' => 'image_upload.form.credit.label', 'required' => true]
            )
            ->add(
                'upload',
                SubmitType::class,
                ['label' => 'image_upload.form.upload']
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Image::class]);
    }
}
