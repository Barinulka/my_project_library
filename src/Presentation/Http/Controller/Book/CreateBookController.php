<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller\Book;

use App\Application\Book\CreateBookHandler;
use App\Application\Book\Exception\BookInvNumberAlreadyExistsException;
use App\Presentation\Http\Request\Book\CreateBookRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class CreateBookController extends AbstractController
{
    #[Route('/api/books', name: 'api.books_create', methods: ['POST'], format: 'json')]
    public function __invoke(
        #[MapRequestPayload] CreateBookRequest $request,
        CreateBookHandler $handler,
    ): JsonResponse {
        $book = $handler->handle($request->inv_number, $request->title, $request->author);

        return $this->json(['id' => $book->id()], Response::HTTP_CREATED);
    }
}
