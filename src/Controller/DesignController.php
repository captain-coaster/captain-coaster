<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The design system's reference page: DESIGN.md rendered with the real tokens and components. Dev only.
 */
class DesignController extends BaseController
{
    #[Route(path: '/design', name: 'design_index', methods: ['GET'], env: 'dev')]
    public function index(): Response
    {
        return $this->render('Design/index.html.twig');
    }
}
