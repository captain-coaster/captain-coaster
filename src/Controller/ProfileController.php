<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\RiddenCoaster;
use App\Entity\TopCoaster;
use App\Entity\User;
use App\Form\Type\ProfileType;
use App\Repository\ImageRepository;
use App\Service\StatService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProfileController extends BaseController
{
    #[Route(path: '/me', name: 'me', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function meAction(Request $request, StatService $statService, EntityManagerInterface $em, ImageRepository $imageRepository): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(ProfileType::class, $user, [
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastName(),
            'locales' => $this->getParameter('app_locales_array'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($user);
            $em->flush();

            return $this->redirectToRoute('me');
        }

        return $this->render('Profile/me.html.twig', [
            'user' => $this->getUser(),
            'form' => $form,
            'stats' => $statService->getUserStats($user),
            'images_counter' => $imageRepository->countUserEnabledImages($user),
        ]);
    }

    /** Show my ratings. */
    #[Route(path: '/me/ratings/{page}', name: 'me_ratings', requirements: ['page' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function ratingsAction(EntityManagerInterface $em, PaginatorInterface $paginator, int $page = 1): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $query = $em
            ->getRepository(RiddenCoaster::class)
            ->getUserRatings($user);

        try {
            $ratings = $paginator->paginate($query, $page, 30, [
                'defaultSortFieldName' => 'r.updatedAt',
                'defaultSortDirection' => 'desc',
            ]);
        } catch (\UnexpectedValueException) {
            throw new BadRequestHttpException();
        }

        return $this->render('Profile/ratings.html.twig', [
            'ratings' => $ratings,
        ]);
    }

    #[Route(path: '/banner/data/{id}', name: 'banner_data', methods: ['GET'])]
    public function getBannerData(User $user, TranslatorInterface $translator): Response
    {
        $top = [];
        $i = 1;
        /** @var TopCoaster $topCoaster */
        foreach ($user->getMainTop()->getTopCoasters()->slice(0, 3) as $topCoaster) {
            $top[] = $i.' - '.$topCoaster->getCoaster()->getName();
            ++$i;
        }

        return new JsonResponse([
            'count' => $translator->trans('banner.coasters', ['count' => $user->getRatings()->count()]),
            'top' => $top,
        ]);
    }
}
