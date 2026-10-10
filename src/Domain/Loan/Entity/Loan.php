<?php

declare(strict_types=1);

namespace App\Domain\Loan\Entity;

use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Loan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: Book::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Book $book;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $loan_at;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $planned_return_at;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $returned_at = null;

    public function __construct(
        Client $client,
        Book $book,
        DateTimeImmutable $loan_at,
        DateTimeImmutable $planned_return_at
    ) {
        if ($planned_return_at < $loan_at) {
            throw new \InvalidArgumentException('Срок возврата не может быть раньше даты выдачи');
        }

        $this->client = $client;
        $this->book = $book;
        $this->loan_at = $loan_at;
        $this->planned_return_at = $planned_return_at;
    }

    public function returned(): void
    {
        if ($this->returned_at() !== null) {
            throw new \RuntimeException('Книга уже возвращена');
        }

        $this->returned_at = new DateTimeImmutable('now');
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function client(): Client
    {
        return $this->client;
    }

    public function book(): Book
    {
        return $this->book;
    }

    public function loan_at(): DateTimeImmutable
    {
        return $this->loan_at;
    }

    public function planned_return_at(): DateTimeImmutable
    {
        return $this->planned_return_at;
    }

    public function returned_at(): ?DateTimeImmutable
    {
        return $this->returned_at;
    }
}
