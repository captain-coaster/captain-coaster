<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\ContactTopic;
use App\Form\Type\ContactType;
use App\Repository\CoasterRepository;
use App\Repository\ParkRepository;
use App\Service\Contact\ContactMessage;
use App\Service\Contact\ContactNotifier;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactController extends BaseController
{
    /** Contact form. `topic`, `coaster`, `park` and `q` in the query string say what the rider comes to report. */
    #[Route(path: '/contact', name: 'contact_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ContactNotifier $notifier,
        CoasterRepository $coasterRepository,
        ParkRepository $parkRepository,
        TranslatorInterface $translator,
    ): RedirectResponse|Response {
        $user = $this->getUser();
        $coaster = $request->query->has('coaster') ? $coasterRepository->findOneBy(['id' => $request->query->getInt('coaster'), 'enabled' => true]) : null;
        $park = $request->query->has('park') ? $parkRepository->findOneBy(['id' => $request->query->getInt('park'), 'enabled' => true]) : null;
        $searchQuery = mb_substr(trim($request->query->getString('q')), 0, 100);

        /** @var Form $form */
        $form = $this->createForm(
            ContactType::class,
            ['topic' => ContactTopic::tryFrom($request->query->getString('topic'))],
            ['is_logged_in' => (bool) $user],
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{topic: ContactTopic, subject: ?string, message: string, name?: string, email?: ?string} $data */
            $data = $form->getData();
            $name = $user ? $user->getDisplayName() : $data['name'] ?? '';

            $notifier->send(new ContactMessage(
                topic: $data['topic'],
                message: $data['message'],
                name: $name,
                email: $user ? $user->getEmail() : ($data['email'] ?? null),
                locale: $request->getLocale(),
                subject: ContactTopic::Other === $data['topic'] ? $data['subject'] : null,
                user: $user,
                coaster: $coaster,
                park: $park,
                searchQuery: '' !== $searchQuery ? $searchQuery : null,
            ));

            $this->addFlash('success', $translator->trans('contact.flash.success', ['%name%' => $name]));

            return $this->redirectToRoute('contact_index');
        }

        return $this->render('Contact/index.html.twig', [
            'form' => $form,
            'isLoggedIn' => (bool) $user,
            'coaster' => $coaster,
            'park' => $park,
            'searchQuery' => $searchQuery,
        ]);
    }
}
