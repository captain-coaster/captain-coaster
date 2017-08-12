<?php

namespace BddBundle\Controller;

use BddBundle\Form\Type\ContactType;
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
     * Root of application, redirect to /en or /fr
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function rootAction(Request $request)
    {
        $locale = $request->getPreferredLanguage(['en', 'fr']);

        return $this->redirectToRoute('bdd_index', ['_locale' => $locale], 301);
    }

    /**
     * Index of application
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/", name="bdd_index")
     * @Method({"GET"})
     */
    public function indexAction(Request $request)
    {
        $goodCoasters = [1985,2197,202,2183,2169,1572,1980,59,2028,2210,2130,196,2219,85,2247,795,2165,2000,2190,62,2192,2138];
        $coasterId = $goodCoasters[array_rand($goodCoasters)];

        $ratingFeed = $this
            ->getDoctrine()
            ->getRepository('BddBundle:RiddenCoaster')
            ->findBy([], ['updatedAt' => 'DESC'], 6);

        $images = $this->get('BddBundle\Service\ImageService')
            ->getCoasterImagesUrl($coasterId);

        $coaster = $this
            ->getDoctrine()
            ->getRepository('BddBundle:Coaster')
            ->findOneBy(['id' => $coasterId]);

        $stats = $this
            ->getDoctrine()
            ->getRepository('BddBundle:BuiltCoaster')
            ->getStats();

        $ratingNumber = $this
            ->getDoctrine()
            ->getRepository('BddBundle:RiddenCoaster')
            ->countAll();

        $date = new \DateTime();
        $date->sub(new \DateInterval('P1D'));
        $newRatingNumber = $this
            ->getDoctrine()
            ->getRepository('BddBundle:RiddenCoaster')
            ->countNew($date);

        $reviews = $this
            ->getDoctrine()
            ->getRepository('BddBundle:RiddenCoaster')
            ->getLatestReviewsByLocale($request->getLocale());

        return $this->render(
            'BddBundle:Default:index.html.twig',
            [
                'ratingFeed' => $ratingFeed,
                'images' => $images,
                'coaster' => $coaster,
                'stats' => $stats,
                'ratingNumber' => $ratingNumber,
                'newRatingNumber' => $newRatingNumber,
                'reviews' => $reviews,
            ]
        );
    }

    /**
     * Contact form
     *
     * @param Request $request
     * @param \Swift_Mailer $mailer
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @Route("/contact", name="default_contact")
     * @Method({"GET", "POST"})
     */
    public function contactAction(Request $request, \Swift_Mailer $mailer)
    {
        /** @var Form $form */
        $form = $this->createForm(ContactType::class, null);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $message = (new \Swift_Message($this->get('translator')->trans('contact.email.title')))
                ->setFrom($this->getParameter('mail_from'))
                ->setTo($this->getParameter('mail_to'))
                ->setReplyTo($data['email'])
                ->setBody($data['name']."\n".$data['message']);
            $mailer->send($message);

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
            'BddBundle:Default:terms.html.twig');
    }
}
