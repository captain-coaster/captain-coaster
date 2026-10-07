<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\Type\ContactType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Contact and legal pages.
 */
class DefaultController extends BaseController
{
    /**
     * Contact form.
     *
     * @throws TransportExceptionInterface
     */
    #[Route(path: '/contact', name: 'default_contact', methods: ['GET', 'POST'])]
    public function contactAction(Request $request, MailerInterface $mailer, ChatterInterface $chatter, TranslatorInterface $translator): RedirectResponse|Response
    {
        $user = $this->getUser();

        /** @var Form $form */
        $form = $this->createForm(ContactType::class, null, ['is_logged_in' => (bool) $user]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, message: string, email?: string|null} $formData */
            $formData = $user
                ? ['name' => $user->getDisplayName(), 'email' => $user->getEmail()] + $form->getData()
                : $form->getData();

            /** @var string $contactMailTo */
            $contactMailTo = $this->getParameter('app_contact_mail_to');

            $message = new Email()
                ->to($contactMailTo)
                ->subject($translator->trans('contact.email.title'))
                ->html($this->renderView('Default/contact_mail.txt.twig', [
                    'name' => $formData['name'],
                    'message' => $formData['message'],
                    'isLoggedIn' => (bool) $user,
                    'email' => $formData['email'] ?? null,
                ]));

            if (!empty($formData['email'])) {
                $message->replyTo($formData['email']);
            }

            $mailer->send($message);

            // send notification
            $chatter->send(new ChatMessage('We just received new message from '.$formData['name']."\n\n".$formData['message'])->transport('discord_notif'));

            $this->addFlash('success', $translator->trans('contact.flash.success', ['%name%' => $formData['name']]));

            return $this->redirectToRoute('default_contact');
        }

        return $this->render('Default/contact.html.twig', [
            'form' => $form,
            'isLoggedIn' => (bool) $user,
        ]);
    }

    #[Route(path: '/terms-conditions', name: 'app_terms_conditions', methods: ['GET'])]
    public function privacyPolicy(): Response
    {
        return $this->render('Default/terms_conditions.html.twig');
    }
}
