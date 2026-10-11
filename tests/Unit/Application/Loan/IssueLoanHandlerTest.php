<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Loan;

use App\Application\Loan\Exception\LoanIssueRejectedException;
use App\Application\Loan\IssueLoanHandler;
use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Driver\PDO\Exception as DriverException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class IssueLoanHandlerTest extends TestCase
{
    public function testRethrowsUnrelatedUniqueConstraintViolation(): void
    {
        $client = new Client('Анна', 'anna@example.com');
        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        // Делаем Mock репозитория
        $repository = $this->createMock(LoanRepository::class);

        $repository->method('findOpenedLoan')->willReturn(null);
        $repository->method('countOpenLoansByClient')->willReturn(0);
        $repository->method('hasOverdueLoansByClient')->willReturn(false);

        // Подготавливаем исключение похожее на настоящую ошибку PDO
        $pdoException = new \PDOException(
            'duplicate key value violates unique constraint "another_index"',
        );

        $pdoException->errorInfo = ['23505', 7, $pdoException->getMessage()];

        $exception = new UniqueConstraintViolationException(
            DriverException::new($pdoException),
            null,
        );

        // Настройка сохранения
        $repository->expects(self::once())->method('save')->willThrowException($exception);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entityManager->expects(self::once())
            ->method('wrapInTransaction')
            ->willReturnCallback(
                static fn (callable $operation) => $operation(),
            );

        $entityManager->expects(self::once())
            ->method('lock')
            ->with($client, LockMode::PESSIMISTIC_WRITE);

        $handler = new IssueLoanHandler($repository, $entityManager);

        $this->expectException(UniqueConstraintViolationException::class);

        $handler->handle($client, $book);
    }

    public function testConvertsOpenBookConflictToLoanIssueRejection(): void
    {
        $client = new Client('Анна', 'anna@example.com');
        $book = new Book('LIB-0042', 'Учебная книга', 'Автор');

        // Делаем Mock репозитория
        $repository = $this->createMock(LoanRepository::class);

        $repository->method('findOpenedLoan')->willReturn(null);
        $repository->method('countOpenLoansByClient')->willReturn(0);
        $repository->method('hasOverdueLoansByClient')->willReturn(false);

        // Подготавливаем исключение похожее на настоящую ошибку PDO
        $pdoException = new \PDOException(
            'duplicate key value violates unique constraint "uniq_loan_open_book"',
        );

        $pdoException->errorInfo = ['23505', 7, $pdoException->getMessage()];

        $exception = new UniqueConstraintViolationException(
            DriverException::new($pdoException),
            null,
        );

        // Настройка сохранения
        $repository->expects(self::once())->method('save')->willThrowException($exception);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entityManager->expects(self::once())
            ->method('wrapInTransaction')
            ->willReturnCallback(
                static fn (callable $operation) => $operation(),
            );

        $entityManager->expects(self::once())
            ->method('lock')
            ->with($client, LockMode::PESSIMISTIC_WRITE);

        $handler = new IssueLoanHandler($repository, $entityManager);

        $this->expectException(LoanIssueRejectedException::class);
        $this->expectExceptionMessageIs('Экземпляр уже выдан');

        $handler->handle($client, $book);
    }
}
