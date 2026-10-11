<?php

declare(strict_types=1);

namespace App\Application\Loan;

use App\Application\Loan\Exception\LoanReturnRejectedException;
use App\Domain\Book\Entity\Book;
use App\Domain\Loan\Entity\Loan;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;

class ReturnLoanHandler
{
    public function __construct(
        private LoanRepository $loanRepository,
    ) {
    }

    public function handle(Book $book): Loan
    {
        $loan = $this->loanRepository->findOpenedLoan($book);

        if ($loan === null) {
            throw new LoanReturnRejectedException('Не удалось найти открытой выдачи');
        }

        $loan->returned();

        $this->loanRepository->save($loan);

        return $loan;
    }
}
