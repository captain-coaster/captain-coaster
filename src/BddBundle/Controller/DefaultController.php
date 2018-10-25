<?php

namespace BddBundle\Controller;

use BddBundle\Entity\User;
use BddBundle\Form\Type\ContactType;
use BddBundle\Service\DiscordService;
use BddBundle\Service\StatService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class DefaultController
 * @package BddBundle\Controller
 */
class DefaultController extends Controller
{
    /**
     * Root of application, redirect to browser language if defined
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function rootAction(Request $request)
    {
        $locale = $request->getPreferredLanguage($this->getParameter('app.locales.array'));

        return $this->redirectToRoute('bdd_index', ['_locale' => $locale], 301);
    }

    /**
     * Index of application
     *
     * @param Request $request
     * @param StatService $statService
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     * @Route("/", name="bdd_index")
     * @Method({"GET"})
     */
    public function indexAction(Request $request, StatService $statService)
    {
        $ratingFeed = $this
            ->getDoctrine()
            ->getRepository('BddBundle:RiddenCoaster')
            ->findBy([], ['updatedAt' => 'DESC'], 6);

        $image = $this
            ->getDoctrine()
            ->getRepository('BddBundle:Image')
            ->findLatestImage();

        $stats = $statService->getIndexStats();

        $reviews = $this
            ->getDoctrine()
            ->getRepository('BddBundle:RiddenCoaster')
            ->getLatestReviewsByLocale($request->getLocale());

        $missingImages = [];
        if ($user = $this->getUser() instanceof User) {
            $missingImages = $this
                ->getDoctrine()
                ->getRepository('BddBundle:RiddenCoaster')
                ->findCoastersWithNoImage($this->getUser());
        }

        return $this->render(
            'BddBundle:Default:index.html.twig',
            [
                'ratingFeed' => $ratingFeed,
                'image' => $image,
                'stats' => $stats,
                'reviews' => $reviews,
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
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @Route("/contact", name="default_contact")
     * @Method({"GET", "POST"})
     */
    public function contactAction(Request $request, \Swift_Mailer $mailer, DiscordService $discord)
    {
        /** @var Form $form */
        $form = $this->createForm(ContactType::class, null);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $message = (new \Swift_Message($this->get('translator')->trans('contact.email.title')))
                ->setFrom($this->getParameter('mail_from'), $this->getParameter('mail_from_name'))
                ->setTo($this->getParameter('mail_to'))
                ->setReplyTo($data['email'])
                ->setBody(
                    $this->renderView(
                        '@Bdd/Default/contact_mail.txt.twig',
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
                $this->get('translator')->trans('contact.flash.success', ['%name%' => $data['name']])
            );

            return $this->redirectToRoute('default_contact');
        }

        return $this->render(
            'BddBundle:Default:contact.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/terms", name="default_terms")
     * @Method({"GET"})
     */
    public function termsAction()
    {
        return $this->render(
            'BddBundle:Default:terms.html.twig'
        );
    }
}
