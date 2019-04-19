<?php

namespace App\Controller;

use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Form\Type\ProfileType;
use App\Service\BannerMaker;
use App\Service\StatService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    /**
     * @param Request $request
     * @param StatService $statService
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/me", name="me", methods={"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function meAction(Request $request, StatService $statService)
    {
        $user = $this->getUser();

        /** @var Form $form */
        $form = $this->createForm(
            ProfileType::class,
            $user,
            [
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastName(),
                'locales' => $this->getParameter('app_locales_array'),
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            return $this->redirectToRoute('me');
        }

        return $this->render(
            'Profile/me.html.twig',
            [
                'user' => $this->getUser(),
                'form' => $form->createView(),
                'stats' => $statService->getUserStats($user),
            ]
        );
    }

    /**
     * @param EntityManagerInterface $em
     * @param PaginatorInterface $paginator
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/me/ratings/{page}", name="me_ratings", requirements={"page" = "\d+"}, methods={"GET"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function ratingsAction(EntityManagerInterface $em, PaginatorInterface $paginator, $page = 1)
    {
        /** @var User $user */
        $user = $this->getUser();

        $query = $em
            ->getRepository(RiddenCoaster::class)
            ->getUserRatings($user);

        $ratings = $paginator->paginate(
            $query,
            $page,
            30,
            [
                'wrap-queries' => true,
                'defaultSortFieldName' => 'r.updatedAt',
                'defaultSortDirection' => 'desc',
            ]
        );

        return $this->render(
            'Profile/ratings.html.twig',
            [
                'ratings' => $ratings,
            ]
        );
    }

    /**
     * @Route(
     *     "/banner",
     *     name="profile_banner",
     *     methods={"GET"},
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @Security("is_granted('ROLE_USER')")
     *
     * @param BannerMaker $bannerMaker
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getBanner(BannerMaker $bannerMaker)
    {
        $user = $this->getUser();
        $bannerMaker->makeBanner($user);

        return $this->render(
            'Profile/banner.html.twig',
            ['user' => $user]
        );
    }
}
