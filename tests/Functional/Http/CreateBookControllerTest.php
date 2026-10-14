<?php

declare(strict_types=1);

namespace App\Tests\Functional\Http;

use App\Domain\Book\Entity\Book;
use PHPUnit\Framework\Attributes\DataProvider;

final class CreateBookControllerTest extends DatabaseWebTestCase
{
    public function testCreatesBookAndTrimsInput(): void
    {
        $this->browser->jsonRequest('POST', '/api/books', [
            'inv_number' => ' LIB-0042 ', 'title' => ' Книга ', 'author' => ' Автор ',
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = json_decode($this->browser->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $data);
        self::assertIsInt($data['id']);
        self::assertGreaterThan(0, $data['id']);

        $this->entityManager->clear();
        $book = $this->entityManager->find(Book::class, $data['id']);
        self::assertInstanceOf(Book::class, $book);
        self::assertSame('LIB-0042', $book->invNumber());
        self::assertSame('Книга', $book->title());
        self::assertSame('Автор', $book->author());
    }

    #[DataProvider('blankFields')]
    public function testRejectsBlankRequiredField(string $field): void
    {
        $payload = ['inv_number' => 'LIB-0042', 'title' => 'Книга', 'author' => 'Автор'];
        $payload[$field] = '   ';
        $repository = $this->entityManager->getRepository(Book::class);
        $countBefore = $repository->count([]);

        $this->browser->jsonRequest('POST', '/api/books', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'message' => 'Ошибка валидации',
            'errors' => [['field' => $field, 'message' => 'Не может быть пустым']],
        ], json_decode($this->browser->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));
        self::assertSame($countBefore, $repository->count([]));
    }

    public static function blankFields(): iterable
    {
        foreach (['inv_number', 'title', 'author'] as $field) {
            yield $field => [$field];
        }
    }

    public function testRejectsDuplicateInventoryNumberWithoutCreatingBook(): void
    {
        $book = new Book('LIB-0042', 'Книга', 'Автор');
        $this->entityManager->persist($book);
        $this->entityManager->flush();
        $bookId = $book->id();
        $repository = $this->entityManager->getRepository(Book::class);
        $countBefore = $repository->count([]);

        $this->browser->jsonRequest('POST', '/api/books', [
            'inv_number' => 'LIB-0042', 'title' => 'Другая книга', 'author' => 'Другой автор',
        ]);

        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'message' => "Книга с таким инвентарным номером 'LIB-0042' уже существует",
            'errors' => [],
        ], json_decode($this->browser->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));

        $this->entityManager->clear();
        self::assertSame($countBefore, $repository->count([]));
        $saved = $this->entityManager->find(Book::class, $bookId);
        self::assertInstanceOf(Book::class, $saved);
        self::assertSame('Книга', $saved->title());
    }

    public function testAllowsAnotherCopyOfSameWork(): void
    {
        $first = new Book('LIB-0042', 'Книга', 'Автор');
        $this->entityManager->persist($first);
        $this->entityManager->flush();
        $firstId = $first->id();

        $this->browser->jsonRequest('POST', '/api/books', [
            'inv_number' => 'LIB-0043', 'title' => 'Книга', 'author' => 'Автор',
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($this->browser->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotSame($firstId, $data['id']);
        $this->entityManager->clear();
        $second = $this->entityManager->find(Book::class, $data['id']);
        self::assertInstanceOf(Book::class, $second);
        self::assertSame('LIB-0043', $second->invNumber());
        self::assertSame('Книга', $second->title());
    }
}
