<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Form\Type\ProfileType;
use App\Service\BannerMaker;
use App\Service\StatService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    /**
     * @return Response
     */
    #[Route(path: '/me', name: 'me', methods: ['GET', 'POST'])]
    public function meAction(Request $request, StatService $statService)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

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
                'form' => $form,
                'stats' => $statService->getUserStats($user),
            ]
        );
    }

    /**
     * Show my ratings.
     */
    #[Route(path: '/me/ratings/{page}', name: 'me_ratings', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function ratingsAction(EntityManagerInterface $em, PaginatorInterface $paginator, int $page = 1): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        $query = $em
            ->getRepository(RiddenCoaster::class)
            ->getUserRatings($user);

        try {
            $ratings = $paginator->paginate(
                $query,
                $page,
                30,
                [
                    'defaultSortFieldName' => 'r.updatedAt',
                    'defaultSortDirection' => 'desc',
                ]
            );
        } catch (\UnexpectedValueException) {
            throw new BadRequestHttpException();
        }

        return $this->render(
            'Profile/ratings.html.twig',
            [
                'ratings' => $ratings,
            ]
        );
    }

    #[Route(path: '/banner', name: 'profile_banner', methods: ['GET'], options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
    public function getBanner(BannerMaker $bannerMaker): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        $bannerMaker->makeBanner($user);

        return $this->render(
            'Profile/banner.html.twig',
            ['user' => $user]
        );
    }
}
