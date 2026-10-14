<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller\Client;

use App\Domain\Loan\Entity\Loan;
use App\Infrastructure\Persistence\Doctrine\ClientRepository;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientOpenLoansController extends AbstractController
{
    #[Route('/api/clients/{client_id}/loans', name: 'api.client_open_loans', methods: ['GET'], format: 'json')]
    public function __invoke(
        int $client_id,
        ClientRepository $clientRepository,
        LoanRepository $loanRepository,
    ): JsonResponse {
        $client = $clientRepository->find($client_id);

        if ($client === null) {
            throw $this->createNotFoundException('Клиент не найден');
        }

        $loans = $loanRepository->findOpenLoansByClient($client);

        $response = array_map(fn (Loan $loan) => [
            'loan_id' => $loan->id(),
            'book_id' => $loan->book()->id(),
            'inv_number' => $loan->book()->invNumber(),
            'title' => $loan->book()->title(),
            'author' => $loan->book()->author(),
            'loan_at' =>$loan->loan_at()->format(DATE_ATOM),
            'planned_return_at' => $loan->planned_return_at()->format(DATE_ATOM),
        ], $loans);

        return $this->json($response, Response::HTTP_OK);
    }
}
