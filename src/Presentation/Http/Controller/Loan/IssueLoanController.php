<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller\Loan;

use App\Application\Loan\Exception\LoanIssueRejectedException;
use App\Application\Loan\IssueLoanHandler;
use App\Infrastructure\Persistence\Doctrine\BookRepository;
use App\Infrastructure\Persistence\Doctrine\ClientRepository;
use App\Presentation\Http\Request\Loan\IssueLoanRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class IssueLoanController extends AbstractController
{
    #[Route('/api/loans', name: 'api.issue_loans', methods: ['POST'], format: 'json')]
    public function __invoke(
        #[MapRequestPayload] IssueLoanRequest $request,
        ClientRepository $clientRepository,
        BookRepository $bookRepository,
        IssueLoanHandler $loanHandler
    ): JsonResponse {
        $client = $clientRepository->find($request->client_id);

        if ($client === null) {
            throw $this->createNotFoundException('Клиент не найден');
        }

        $book = $bookRepository->find($request->book_id);

        if ($book === null) {
            throw $this->createNotFoundException('Книга не найдена');
        }

        $loan = $loanHandler->handle($client, $book);

        return $this->json(['id' => $loan->id()], Response::HTTP_CREATED);
    }
}
