<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller\Web\Client;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientPageController extends AbstractController
{
    #[Route('/clients', name: 'web.clients', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('client/index.html.twig');
    }
}
