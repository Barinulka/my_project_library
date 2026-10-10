<?php

declare(strict_types=1);

namespace App\Tests\Domain\Loan\Entity;

use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use App\Domain\Loan\Entity\Loan;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LoanTest extends TestCase
{
    public function testRejectsReturnDeadlineBeforeLoanDate(): void
    {
        $client = new Client('Тест', 'test@test.ru');
        $book = new Book('TEST-0001', 'title', 'author');

        $loanAt = new DateTimeImmutable('2026-10-10 12:00:00');
        $plannedReturnAt = new DateTimeImmutable('2026-10-09 23:59:59');

        $this->expectException(InvalidArgumentException::class);

        new Loan($client, $book, $loanAt, $plannedReturnAt);
    }

    public function testReturnSetsActualReturnDate(): void
    {
        $client = new Client('Тест', 'test@test.ru');
        $book = new Book('TEST-0001', 'title', 'author');

        $loanAt = new DateTimeImmutable('2026-10-01 12:00:00');
        $plannedReturnAt = $loanAt->modify('+14 days')->setTime(23, 59, 59);

        $loan = new Loan($client, $book, $loanAt, $plannedReturnAt);

        self::assertNull($loan->returned_at());

        $loan->returned();

        self::assertNotNull($loan->returned_at());
    }

    public function testRejectRepeatedReturnLoan(): void
    {
        $client = new Client('Тест', 'test@test.ru');
        $book = new Book('TEST-0001', 'title', 'author');

        $loanAt = new DateTimeImmutable('2026-10-01 12:00:00');
        $plannedReturnAt = $loanAt->modify('+14 days')->setTime(23, 59, 59);

        $loan = new Loan($client, $book, $loanAt, $plannedReturnAt);

        $loan->returned();

        $this->expectException(RuntimeException::class);

        $loan->returned();
    }
}
