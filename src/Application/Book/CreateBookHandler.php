<?php

declare(strict_types=1);

namespace App\Application\Book;

use App\Application\Book\Exception\BookInvNumberAlreadyExistsException;
use App\Domain\Book\Entity\Book;
use App\Infrastructure\Persistence\Doctrine\BookRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class CreateBookHandler
{
    public function __construct(
        private BookRepository $repository,
    ) {
    }

    /**
     * @throws UniqueConstraintViolationException
     */
    public function handle(string $inv_number, string $title, string $author): Book
    {
        if ($this->repository->hasInvNumber($inv_number)) {
            throw new BookInvNumberAlreadyExistsException("Книга с таким инвентарным номером '$inv_number' уже существует");
        }

        $book = new Book($inv_number, $title, $author);

        try {
            $this->repository->save($book);
        } catch(UniqueConstraintViolationException $exception) {
            if (!str_contains($exception->getMessage(), '"uniq_cbe5a331c229a244"')) {
                throw $exception;
            }

            throw new BookInvNumberAlreadyExistsException(
                "Книга с таким инвентарным номером '$inv_number' уже существует",
                previous: $exception,
            );
        }

        return $book;
    }
}
