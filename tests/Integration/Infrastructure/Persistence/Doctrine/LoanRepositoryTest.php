<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine;

use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use App\Domain\Loan\Entity\Loan;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;
use App\Tests\Integration\DatabaseTestCase;

final class LoanRepositoryTest extends DatabaseTestCase
{
    public function testRejectsTwoOpenLoansForSameBook(): void
    {
        $client = new Client('Анна', 'anna@example.com');
        $clientTwo = new Client('Борис', 'boris@example.com');

        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        $loanAt = new \DateTimeImmutable('-1 day');
        $plannedLoanAt = $loanAt->modify('+14 day')->setTime(23, 59, 59);

        $loan = new Loan($client, $book, $loanAt, $plannedLoanAt);

        $this->entityManager->persist($client);
        $this->entityManager->persist($clientTwo);
        $this->entityManager->persist($book);
        $this->entityManager->persist($loan);
        $this->entityManager->flush();

        $loanTwo = new Loan($clientTwo, $book, $loanAt, $plannedLoanAt);

        $repository = self::getContainer()->get(LoanRepository::class);

        $this->expectException(
            \Doctrine\DBAL\Exception\UniqueConstraintViolationException::class,
        );

        $repository->save($loanTwo);
    }

    public function testAllowsNewLoanForSameBookAfterReturn(): void
    {
        $client = new Client('Анна', 'anna@example.com');
        $clientTwo = new Client('Борис', 'boris@example.com');

        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        $this->entityManager->persist($client);
        $this->entityManager->persist($clientTwo);
        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $bookId = $book->id();

        $repository = self::getContainer()->get(LoanRepository::class);

        $loanAt = new \DateTimeImmutable('-1 day');
        $plannedLoanAt = $loanAt->modify('+14 day')->setTime(23, 59, 59);

        $loan = new Loan($client, $book, $loanAt, $plannedLoanAt);

        $repository->save($loan);

        $loanId = $loan->id();

        $loan->returned();

        $repository->save($loan);

        $loanTwo = new Loan($clientTwo, $book, $loanAt, $plannedLoanAt);

        $repository->save($loanTwo);

        $this->entityManager->clear();

        $book = $this->entityManager->find(Book::class, $bookId);

        self::assertInstanceOf(Book::class, $book);

        $openedLoan = $repository->findOpenedLoan($book);

        self::assertInstanceOf(Loan::class, $openedLoan);
        self::assertSame(2, $repository->count(['book' => $book]));
        self::assertSame($clientTwo->id(), $openedLoan->client()->id());

        $firstLoan = $this->entityManager->find(Loan::class, $loanId);

        self::assertInstanceOf(Loan::class, $firstLoan);
        self::assertNotNull($firstLoan->returned_at());
    }
}
