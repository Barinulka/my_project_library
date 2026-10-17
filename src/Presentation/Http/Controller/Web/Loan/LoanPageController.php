<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller\Web\Loan;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoanPageController extends AbstractController
{
    #[Route('/loans', name: 'web.loans', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('loan/index.html.twig');
    }
}
