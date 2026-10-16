<?php

declare(strict_types = 1);

namespace App\Presentation\Http\Response\Book;

final readonly class BookResponse
{
    public function __construct(
        public int $id,
        public string $inv_number,
        public string $title,
        public string $author,
        public bool $available
    ) {
    }
}
