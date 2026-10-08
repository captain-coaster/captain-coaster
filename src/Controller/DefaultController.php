<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Legal pages.
 */
class DefaultController extends BaseController
{
    #[Route(path: '/terms-conditions', name: 'app_terms_conditions', methods: ['GET'])]
    public function privacyPolicy(): Response
    {
        return $this->render('Default/terms_conditions.html.twig');
    }
}
