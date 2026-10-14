<?php

declare(strict_types=1);

namespace App\Tests\Functional\Http;

use App\Domain\Client\Entity\Client;
use PHPUnit\Framework\Attributes\DataProvider;

final class CreateClientControllerTest extends DatabaseWebTestCase
{
    public function testCreatesClientAndTrimsInput(): void
    {
        $this->browser->jsonRequest('POST', '/api/clients', [
            'name' => ' Анна ',
            'email' => ' anna@example.com ',
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = json_decode($this->browser->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $data);
        self::assertIsInt($data['id']);
        self::assertGreaterThan(0, $data['id']);

        $this->entityManager->clear();
        $client = $this->entityManager->find(Client::class, $data['id']);
        self::assertInstanceOf(Client::class, $client);
        self::assertSame('Анна', $client->name());
        self::assertSame('anna@example.com', $client->email());
    }

    #[DataProvider('invalidRequests')]
    public function testRejectsInvalidInput(array $payload, string $field, string $message): void
    {
        $repository = $this->entityManager->getRepository(Client::class);
        $countBefore = $repository->count([]);

        $this->browser->jsonRequest('POST', '/api/clients', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'message' => 'Ошибка валидации',
            'errors' => [['field' => $field, 'message' => $message]],
        ], json_decode($this->browser->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));
        self::assertSame($countBefore, $repository->count([]));
    }

    public static function invalidRequests(): iterable
    {
        yield 'blank name' => [['name' => '   ', 'email' => 'anna@example.com'], 'name', 'Не может быть пустым'];
        yield 'blank email' => [['name' => 'Анна', 'email' => '   '], 'email', 'Не может быть пустым'];
        yield 'invalid email' => [['name' => 'Анна', 'email' => 'abc'], 'email', 'Некорректный email'];
    }

    public function testRejectsRegisteredEmailWithoutCreatingClient(): void
    {
        $client = new Client('Анна', 'anna@example.com');
        $this->entityManager->persist($client);
        $this->entityManager->flush();
        $clientId = $client->id();
        $repository = $this->entityManager->getRepository(Client::class);
        $countBefore = $repository->count([]);

        $this->browser->jsonRequest('POST', '/api/clients', [
            'name' => 'Другой читатель', 'email' => 'anna@example.com',
        ]);

        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'message' => "Клиент с таким email 'anna@example.com' уже зарегистрирован",
            'errors' => [],
        ], json_decode($this->browser->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));

        $this->entityManager->clear();
        self::assertSame($countBefore, $repository->count([]));
        $saved = $this->entityManager->find(Client::class, $clientId);
        self::assertInstanceOf(Client::class, $saved);
        self::assertSame('Анна', $saved->name());
    }
}
