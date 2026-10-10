<?php

declare(strict_types=1);

namespace App\Presentation\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class IssueLoanRequest
{
    #[Assert\Positive(message: "Значение должно быть положительным числом")]
    public int $client_id;
    #[Assert\Positive(message: "Значение должно быть положительным числом")]
    public int $book_id;

    public function __construct(
        int $client_id,
        int $book_id
    ) {
        $this->client_id = $client_id;
        $this->book_id = $book_id;
    }
}
