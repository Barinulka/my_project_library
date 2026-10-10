<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use App\Domain\Loan\Entity\Loan;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Loan> */
class LoanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Loan::class);
    }

    public function save(Loan $loan): void
    {
        $this->getEntityManager()->persist($loan);
        $this->getEntityManager()->flush();
    }

    public function findOpenedLoan(Book $book): ?Loan
    {
        $qb = $this->createQueryBuilder('l');

        $qb
            ->where('l.book = :book')
            ->andWhere('l.returned_at IS NULL')
            ->setParameter('book', $book);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function countOpenLoansByClient(Client $client): int
    {
        $qb = $this->createQueryBuilder('l');

        $qb
            ->select('COUNT(l.id)')
            ->where('l.client = :client')
            ->andWhere('l.returned_at IS NULL')
            ->setParameter('client', $client);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function hasOverdueLoansByClient(
        Client $client,
        DateTimeImmutable $now,
    ): bool
    {
        $qb = $this->createQueryBuilder('l');

        $qb
            ->select('COUNT(l.id)')
            ->where('l.client = :client')
            ->andWhere('l.returned_at IS NULL')
            ->andWhere('l.planned_return_at < :now')
            ->setParameter('client', $client)
            ->setParameter('now', $now);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
