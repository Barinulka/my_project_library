<?php

declare(strict_types=1);

namespace App\Presentation\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReturnLoanRequest
{
    #[Assert\Positive(message: "Значение должно быть положительным числом")]
    public int $book_id;

    public function __construct(
        int $book_id,
    ) {
        $this->book_id = $book_id;
    }
}
