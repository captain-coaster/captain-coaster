<?php

declare(strict_types=1);

namespace App\Tests\Form\Type;

use App\Enum\ContactTopic;
use App\Form\Type\ContactType;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

final class ContactTypeTest extends TypeTestCase
{
    public function testAGuestSubmitsATopicAndTheirIdentity(): void
    {
        $form = $this->form();

        $form->submit(['topic' => 'missing', 'message' => 'Voltron Nevera, Europa-Park.', 'name' => 'Ana', 'email' => 'ana@site.test']);

        $this->assertTrue($form->isValid());
        $this->assertSame(
            ['name' => 'Ana', 'email' => 'ana@site.test', 'topic' => ContactTopic::Missing, 'subject' => null, 'message' => 'Voltron Nevera, Europa-Park.'],
            $form->getData(),
        );
    }

    public function testASignedInRiderHasNoIdentityFields(): void
    {
        $form = $this->form(isLoggedIn: true);

        $this->assertFalse($form->has('name'));
        $this->assertFalse($form->has('email'));

        $form->submit(['topic' => 'other', 'subject' => 'Partnership', 'message' => 'Hello']);

        $this->assertTrue($form->isValid());
        $this->assertSame(['topic' => ContactTopic::Other, 'subject' => 'Partnership', 'message' => 'Hello'], $form->getData());
    }

    public function testTheTopicFromThePageLinkIsPreselected(): void
    {
        $form = $this->form(data: ['topic' => ContactTopic::Data]);

        $this->assertSame('data', $form->createView()['topic']->vars['value']);
    }

    public function testATopicIsRequired(): void
    {
        $form = $this->form(isLoggedIn: true);

        $form->submit(['topic' => '', 'message' => 'Hello']);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('topic')->getErrors());
    }

    public function testAnUnknownTopicIsRejected(): void
    {
        $form = $this->form(isLoggedIn: true);

        $form->submit(['topic' => 'spam', 'message' => 'Hello']);

        $this->assertFalse($form->isValid());
    }

    public function testTheSubjectIsCappedAt80Characters(): void
    {
        $form = $this->form(isLoggedIn: true);

        $form->submit(['topic' => 'other', 'subject' => str_repeat('a', 81), 'message' => 'Hello']);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('subject')->getErrors());
    }

    public function testAGuestNameIsCappedAt100Characters(): void
    {
        $form = $this->form();

        $form->submit(['topic' => 'site', 'message' => 'Hello', 'name' => str_repeat('a', 101)]);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('name')->getErrors());
    }

    /**
     * The form without its Turnstile field, whose validator calls Cloudflare.
     *
     * @param array<string, mixed>|null $data
     *
     * @return FormInterface<mixed>
     */
    private function form(?array $data = null, bool $isLoggedIn = false): FormInterface
    {
        return $this->factory->create(ContactType::class, $data, ['is_logged_in' => $isLoggedIn])->remove('recaptcha');
    }

    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension([new TurnstileType('key', false)], []),
            new ValidatorExtension(Validation::createValidator()),
        ];
    }
}
