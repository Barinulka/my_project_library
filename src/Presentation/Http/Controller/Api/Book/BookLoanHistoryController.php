<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller\Api\Book;

use App\Domain\Loan\Entity\Loan;
use App\Infrastructure\Persistence\Doctrine\BookRepository;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BookLoanHistoryController extends AbstractController
{
    #[Route('/api/books/{book_id}/loans', name: 'api.books_loans_history', methods: ['GET'], format: 'json')]
    public function __invoke(
        int $book_id,
        BookRepository $bookRepository,
        LoanRepository $loanRepository,
    ): JsonResponse {
        $book = $bookRepository->find($book_id);

        if ($book === null) {
            throw $this->createNotFoundException('Книга не найдена');
        }

        $loans = $loanRepository->findHistoryByBook($book);

        $response = array_map(fn (Loan $loan) => [
            'loan_id' => $loan->id(),
            'client_id' => $loan->client()->id(),
            'client_name' => $loan->client()->name(),
            'book_id' => $loan->book()->id(),
            'inv_number' => $loan->book()->invNumber(),
            'title' => $loan->book()->title(),
            'author' => $loan->book()->author(),
            'loan_at' =>$loan->loan_at()->format(DATE_ATOM),
            'planned_return_at' => $loan->planned_return_at()->format(DATE_ATOM),
            'returned_at' => $loan->returned_at()?->format(DATE_ATOM),
        ], $loans);

        return $this->json($response, Response::HTTP_OK);
    }
}
