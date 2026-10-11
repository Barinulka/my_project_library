<?php

declare(strict_types=1);

namespace App\Tests\Functional\Http;

use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use App\Domain\Loan\Entity\Loan;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;
use DateTimeImmutable;

final class IssueLoanControllerTest extends DatabaseWebTestCase
{
    public function testCreatesLoan(): void
    {
        $client = new Client('Анна', 'anna@example.com');
        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        $this->entityManager->persist($client);
        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $clientId = $client->id();
        $bookId = $book->id();

        $this->browser->jsonRequest('POST', '/api/loans', [
            'client_id' => $client->id(),
            'book_id' => $book->id(),
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode(
            $this->browser->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayHasKey('id', $data);
        self::assertIsInt($data['id']);
        self::assertGreaterThan(0, $data['id']);

        $this->entityManager->clear();

        $loan = $this->entityManager->find(Loan::class, $data['id']);

        self::assertInstanceOf(Loan::class, $loan);
        self::assertSame($clientId, $loan->client()->id());
        self::assertSame($bookId, $loan->book()->id());
        self::assertNull($loan->returned_at());
    }

    public function testReturnsConflictWhenBookIsAlreadyIssued(): void
    {
        $client = new Client('Анна', 'anna@example.com');
        $clientTwo = new Client('Юлия', 'julia@example.com');

        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        $loanAt = new DateTimeImmutable('now');
        $plannedReturnAt = $loanAt->modify('+14 day')->setTime(23,59,59);

        $loan = new Loan($client, $book, $loanAt, $plannedReturnAt);

        $this->entityManager->persist($client);
        $this->entityManager->persist($clientTwo);
        $this->entityManager->persist($book);
        $this->entityManager->persist($loan);
        $this->entityManager->flush();

        $clientId = $client->id();
        $bookId = $book->id();
        $loanId = $loan->id();

        $this->browser->jsonRequest('POST', '/api/loans', [
            'client_id' => $clientTwo->id(),
            'book_id' => $book->id(),
        ]);

        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode(
            $this->browser->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayHasKey('message', $data);
        self::assertSame('Экземпляр уже выдан', $data['message']);

        $this->entityManager->clear();

        $repository = self::getContainer()->get(LoanRepository::class);
        $openedLoanForClient = $repository->findOpenedLoan($book);

        self::assertInstanceOf(Loan::class, $openedLoanForClient);
        self::assertSame($clientId, $openedLoanForClient->client()->id());
        self::assertSame($bookId, $openedLoanForClient->book()->id());
        self::assertSame($loanId, $openedLoanForClient->id());
        self::assertSame(1, $repository->count(['book' => $book]));
        self::assertSame(1, $repository->countOpenLoansByClient($client));
        self::assertSame(0, $repository->countOpenLoansByClient($clientTwo));
    }

    public function testReturnsNotFoundWhenClientDoesNotExist(): void
    {
        $client = new Client('Анна', 'anna@example.com');
        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        $this->entityManager->persist($client);
        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $bookId = $book->id();
        $clientId = $client->id();

        $this->entityManager->remove($client);
        $this->entityManager->flush();

        $this->browser->jsonRequest('POST', '/api/loans', [
            'client_id' => $clientId,
            'book_id' => $bookId,
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode(
            $this->browser->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayHasKey('detail', $data);
        self::assertSame('Клиент не найден', $data['detail']);

        $this->entityManager->clear();

        $repository = self::getContainer()->get(LoanRepository::class);

        self::assertNull($repository->findOpenedLoan($book));
        self::assertSame(0, $repository->count(['book' => $book]));
    }

    public function testReturnsNotFoundWhenBookDoesNotExist(): void
    {
        $client = new Client('Анна', 'anna@example.com');
        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        $this->entityManager->persist($client);
        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $bookId = $book->id();
        $clientId = $client->id();

        $this->entityManager->remove($book);
        $this->entityManager->flush();

        $this->browser->jsonRequest('POST', '/api/loans', [
            'client_id' => $clientId,
            'book_id' => $bookId,
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode(
            $this->browser->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayHasKey('detail', $data);
        self::assertSame('Книга не найдена', $data['detail']);

        $this->entityManager->clear();

        $repository = self::getContainer()->get(LoanRepository::class);

        self::assertSame(0, $repository->countOpenLoansByClient($client));
        self::assertSame(0, $repository->count(['client' => $client]));
    }

    public function testRejectsNegativeClientId(): void
    {
        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $bookId = $book->id();

        $this->browser->jsonRequest('POST', '/api/loans', [
            'client_id' => -1,
            'book_id' => $bookId,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode(
            $this->browser->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayHasKey('detail', $data);
        self::assertSame('client_id: Значение должно быть положительным числом', $data['detail']);

        $this->entityManager->clear();

        $repository = self::getContainer()->get(LoanRepository::class);

        self::assertNull($repository->findOpenedLoan($book));
        self::assertSame(0, $repository->count(['book' => $book]));
    }

    public function testRejectsNegativeBookId(): void
    {
        $client = new Client('Анна', 'anna@example.com');

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        $clientId = $client->id();

        $this->browser->jsonRequest('POST', '/api/loans', [
            'client_id' => $clientId,
            'book_id' => -1,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode(
            $this->browser->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayHasKey('detail', $data);
        self::assertSame('book_id: Значение должно быть положительным числом', $data['detail']);

        $this->entityManager->clear();

        $repository = self::getContainer()->get(LoanRepository::class);

        self::assertSame(0, $repository->countOpenLoansByClient($client));
        self::assertSame(0, $repository->count(['client' => $client]));
    }
}
