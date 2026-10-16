<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Book\Entity\Book;
use App\Domain\Loan\Entity\Loan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Book> */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    public function save(Book $book): void
    {
        $this->getEntityManager()->persist($book);
        $this->getEntityManager()->flush();
    }

    /** @return Book[] */
    public function findAvailable(): array
    {
        $qb = $this->createQueryBuilder('b');

        $qb
            ->leftJoin(Loan::class, 'loan', 'WITH', 'loan.book = b AND loan.returned_at IS NULL')
            ->where('loan.id IS NULL')
        ;

        return $qb->getQuery()->getResult();
    }

    public function getList(): array
    {
        $qb = $this->createQueryBuilder('b');

        $qb
            ->select('b.id, b.inv_number, b.title, b.author')
            ->addSelect('CASE WHEN loan.id IS NULL THEN 1 ELSE 0 END AS available')
            ->leftJoin(Loan::class, 'loan', 'WITH', 'loan.book = b AND loan.returned_at IS NULL')
        ;

        return $qb->getQuery()->getArrayResult();
    }

    public function hasInvNumber(string $inv_number): bool
    {
        $qb = $this->createQueryBuilder('b');

        $qb
            ->select('COUNT(b.id)')
            ->where('b.inv_number = :inv_number')
            ->setParameter('inv_number', $inv_number);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
