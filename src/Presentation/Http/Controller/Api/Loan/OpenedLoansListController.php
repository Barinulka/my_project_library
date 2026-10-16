<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller\Api\Loan;

use App\Domain\Loan\Entity\Loan;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;
use App\Presentation\Http\Response\Loan\LoanResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OpenedLoansListController extends AbstractController
{
    #[Route('/api/loans', name: 'api.loans_list', methods: ['GET'], format: 'json')]
    public function __invoke(
        LoanRepository $repository,
    ): JsonResponse {
        $loans = $repository->getOpenedLoansList();

        $now = new \DateTimeImmutable('now');

        $response = array_map(fn(Loan $loan) => new LoanResponse(
            loan_id: $loan->id(),
            book_id: $loan->book()->id(),
            client_id: $loan->client()->id(),
            client_name: $loan->client()->name(),
            inv_number: $loan->book()->invNumber(),
            title: $loan->book()->title(),
            loan_at: $loan->loan_at()->format(DATE_ATOM),
            planned_return_at: $loan->planned_return_at()->format(DATE_ATOM),
            overdue: $loan->planned_return_at() < $now,
        ), $loans);

        return $this->json($response, Response::HTTP_OK);
    }
}
