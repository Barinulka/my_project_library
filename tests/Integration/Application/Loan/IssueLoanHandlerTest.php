<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Loan;

use App\Application\Loan\IssueLoanHandler;
use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use App\Domain\Loan\Entity\Loan;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;
use App\Tests\Integration\DatabaseTestCase;
use DateTimeImmutable;
use RuntimeException;

final class IssueLoanHandlerTest extends DatabaseTestCase
{
    public function testIssuesBookToClient(): void
    {
        // Подготовка данных.
        $client = new Client('Анна', 'anna@example.com');
        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        $this->entityManager->persist($client);
        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $clientId = $client->id();
        $bookId = $book->id();

        $handler = self::getContainer()->get(IssueLoanHandler::class);

        // Действие.
        $handler->handle($client, $book);

        // Убираем объекты из памяти, чтобы проверить данные из базы.
        $this->entityManager->clear();

        $savedBook = $this->entityManager->find(Book::class, $bookId);

        self::assertInstanceOf(Book::class, $savedBook);

        $repository = self::getContainer()->get(LoanRepository::class);
        $loan = $repository->findOpenedLoan($savedBook);

        // Проверка результата.
        self::assertInstanceOf(Loan::class, $loan);
        self::assertSame($clientId, $loan->client()->id());
        self::assertSame($bookId, $loan->book()->id());
        self::assertNull($loan->returned_at());

        $expectedDeadline = $loan->loan_at()->modify('+14 days')->setTime(23, 59, 59);

        self::assertEquals($expectedDeadline, $loan->planned_return_at(),);

        self::assertSame(1, $repository->count(['book' => $savedBook]));
    }

    public function testRejectIssuesBookToAnotherClient(): void
    {
        $exceptionCaught = false;

        // Подготовка данных.
        $clientFirst = new Client('Анна', 'anna@example.com');
        $clientSecond = new Client('Михаил', 'mikhail@example.com');

        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        $this->entityManager->persist($clientFirst);
        $this->entityManager->persist($clientSecond);
        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $handler = self::getContainer()->get(IssueLoanHandler::class);

        $handler->handle($clientFirst, $book);

        try {
            $handler->handle($clientSecond, $book);
        } catch(RuntimeException $e) {
            self::assertSame('Экземпляр уже выдан', $e->getMessage());
            $exceptionCaught = true;
        }

        self::assertTrue($exceptionCaught);

        $repository = self::getContainer()->get(LoanRepository::class);
        $openedLoansFirstClient = $repository->countOpenLoansByClient($clientFirst);
        $openedLoansSecondClient = $repository->countOpenLoansByClient($clientSecond);

        self::assertSame(1, $openedLoansFirstClient);
        self::assertSame(0, $openedLoansSecondClient);
    }

    public function testRejectsFourthOpenLoanForClient(): void
    {
        $exceptionCaught = false;

        // Подготовка данных.
        $client = new Client('Анна', 'anna@example.com');

        $bookOne = new Book('LIB-0042', 'Учебная книга 1', 'Автор');
        $bookTwo = new Book('LIB-0043', 'Учебная книга 2', 'Автор');
        $bookThree = new Book('LIB-0044', 'Учебная книга 3', 'Автор');
        $bookFour = new Book('LIB-0045', 'Учебная книга 4', 'Автор');

        $this->entityManager->persist($client);
        $this->entityManager->persist($bookOne);
        $this->entityManager->persist($bookTwo);
        $this->entityManager->persist($bookThree);
        $this->entityManager->persist($bookFour);
        $this->entityManager->flush();

        $handler = self::getContainer()->get(IssueLoanHandler::class);

        $handler->handle($client, $bookOne);
        $handler->handle($client, $bookTwo);
        $handler->handle($client, $bookThree);

        try {
            $handler->handle($client, $bookFour);
        } catch(RuntimeException $e) {
            self::assertSame('Нельзя выдать больше 3 экземпляров читателю', $e->getMessage());
            $exceptionCaught = true;
        }

        self::assertTrue($exceptionCaught);

        $repository = self::getContainer()->get(LoanRepository::class);
        $openedLoansForClient = $repository->countOpenLoansByClient($client);

        self::assertSame(3, $openedLoansForClient);
        self::assertNull($repository->findOpenedLoan($bookFour));
    }

    public function testRejectsLoanWhenClientHasOverdueLoans(): void
    {
        $exceptionCaught = false;

        // Подготовка данных.
        $client = new Client('Анна', 'anna@example.com');

        $bookOne = new Book('LIB-0042', 'Учебная книга 1', 'Автор');
        $bookTwo = new Book('LIB-0043', 'Учебная книга 2', 'Автор');

        $nowAt = new DateTimeImmutable('now');
        $loanAt = $nowAt->modify('-15 days')->setTime(12,00,00);
        $plannedReturnAt = $nowAt->modify('-1 day')->setTime(23,59,59);

        $loan = new Loan($client, $bookOne, $loanAt, $plannedReturnAt);

        $this->entityManager->persist($client);
        $this->entityManager->persist($bookOne);
        $this->entityManager->persist($bookTwo);
        $this->entityManager->persist($loan);
        $this->entityManager->flush();

        $handler = self::getContainer()->get(IssueLoanHandler::class);

        try {
            $handler->handle($client, $bookTwo);
        } catch(RuntimeException $e) {
            self::assertSame('У читателя есть просроченные экземпляры книг', $e->getMessage());
            $exceptionCaught = true;
        }

        self::assertTrue($exceptionCaught);

        $repository = self::getContainer()->get(LoanRepository::class);
        $openedLoanForClient = $repository->findOpenedLoan($bookOne);

        self::assertInstanceOf(Loan::class, $openedLoanForClient);
        self::assertNull($openedLoanForClient->returned_at());
        self::assertSame(1, $repository->countOpenLoansByClient($client));
        self::assertNull($repository->findOpenedLoan($bookTwo));
    }

    public function testAllowsLoanAfterOverdueBookIsReturned(): void
    {
        // Подготовка данных.
        $client = new Client('Анна', 'anna@example.com');

        $bookOne = new Book('LIB-0042', 'Учебная книга 1', 'Автор');
        $bookTwo = new Book('LIB-0043', 'Учебная книга 2', 'Автор');

        $nowAt = new DateTimeImmutable('now');
        $loanAt = $nowAt->modify('-15 days')->setTime(12,00,00);
        $plannedReturnAt = $nowAt->modify('-1 day')->setTime(23,59,59);

        $loan = new Loan($client, $bookOne, $loanAt, $plannedReturnAt);
        $loan->returned();

        $this->entityManager->persist($client);
        $this->entityManager->persist($bookOne);
        $this->entityManager->persist($bookTwo);
        $this->entityManager->persist($loan);
        $this->entityManager->flush();

        $handler = self::getContainer()->get(IssueLoanHandler::class);

        $handler->handle($client, $bookTwo);

        $repository = self::getContainer()->get(LoanRepository::class);
        $openedLoanBookOne = $repository->findOpenedLoan($bookOne);
        $openedLoanBookTwo = $repository->findOpenedLoan($bookTwo);

        self::assertNull($openedLoanBookOne);
        self::assertInstanceOf(Loan::class, $openedLoanBookTwo);
        self::assertNull($openedLoanBookTwo->returned_at());
        self::assertSame(1, $repository->countOpenLoansByClient($client));
        self::assertFalse($repository->hasOverdueLoansByClient($client, $nowAt));
        self::assertSame($client->id(), $openedLoanBookTwo->client()->id());
    }
}
