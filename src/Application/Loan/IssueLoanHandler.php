<?php

declare(strict_types=1);

namespace App\Application\Loan;

use App\Application\Loan\Exception\LoanIssueRejectedException;
use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use App\Domain\Loan\Entity\Loan;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;
use DateTimeImmutable;

class IssueLoanHandler
{
    public function __construct(
        private LoanRepository $loanRepository,
    ) {
    }

    public function handle(Client $client, Book $book): Loan
    {
        $now = new DateTimeImmutable('now');

        $openedLoan = $this->loanRepository->findOpenedLoan($book);

        if ($openedLoan !== null) {
            throw new LoanIssueRejectedException('Экземпляр уже выдан');
        }

        $countOpenedLoans = $this->loanRepository->countOpenLoansByClient($client);

        if ($countOpenedLoans >= 3) {
            throw new LoanIssueRejectedException('Нельзя выдать больше 3 экземпляров читателю');
        }

        $overdueLoans = $this->loanRepository->hasOverdueLoansByClient($client, $now);

        if ($overdueLoans) {
            throw new LoanIssueRejectedException('У читателя есть просроченные экземпляры книг');
        }

        $plannedReturnAt = $now->modify('+14 day')->setTime(23, 59, 59);

        $loan = new Loan(
            client: $client,
            book: $book,
            loan_at: $now,
            planned_return_at: $plannedReturnAt,
        );

        $this->loanRepository->save($loan);

        return $loan;
    }
}
