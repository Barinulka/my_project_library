<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Loan;

use App\Application\Loan\ReturnLoanHandler;
use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use App\Domain\Loan\Entity\Loan;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;
use App\Tests\Integration\DatabaseTestCase;
use DateTimeImmutable;
use RuntimeException;

class ReturnLoanHandlerTest extends DatabaseTestCase
{
    public function testReturnsBookAndPreservesLoanHistory(): void
    {
        // Подготовка данных.
        $client = new Client('Анна', 'anna@example.com');

        $book = new Book('LIB-0042', 'Учебная книга 1', 'Автор');

        $nowAt = new DateTimeImmutable('now');
        $loanAt = $nowAt->modify('-14 days')->setTime(12,00,00);
        $plannedReturnAt = $nowAt->setTime(23,59,59);

        $loan = new Loan($client, $book, $loanAt, $plannedReturnAt);

        $this->entityManager->persist($client);
        $this->entityManager->persist($book);
        $this->entityManager->persist($loan);
        $this->entityManager->flush();

        $clientId = $client->id();
        $bookId = $book->id();
        $loanId = $loan->id();

        $handler = self::getContainer()->get(ReturnLoanHandler::class);

        $handler->handle($book);

        // Убираем объекты из памяти, чтобы проверить данные из базы.
        $this->entityManager->clear();

        $savedBook = $this->entityManager->find(Book::class, $bookId);

        self::assertInstanceOf(Book::class, $savedBook);

        $repository = self::getContainer()->get(LoanRepository::class);
        $openedLoan = $repository->findOpenedLoan($savedBook);

        self::assertNull($openedLoan);

        $savedLoan = $this->entityManager->find(Loan::class, $loanId);

        self::assertInstanceOf(Loan::class, $savedLoan);
        self::assertSame($clientId, $savedLoan->client()->id());
        self::assertSame($bookId, $savedLoan->book()->id());
        self::assertSame($loanId, $savedLoan->id());
        self::assertNotNull($savedLoan->returned_at());
    }

    public function testRejectsReturnWhenBookHasNoOpenLoan(): void
    {
        $book = new Book('LIB-0042', 'Учебная книга 1', 'Автор');

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $handler = self::getContainer()->get(ReturnLoanHandler::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Не удалось найти открытой выдачи');

        $handler->handle($book);
    }
}
