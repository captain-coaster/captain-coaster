<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\RiddenCoaster;
use App\Entity\TopCoaster;
use App\Entity\User;
use App\Form\Type\ProfileSettingsForm;
use App\Repository\ImageRepository;
use App\Service\ProfilePictureManager;
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
    /** Show my profile. */
    #[Route(path: '/profile', name: 'profile', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(
        StatService $statService,
        ImageRepository $imageRepository
    ): Response {
        $user = $this->getUser();

        return $this->render('Profile/index.html.twig', [
            'user' => $user,
            'stats' => $statService->getUserStats($user),
            'images_counter' => $imageRepository->countUserEnabledImages($user),
        ]);
    }

    /** Show my ratings. */
    #[Route(path: '/profile/ratings/{page}', name: 'profile_ratings', requirements: ['page' => '\d+'], methods: ['GET'])]
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

    /** Get banner data */
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

    /** Show my settings. */
    #[Route(path: '/profile/settings', name: 'profile_settings', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function settings(
        Request $request,
        StatService $statService,
        EntityManagerInterface $em,
        ImageRepository $imageRepository,
        ProfilePictureManager $profilePictureManager,
        TranslatorInterface $translator
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProfileSettingsForm::class, $user, [
            'can_change_name' => $user->canChangeName(),
            'locales' => $this->getParameter('app_locales_array'),
            'translator' => $translator,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Handle profile picture upload
            $profilePictureFile = $form->get('profilePicture')->getData();
            if ($profilePictureFile) {
                $filename = $profilePictureManager->uploadProfilePicture($profilePictureFile, $user);
                if ($filename) {
                    $user->setProfilePicture($filename);
                }
            }

            $this->addFlash('success', $translator->trans('profile.settings.updated_success'));

            $em->persist($user);
            $em->flush();

            return $this->redirectToRoute('profile_settings');
        }

        return $this->render('Profile/settings.html.twig', [
            'form' => $form,
            'user' => $user,
            'canChangeName' => $user->canChangeName(),
            'previewNames' => $user->getNamePreviewFormats(),
            'images_counter' => $imageRepository->countUserEnabledImages($user),
        ]);
    }
}
