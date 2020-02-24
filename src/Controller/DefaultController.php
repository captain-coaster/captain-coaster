<?php

namespace App\Controller;

use App\Entity\Image;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Form\Type\ContactType;
use App\Service\DiscordService;
use App\Service\StatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class DefaultController
 * @package App\Controller
 */
class DefaultController extends AbstractController
{
    /**
     * Root of application, redirect to browser language if defined
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function rootAction(Request $request)
    {
        $locale = $request->getPreferredLanguage($this->getParameter('app_locales_array'));

        return $this->redirectToRoute('bdd_index', ['_locale' => $locale], 301);
    }

    /**
     * Index of application
     *
     * @param Request $request
     * @param StatService $statService
     * @param EntityManagerInterface $em
     * @return Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     * @Route("/", name="bdd_index", methods={"GET"})
     */
    public function indexAction(Request $request, StatService $statService, EntityManagerInterface $em)
    {
        $missingImages = [];
        if (($user = $this->getUser()) instanceof User) {
            $missingImages = $em->getRepository(RiddenCoaster::class)->findCoastersWithNoImage($user);
        }

        return $this->render(
            'Default/index.html.twig',
            [
                'ratingFeed' => $em->getRepository(RiddenCoaster::class)->findBy([], ['updatedAt' => 'DESC'], 6),
                'image' => $em->getRepository(Image::class)->findLatestImage(),
                'stats' => $statService->getIndexStats(),
                'reviews' => $em->getRepository(RiddenCoaster::class)->getLatestReviewsByLocale($request->getLocale()),
                'missingImages' => $missingImages,
            ]
        );
    }

    /**
     * Contact form
     *
     * @param Request $request
     * @param \Swift_Mailer $mailer
     * @param DiscordService $discord
     * @param TranslatorInterface $translator
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     * @Route("/contact", name="default_contact", methods={"GET", "POST"})
     */
    public function contactAction(
        Request $request,
        \Swift_Mailer $mailer,
        DiscordService $discord,
        TranslatorInterface $translator
    ) {
        /** @var Form $form */
        $form = $this->createForm(ContactType::class, null);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $message = (new \Swift_Message($translator->trans('contact.email.title')))
                ->setFrom($this->getParameter('app_mail_from'), $this->getParameter('app_mail_from_name'))
                ->setTo($this->getParameter('app_contact_mail_to'))
                ->setReplyTo($data['email'])
                ->setBody(
                    $this->renderView(
                        'Default/contact_mail.txt.twig',
                        [
                            'name' => $data['name'],
                            'message' => $data['message'],
                        ]
                    )
                );
            $mailer->send($message);

            // send notification
            $discord->notify('We just received new message from '.$data['name']."\n\n".$data['message']);

            $this->addFlash(
                'success',
                $translator->trans('contact.flash.success', ['%name%' => $data['name']])
            );

            return $this->redirectToRoute('default_contact');
        }

        return $this->render('Default/contact.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @return Response
     * @Route("/privacy-policy", name="default_privacy_policy", methods={"GET"})
     */
    public function privacyPolicy()
    {
        return $this->render('Default/policy.html.twig');
    }
}
