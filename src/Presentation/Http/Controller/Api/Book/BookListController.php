<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller\Api\Book;

use App\Infrastructure\Persistence\Doctrine\BookRepository;
use App\Presentation\Http\Response\Book\BookResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BookListController extends AbstractController
{
    #[Route('/api/books', name: 'api.book_list', methods: ['GET'], format: 'json')]
    public function __invoke(
        BookRepository $repository,
    ): JsonResponse {
        $books = $repository->getList();

        $response = array_map(fn (array $book) => new BookResponse(
            id: $book['id'],
            inv_number: $book['inv_number'],
            title: $book['title'],
            author: $book['author'],
            available: (bool) $book['available'],
        ), $books);

        return $this->json($response, Response::HTTP_OK);
    }
}
