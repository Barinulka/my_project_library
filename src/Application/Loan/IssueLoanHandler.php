<?php

declare(strict_types=1);

namespace App\Application\Loan;

use App\Application\Loan\Exception\LoanIssueRejectedException;
use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use App\Domain\Loan\Entity\Loan;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class IssueLoanHandler
{
    public function __construct(
        private LoanRepository $loanRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param Client $client
     * @param Book   $book
     *
     * @return Loan
     * @throws UniqueConstraintViolationException
     */
    public function handle(Client $client, Book $book): Loan
    {
        try {
            return $this->entityManager->wrapInTransaction(
                function () use ($client, $book): Loan {
                    // Ждём своей очереди на выдачу этому читателю.
                    $this->entityManager->lock($client, LockMode::PESSIMISTIC_WRITE,);

                    // Время определяем после возможного ожидания.
                    $now = new DateTimeImmutable('now');

                    $openedLoan = $this->loanRepository->findOpenedLoan($book);

                    if ($openedLoan !== null) {
                        throw new LoanIssueRejectedException('Экземпляр уже выдан',);
                    }

                    $countOpenedLoans = $this->loanRepository->countOpenLoansByClient($client);

                    if ($countOpenedLoans >= 3) {
                        throw new LoanIssueRejectedException('Нельзя выдать больше 3 экземпляров читателю',);
                    }

                    $hasOverdueLoans = $this->loanRepository->hasOverdueLoansByClient($client, $now);

                    if ($hasOverdueLoans) {
                        throw new LoanIssueRejectedException('У читателя есть просроченные экземпляры книг',);
                    }

                    $plannedReturnAt = $now->modify('+14 days')->setTime(23, 59, 59);

                    $loan = new Loan(
                        client: $client,
                        book: $book,
                        loan_at: $now,
                        planned_return_at: $plannedReturnAt,
                    );

                    $this->loanRepository->save($loan);

                    return $loan;
                },
            );
        } catch (UniqueConstraintViolationException $exception) {
            if (!str_contains($exception->getMessage(), '"uniq_loan_open_book"')) {
                throw $exception;
            }

            throw new LoanIssueRejectedException('Экземпляр уже выдан', previous: $exception,);
        }
    }
}
