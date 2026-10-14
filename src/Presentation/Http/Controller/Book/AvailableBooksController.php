<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller\Book;

use App\Domain\Book\Entity\Book;
use App\Infrastructure\Persistence\Doctrine\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AvailableBooksController extends AbstractController
{
    #[Route('/api/books/available', name: 'api.available_books', methods: ['GET'], format: 'json')]
    public function __invoke(
        BookRepository $bookRepository,
    ): JsonResponse {
        $books = $bookRepository->findAvailable();

        $response = array_map(fn (Book $book) => [
            'id' => $book->id(),
            'inv_number' => $book->invNumber(),
            'title' => $book->title(),
            'author' => $book->author(),
        ], $books);

        return $this->json($response, Response::HTTP_OK);
    }
}
