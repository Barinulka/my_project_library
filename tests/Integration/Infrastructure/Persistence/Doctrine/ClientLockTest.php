<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ClientLockTest extends KernelTestCase
{
    public function testClientLockIsHeldUntilTransactionEnds(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $first = $entityManager->getConnection();

        // Те же параметры тестовой базы, но отдельное соединение.
        $second = DriverManager::getConnection($first->getParams());

        $clientId = null;

        try {
            // Без BEGIN: запись сразу фиксируется и видна обоим.
            $clientId = (int) $first->fetchOne(
                'INSERT INTO client (name, email)
                 VALUES (:name, :email) RETURNING id',
                [
                    'name' => 'Проверка блокировки',
                    'email' => bin2hex(random_bytes(8)).'@example.com',
                ],
            );

            $first->beginTransaction();

            $first->fetchOne('SELECT id FROM client WHERE id = ? FOR UPDATE', [$clientId],);

            $second->beginTransaction();

            // Ограничиваем ожидание, чтобы тест не завис.
            $second->executeStatement("SET LOCAL lock_timeout = '200ms'");

            $lockTimeoutOccurred = false;

            try {
                $second->fetchOne('SELECT id FROM client WHERE id = ? FOR UPDATE', [$clientId],);
            } catch (DriverException $exception) {
                self::assertSame('55P03', $exception->getSQLState());
                $lockTimeoutOccurred = true;
            }

            self::assertTrue($lockTimeoutOccurred, 'Второе соединение не должно получить занятую блокировку',);

            // После ошибки PostgreSQL требует откат этой транзакции.
            $second->rollBack();

            // Освобождаем блокировку первого соединения.
            $first->commit();

            // Теперь второе соединение должно получить её успешно.
            $second->beginTransaction();
            $second->executeStatement("SET LOCAL lock_timeout = '200ms'");

            $lockedClientId = $second->fetchOne('SELECT id FROM client WHERE id = ? FOR UPDATE', [$clientId],);

            self::assertSame($clientId, (int) $lockedClientId);
        } finally {
            if ($second->isTransactionActive()) {
                $second->rollBack();
            }

            if ($first->isTransactionActive()) {
                $first->rollBack();
            }

            if ($clientId !== null) {
                $first->executeStatement('DELETE FROM client WHERE id = ?', [$clientId],);
            }

            $second->close();
        }
    }
}
