<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller\Loan;

use App\Domain\Loan\Entity\Loan;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OverdueLoansController extends AbstractController
{
    #[Route('/api/loans/overdue', name: 'api.loans.overdue', methods: ['GET'], format: 'json')]
    public function __invoke(
        LoanRepository $loanRepository,
    ): JsonResponse {
        $now = new DateTimeImmutable('now');

        $overdueLoans = $loanRepository->findOverdueLoans($now);

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
        ], $overdueLoans);

        return $this->json($response, Response::HTTP_OK);
    }
}
