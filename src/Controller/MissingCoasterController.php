<?php

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Park;
use App\Form\Type\ChooseParkType;
use App\Form\Type\CreateCoasterType;
use App\Form\Type\CreateParkType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class MissingCoasterController
 * @package App\Controller
 * @Route("/missing-coaster")
 */
class MissingCoasterController extends AbstractController
{
    /**
     * Starts missing coaster procedure
     *
     * @Route("/start", name="missingcoaster_start", methods={"GET", "POST"})
     *
     * @param Request $request
     * @param EntityManagerInterface $em
     * @return RedirectResponse|Response
     */
    public function start(Request $request, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('ROLE_PREVIEW_FEATURE');

        $chooseForm = $this->createForm(ChooseParkType::class);
        $chooseForm->handleRequest($request);

        if ($chooseForm->isSubmitted() && $chooseForm->isValid()) {
            return $this->redirectToRoute(
                'missingcoaster_add',
                ['id' => $chooseForm->get('existingPark')->getData()->getId()]
            );
        }

        $park = new Park();
        $form = $this->createForm(CreateParkType::class, $park);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $park->setEnabled(false);
            $em->persist($park);
            $em->flush();

            return $this->redirectToRoute('missingcoaster_add', ['id' => $park->getId()]);
        }

        return $this->render(
            'MissingCoaster/start.html.twig',
            [
                'chooseForm' => $chooseForm->createView(),
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * Main form to add a missing coaster
     *
     * @Route("/park/{id}/add", name="missingcoaster_add", methods={"GET", "POST"})
     *
     * @param Request $request
     * @param Park $park
     * @param EntityManagerInterface $em
     * @return RedirectResponse|Response
     */
    public function addCoaster(Request $request, Park $park, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('ROLE_PREVIEW_FEATURE');

        $coaster = new Coaster();
        $coaster->setPark($park);

        $form = $this->createForm(CreateCoasterType::class, $coaster);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $coaster->setEnabled(false);
            $em->persist($coaster);
            $em->flush();

            return $this->redirectToRoute('missingcoaster_success', ['coaster' => $coaster]);
        }

        return $this->render('MissingCoaster/create.html.twig', ['form' => $form->createView()]);
    }

    /**
     * Recap message for the user
     *
     * @Route("/success", name="missingcoaster_success", methods={"GET"})
     *
     * @param Coaster $coaster
     * @return Response
     */
    public function success(Coaster $coaster)
    {
        $this->denyAccessUnlessGranted('ROLE_PREVIEW_FEATURE');

        return $this->render('MissingCoaster/success.html.twig', ['coaster' => $coaster]);
    }
}
