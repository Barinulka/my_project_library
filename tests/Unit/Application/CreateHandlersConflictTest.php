<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Book\CreateBookHandler;
use App\Application\Book\Exception\BookInvNumberAlreadyExistsException;
use App\Application\Client\CreateClientHandler;
use App\Application\Client\Exception\ClientEmailAlreadyExistsException;
use App\Infrastructure\Persistence\Doctrine\BookRepository;
use App\Infrastructure\Persistence\Doctrine\ClientRepository;
use Doctrine\DBAL\Driver\PDO\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CreateHandlersConflictTest extends TestCase
{
    #[DataProvider('handlers')]
    public function testConvertsOnlyOwnUniqueIndexConflict(
        string $handlerClass,
        string $repositoryClass,
        string $lookupMethod,
        array $arguments,
        string $index,
        string $businessExceptionClass,
        string $message,
    ): void {
        $source = $this->uniqueViolation($index);
        $repository = $this->createMock($repositoryClass);
        $repository->expects(self::once())->method($lookupMethod)->willReturn(false);
        $repository->expects(self::once())->method('save')->willThrowException($source);
        $handler = new $handlerClass($repository);

        try {
            $handler->handle(...$arguments);
            self::fail('Ожидался бизнес-отказ после конфликта при сохранении');
        } catch (\RuntimeException $exception) {
            self::assertInstanceOf($businessExceptionClass, $exception);
            self::assertSame($message, $exception->getMessage());
            self::assertSame($source, $exception->getPrevious());
        }
    }

    #[DataProvider('handlers')]
    public function testRethrowsUnrelatedUniqueViolation(
        string $handlerClass,
        string $repositoryClass,
        string $lookupMethod,
        array $arguments,
        string $index,
        string $businessExceptionClass,
        string $message,
    ): void {
        $source = $this->uniqueViolation('another_index');
        $repository = $this->createMock($repositoryClass);
        $repository->method($lookupMethod)->willReturn(false);
        $repository->expects(self::once())->method('save')->willThrowException($source);
        $handler = new $handlerClass($repository);

        try {
            $handler->handle(...$arguments);
            self::fail('Посторонняя ошибка уникальности не должна скрываться');
        } catch (UniqueConstraintViolationException $exception) {
            self::assertSame($source, $exception);
        }
    }

    public static function handlers(): iterable
    {
        yield 'client email' => [
            CreateClientHandler::class, ClientRepository::class, 'hasEmail',
            ['Анна', 'anna@example.com'], 'uniq_c7440455e7927c74',
            ClientEmailAlreadyExistsException::class,
            "Клиент с таким email 'anna@example.com' уже зарегистрирован",
        ];
        yield 'book inventory number' => [
            CreateBookHandler::class, BookRepository::class, 'hasInvNumber',
            ['LIB-0042', 'Книга', 'Автор'], 'uniq_cbe5a331c229a244',
            BookInvNumberAlreadyExistsException::class,
            "Книга с таким инвентарным номером 'LIB-0042' уже существует",
        ];
    }

    private function uniqueViolation(string $index): UniqueConstraintViolationException
    {
        $pdo = new \PDOException('duplicate key value violates unique constraint "'.$index.'"');
        $pdo->errorInfo = ['23505', 7, $pdo->getMessage()];

        return new UniqueConstraintViolationException(DriverException::new($pdo), null);
    }
}
