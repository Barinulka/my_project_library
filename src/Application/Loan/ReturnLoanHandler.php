<?php

declare(strict_types=1);

namespace App\Application\Loan;

use App\Domain\Book\Entity\Book;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;

class ReturnLoanHandler
{
    public function __construct(
        private LoanRepository $loanRepository,
    ) {
    }

    public function handle(Book $book): void
    {
        $loan = $this->loanRepository->findOpenedLoan($book);

        if ($loan === null) {
            throw new \RuntimeException('Не удалось найти открытой выдачи');
        }

        $loan->returned();

        $this->loanRepository->save($loan);
    }
}
