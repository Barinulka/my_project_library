<?php

declare(strict_types=1);

namespace App\Tests\Functional\Http;

use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use App\Domain\Loan\Entity\Loan;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;

class ReturnLoanControllerTest extends DatabaseWebTestCase
{
    public function testReturnsBook(): void
    {
        $client = new Client('Анна', 'anna@example.com');
        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        $loanAt = new \DateTimeImmutable('-1 day');
        $plannedLoanAt = $loanAt->modify('+14 day')->setTime(23, 59, 59);

        $loan = new Loan($client, $book, $loanAt, $plannedLoanAt);

        $this->entityManager->persist($client);
        $this->entityManager->persist($book);
        $this->entityManager->persist($loan);
        $this->entityManager->flush();

        $clientId = $client->id();
        $bookId = $book->id();
        $loanId = $loan->id();

        $this->browser->jsonRequest('POST', '/api/return', [
            'book_id' => $book->id(),
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode(
            $this->browser->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('message', $data);
        self::assertIsInt($data['id']);
        self::assertSame($loanId, $data['id']);
        self::assertSame('Книга возвращена', $data['message']);

        $this->entityManager->clear();

        $savedLoan = $this->entityManager->find(Loan::class, $data['id']);
        $book = $this->entityManager->find(Book::class, $bookId);
        $repository = self::getContainer()->get(LoanRepository::class);

        self::assertInstanceOf(Loan::class, $savedLoan);
        self::assertInstanceOf(Book::class, $book);
        self::assertNotNull($savedLoan->returned_at());
        self::assertSame($bookId, $savedLoan->book()->id());
        self::assertSame($clientId, $savedLoan->client()->id());
        self::assertSame($loanId, $savedLoan->id());
        self::assertNull($repository->findOpenedLoan($book));
    }

    public function testReturnsConflictWhenBookHasNoOpenLoan(): void
    {
        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $bookId = $book->id();

        $this->browser->jsonRequest('POST', '/api/return', [
            'book_id' => $bookId,
        ]);

        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode(
            $this->browser->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame([
            'message' => 'Не удалось найти открытой выдачи',
            'errors' => [],
        ], $data);

        $this->entityManager->clear();

        $repository = self::getContainer()->get(LoanRepository::class);
        self::assertNull($repository->findOpenedLoan($book));
        self::assertSame(0, $repository->count(['book' => $book]));
    }

    public function testReturnsNotFoundWhenBookDoesNotExist(): void
    {
        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $bookId = $book->id();

        $this->entityManager->remove($book);
        $this->entityManager->flush();

        $this->browser->jsonRequest('POST', '/api/return', [
            'book_id' => $bookId,
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode(
            $this->browser->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame([
            'message' => 'Книга не найдена',
            'errors' => [],
        ], $data);
    }

    public function testRejectsNegativeBookId(): void
    {
        $this->browser->jsonRequest('POST', '/api/return', [
            'book_id' => -1,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode(
            $this->browser->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame([
            'message' => 'Ошибка валидации',
            'errors' => [[
                'field' => 'book_id',
                'message' => 'Значение должно быть положительным числом',
            ]],
        ], $data);
    }

    public function testRejectsRepeatedReturnWithoutChangingReturnDate(): void
    {
        $client = new Client('Анна', 'anna@example.com');
        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        $loanAt = new \DateTimeImmutable('-1 day');
        $plannedLoanAt = $loanAt->modify('+14 day')->setTime(23, 59, 59);

        $loan = new Loan($client, $book, $loanAt, $plannedLoanAt);
        $loan->returned();

        $this->entityManager->persist($client);
        $this->entityManager->persist($book);
        $this->entityManager->persist($loan);
        $this->entityManager->flush();

        $bookId = $book->id();
        $loanId = $loan->id();
        $loanReturnedAt = $loan->returned_at();

        $this->browser->jsonRequest('POST', '/api/return', [
            'book_id' => $bookId,
        ]);

        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode(
            $this->browser->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame([
            'message' => 'Не удалось найти открытой выдачи',
            'errors' => [],
        ], $data);

        $this->entityManager->clear();

        $savedLoan = $this->entityManager->find(Loan::class, $loanId);
        self::assertInstanceOf(Loan::class, $savedLoan);
        self::assertNotNull($savedLoan->returned_at());
        self::assertSame($loanReturnedAt->format('Y-m-d H:i:s'), $savedLoan->returned_at()->format('Y-m-d H:i:s'));
        self::assertSame($loanId, $savedLoan->id());
    }
}
