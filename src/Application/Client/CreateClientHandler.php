<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Application\Client\Exception\ClientEmailAlreadyExistsException;
use App\Domain\Client\Entity\Client;
use App\Infrastructure\Persistence\Doctrine\ClientRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class CreateClientHandler
{
    public function __construct(
        private ClientRepository $repository,
    ) {
    }

    /**
     * @throws UniqueConstraintViolationException
     */
    public function handle(string $name, string $email): Client
    {
        if ($this->repository->hasEmail($email)) {
            throw new ClientEmailAlreadyExistsException("Клиент с таким email '$email' уже зарегистрирован");
        }

        $client = new Client(name: $name, email: $email);

        try {
            $this->repository->save($client);
        } catch (UniqueConstraintViolationException $exception) {
            if (!str_contains($exception->getMessage(), '"uniq_c7440455e7927c74"')) {
                throw $exception;
            }

            throw new ClientEmailAlreadyExistsException(
                "Клиент с таким email '$email' уже зарегистрирован",
                previous: $exception,
            );
        }

        return $client;
    }
}
