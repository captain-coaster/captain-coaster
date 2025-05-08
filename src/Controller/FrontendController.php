<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontendController extends AbstractController
{
    #[Route('/frontend', name: 'app_frontend')]
    public function index(): Response
    {
        return $this->render('frontend/index.html.twig', [
            'controller_name' => 'FrontendController',
        ]);
    }
}
