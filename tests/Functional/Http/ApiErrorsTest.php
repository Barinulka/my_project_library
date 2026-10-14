<?php

declare(strict_types=1);

namespace App\Tests\Functional\Http;

use Symfony\Component\HttpKernel\KernelEvents;

final class ApiErrorsTest extends DatabaseWebTestCase
{
    public function testMalformedJsonReturnsBadRequest(): void
    {
        $this->browser->request('POST', '/api/clients', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: '{"name":');

        $this->assertError(400, 'Некорректный запрос');
    }

    public function testUnknownApiRouteReturnsJsonNotFound(): void
    {
        $this->browser->request('GET', '/api/does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = json_decode($this->browser->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['message', 'errors'], array_keys($data));
        self::assertIsString($data['message']);
        self::assertNotSame('', $data['message']);
        self::assertSame([], $data['errors']);
    }

    public function testUnexpectedFailureHidesInternalDetails(): void
    {
        // Сбой только в этом тесте, без специального маршрута в приложении.
        self::getContainer()->get('event_dispatcher')->addListener(
            KernelEvents::CONTROLLER,
            static function (): void {
                throw new \RuntimeException('Internal database credentials must not be exposed');
            },
        );

        $this->browser->request('GET', '/api/books/available');

        $this->assertError(500, 'Внутренняя ошибка сервера');
    }

    public function testMethodNotAllowedPreservesAllowHeader(): void
    {
        $this->browser->request('DELETE', '/api/clients');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('Allow', 'POST');
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = json_decode($this->browser->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['message', 'errors'], array_keys($data));
        self::assertSame([], $data['errors']);
    }

    private function assertError(int $status, string $message): void
    {
        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['message' => $message, 'errors' => []], json_decode(
            $this->browser->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR,
        ));
    }
}
