<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MainPageController extends AbstractController
{
    #[Route('/', name: 'web.index', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('main/index.html.twig');
    }
}
