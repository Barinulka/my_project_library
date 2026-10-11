<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Loan\Exception\LoanReturnRejectedException;
use App\Application\Loan\ReturnLoanHandler;
use App\Infrastructure\Persistence\Doctrine\BookRepository;
use App\Presentation\Http\Request\ReturnLoanRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class ReturnLoanController extends AbstractController
{
    #[Route('/api/return', name: 'api.return_loan', methods: ['POST'], format: 'json')]
    public function __invoke(
        #[MapRequestPayload] ReturnLoanRequest $request,
        BookRepository $bookRepository,
        ReturnLoanHandler $loanHandler,
    ): JsonResponse
    {
        $book = $bookRepository->find($request->book_id);

        if ($book === null) {
            throw $this->createNotFoundException('Книга не найдена');
        }

        try {
            $loan = $loanHandler->handle($book);
        } catch (LoanReturnRejectedException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['id' => $loan->id(), 'message' => 'Книга возвращена'], Response::HTTP_OK);
    }
}
