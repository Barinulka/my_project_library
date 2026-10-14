<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Client\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Client> */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function save(Client $client): void
    {
        $this->getEntityManager()->persist($client);
        $this->getEntityManager()->flush();
    }

    public function hasEmail(string $email): bool
    {
        $qb = $this->createQueryBuilder('c');

        $qb
            ->select('COUNT(c.id)')
            ->where('c.email = :email')
            ->setParameter('email', $email);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
