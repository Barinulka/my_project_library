<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller\Web\Book;

use App\Domain\Book\Entity\Book;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BookPageController extends AbstractController
{
    #[Route('/books', name: 'web.books', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('book/index.html.twig');
    }
}
