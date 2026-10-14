<?php

declare(strict_types=1);

namespace App\Tests\Functional\Http;

use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use App\Domain\Loan\Entity\Loan;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;

final class LoanListsControllerTest extends DatabaseWebTestCase
{
    #[DataProvider('emptyLists')]
    public function testEmptyDatabaseReturnsEmptyList(string $url): void
    {
        // Эти общие списки проверяются на отдельной пустой тестовой базе.
        // Остальные тесты оставляют её чистой благодаря откату транзакций.
        self::assertSame([], $this->getList($url));
    }

    public static function emptyLists(): iterable
    {
        yield 'available books' => ['/api/books/available'];
        yield 'overdue loans' => ['/api/loans/overdue'];
    }

    public function testAvailableBooksIncludeNeverIssuedAndReturnedCopies(): void
    {
        $client = $this->client('reader');
        $neverIssued = $this->book('never');
        $returned = $this->book('returned');
        $occupied = $this->book('occupied');
        $closed = $this->loan($client, $returned, '-20 days', '-6 days');
        $closed->returned();
        $this->loan($client, $occupied, '-1 day', '+13 days');
        $this->entityManager->flush();

        $data = $this->getList('/api/books/available');
        $ids = array_column($data, 'id');
        self::assertContains($neverIssued->id(), $ids);
        self::assertContains($returned->id(), $ids);
        self::assertNotContains($occupied->id(), $ids);
        self::assertCount(count(array_unique($ids)), $data);

        $row = array_values(array_filter($data, fn (array $row) => $row['id'] === $returned->id()));
        self::assertSame([[
            'id' => $returned->id(),
            'inv_number' => $returned->invNumber(),
            'title' => $returned->title(),
            'author' => $returned->author(),
        ]], $row);
    }

    public function testClientListContainsOnlyOwnOpenLoansAndDates(): void
    {
        $client = $this->client('reader');
        $other = $this->client('other');
        $open = $this->loan($client, $this->book('open'), '-1 day', '+13 days');
        $closed = $this->loan($client, $this->book('closed'), '-20 days', '-6 days');
        $closed->returned();
        $this->loan($other, $this->book('other'), '-1 day', '+13 days');
        $this->entityManager->flush();

        self::assertSame([
            $this->loanRow($open),
        ], $this->getList('/api/clients/'.$client->id().'/loans'));
    }

    public function testClientWithoutLoansGetsEmptyList(): void
    {
        $client = $this->client('reader');
        $this->entityManager->flush();

        self::assertSame([], $this->getList('/api/clients/'.$client->id().'/loans'));
    }

    public function testOverdueListExcludesReturnedAndFutureLoansAndSortsOldestFirst(): void
    {
        $client = $this->client('reader');
        // Создаём не в порядке срока, чтобы проверить именно сортировку запроса.
        $recent = $this->loan($client, $this->book('recent'), '-15 days', '-1 day');
        $old = $this->loan($client, $this->book('old'), '-24 days', '-10 days');
        $closed = $this->loan($client, $this->book('closed'), '-20 days', '-6 days');
        $closed->returned();
        $future = $this->loan($client, $this->book('future'), '-1 day', '+13 days');
        $this->entityManager->flush();

        $data = $this->getList('/api/loans/overdue');
        $fixtureIds = [$recent->id(), $old->id(), $closed->id(), $future->id()];
        $rows = array_values(array_filter($data, fn (array $row) => in_array($row['loan_id'], $fixtureIds, true)));

        self::assertSame([
            $this->loanRow($old, includeClient: true),
            $this->loanRow($recent, includeClient: true),
        ], $rows);
    }

    public function testBookHistoryIncludesOpenAndClosedLoansNewestFirst(): void
    {
        $firstClient = $this->client('first');
        $secondClient = $this->client('second');
        $book = $this->book('history');
        $closed = $this->loan($firstClient, $book, '-30 days', '-16 days');
        $closed->returned();
        $open = $this->loan($secondClient, $book, '-1 day', '+13 days');
        $this->loan($firstClient, $this->book('unrelated'), '-1 day', '+13 days');
        $this->entityManager->flush();

        self::assertSame([
            $this->loanRow($open, includeClient: true, includeReturn: true),
            $this->loanRow($closed, includeClient: true, includeReturn: true),
        ], $this->getList('/api/books/'.$book->id().'/loans'));
    }

    public function testNeverIssuedBookGetsEmptyHistory(): void
    {
        $book = $this->book('never');
        $this->entityManager->flush();

        self::assertSame([], $this->getList('/api/books/'.$book->id().'/loans'));
    }

    #[DataProvider('missingResources')]
    public function testMissingResourceReturnsNotFound(string $kind, string $message): void
    {
        $entity = $kind === 'clients' ? $this->client('deleted') : $this->book('deleted');
        $this->entityManager->flush();
        $id = $entity->id();
        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        $this->browser->request('GET', '/api/'.$kind.'/'.$id.'/loans');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['message' => $message, 'errors' => []], $this->responseData());
    }

    public static function missingResources(): iterable
    {
        yield 'client' => ['clients', 'Клиент не найден'];
        yield 'book' => ['books', 'Книга не найдена'];
    }

    private function client(string $suffix): Client
    {
        $client = new Client('Читатель '.$suffix, $suffix.'@example.com');
        $this->entityManager->persist($client);

        return $client;
    }

    private function book(string $suffix): Book
    {
        $book = new Book('LIST-'.$suffix, 'Книга '.$suffix, 'Автор');
        $this->entityManager->persist($book);

        return $book;
    }

    private function loan(Client $client, Book $book, string $issued, string $deadline): Loan
    {
        $loan = new Loan(
            $client,
            $book,
            (new DateTimeImmutable($issued))->setTime(12, 0),
            (new DateTimeImmutable($deadline))->setTime(23, 59, 59),
        );
        $this->entityManager->persist($loan);

        return $loan;
    }

    private function getList(string $url): array
    {
        // Один HTTP-запрос на тест: ядро и внешняя транзакция сохраняются.
        $this->browser->request('GET', $url);
        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = $this->responseData();
        self::assertTrue(array_is_list($data));

        return $data;
    }

    private function responseData(): array
    {
        return json_decode($this->browser->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function loanRow(Loan $loan, bool $includeClient = false, bool $includeReturn = false): array
    {
        $row = ['loan_id' => $loan->id()];
        if ($includeClient) {
            $row['client_id'] = $loan->client()->id();
            $row['client_name'] = $loan->client()->name();
        }
        $row += [
            'book_id' => $loan->book()->id(),
            'inv_number' => $loan->book()->invNumber(),
            'title' => $loan->book()->title(),
            'author' => $loan->book()->author(),
            'loan_at' => $loan->loan_at()->format(DATE_ATOM),
            'planned_return_at' => $loan->planned_return_at()->format(DATE_ATOM),
        ];
        if ($includeReturn) {
            $row['returned_at'] = $loan->returned_at()?->format(DATE_ATOM);
        }

        return $row;
    }
}
