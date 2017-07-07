<?php

namespace BddBundle\Controller;

use BddBundle\Entity\TopCoaster;
use BddBundle\Entity\User;
use BddBundle\Form\Type\TopTenType;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TopCoasterController
 * @package BddBundle\Controller
 */
class TopCoasterController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse|Response
     *
     * @Route("/top/coasters/edit", name="bdd_top_edit")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function editAction(Request $request)
    {
        /** @var User $user */
        $user = $this->getUser();

        $originalTopCoasters = new ArrayCollection();
        foreach ($user->getTopCoasters() as $topCoaster) {
            $originalTopCoasters->add($topCoaster);
        }

        /** @var Form $form */
        $form = $this->createForm(TopTenType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            foreach ($originalTopCoasters as $topCoaster) {
                if (false === $user->getTopCoasters()->contains($topCoaster)) {
                    $em->remove($topCoaster);
                }
            }

            /** @var TopCoaster $topCoaster */
            foreach ($user->getTopCoasters() as $topCoaster) {
                $topCoaster->setUser($user);
            }

            $em->persist($user);
            $em->flush();

            return $this->redirectToRoute('bdd_top_edit');
        }

        return $this->render(
            'BddBundle:TopTen:edit.html.twig',
            array(
                'form' => $form->createView(),
            )
        );
    }
}