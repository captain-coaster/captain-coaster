<?php

declare(strict_types=1);

namespace App\Tests\Form\Type;

use App\Entity\User;
use App\Form\Type\RegistrationFormType;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegistrationFormTypeTest extends TypeTestCase
{
    /** An empty or whitespace-only field used to reach User's string setters as null and throw. */
    public function testBlankFieldsAreSubmittedAsEmptyStrings(): void
    {
        $user = new User();
        $form = $this->factory->create(RegistrationFormType::class, $user);

        $form->submit(['email' => '', 'firstName' => '   ', 'lastName' => '']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('', $user->getFirstName());
        $this->assertSame('', $user->getEmail());
    }

    protected function getExtensions(): array
    {
        // Only the `constraints` option is needed here, not the validation itself
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());
        $validator->method('getMetadataFor')->willReturn(new ClassMetadata(Form::class));

        return [
            new PreloadedExtension([new TurnstileType('key', false)], []),
            new ValidatorExtension($validator),
        ];
    }
}
