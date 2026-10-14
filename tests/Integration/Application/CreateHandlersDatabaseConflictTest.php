<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application;

use App\Application\Book\CreateBookHandler;
use App\Application\Book\Exception\BookInvNumberAlreadyExistsException;
use App\Application\Client\CreateClientHandler;
use App\Application\Client\Exception\ClientEmailAlreadyExistsException;
use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use App\Infrastructure\Persistence\Doctrine\BookRepository;
use App\Infrastructure\Persistence\Doctrine\ClientRepository;
use App\Tests\Integration\DatabaseTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class CreateHandlersDatabaseConflictTest extends DatabaseTestCase
{
    public function testClientConflictUsesActualPostgresIndexName(): void
    {
        $this->entityManager->persist(new Client('Первый', 'conflict@example.com'));
        $this->entityManager->flush();
        $realRepository = self::getContainer()->get(ClientRepository::class);

        // Моделируем устаревшую проверку, но сохраняем через настоящую БД.
        $repository = $this->createMock(ClientRepository::class);
        $repository->method('hasEmail')->willReturn(false);
        $repository->expects(self::once())->method('save')->willReturnCallback(
            static fn (Client $client) => $realRepository->save($client),
        );
        $handler = new CreateClientHandler($repository);

        try {
            $handler->handle('Второй', 'conflict@example.com');
            self::fail('База должна отклонить повторный email');
        } catch (ClientEmailAlreadyExistsException $exception) {
            self::assertSame("Клиент с таким email 'conflict@example.com' уже зарегистрирован", $exception->getMessage());
            self::assertInstanceOf(UniqueConstraintViolationException::class, $exception->getPrevious());
        }
        // После ошибки flush EntityManager закрыт; откат выполняет tearDown.
    }

    public function testBookConflictUsesActualPostgresIndexName(): void
    {
        $this->entityManager->persist(new Book('CONFLICT-42', 'Первая', 'Автор'));
        $this->entityManager->flush();
        $realRepository = self::getContainer()->get(BookRepository::class);

        $repository = $this->createMock(BookRepository::class);
        $repository->method('hasInvNumber')->willReturn(false);
        $repository->expects(self::once())->method('save')->willReturnCallback(
            static fn (Book $book) => $realRepository->save($book),
        );
        $handler = new CreateBookHandler($repository);

        try {
            $handler->handle('CONFLICT-42', 'Вторая', 'Автор');
            self::fail('База должна отклонить повторный инвентарный номер');
        } catch (BookInvNumberAlreadyExistsException $exception) {
            self::assertSame("Книга с таким инвентарным номером 'CONFLICT-42' уже существует", $exception->getMessage());
            self::assertInstanceOf(UniqueConstraintViolationException::class, $exception->getPrevious());
        }
    }
}
