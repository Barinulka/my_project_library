<?php

declare(strict_types=1);

namespace App\Presentation\Http\EventListener;

use App\Application\Book\Exception\BookInvNumberAlreadyExistsException;
use App\Application\Client\Exception\ClientEmailAlreadyExistsException;
use App\Application\Loan\Exception\LoanIssueRejectedException;
use App\Application\Loan\Exception\LoanReturnRejectedException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();

        if ($path !== '/api' && !str_starts_with($path, '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        $message = 'Внутренняя ошибка сервера';
        $headers = [];
        $errors = [];

        if (
            $exception instanceof LoanIssueRejectedException
            || $exception instanceof LoanReturnRejectedException
            || $exception instanceof ClientEmailAlreadyExistsException
            || $exception instanceof BookInvNumberAlreadyExistsException
        ) {
            $status = Response::HTTP_CONFLICT;
            $message = $exception->getMessage();
        } elseif ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $headers = $exception->getHeaders();

            // Технические подробности ошибок сервера не раскрываем.
            if ($status < 500) {
                $message = match ($status) {
                    Response::HTTP_BAD_REQUEST => 'Некорректный запрос',
                    Response::HTTP_UNSUPPORTED_MEDIA_TYPE =>
                    'Неподдерживаемый формат запроса',
                    default => $exception->getMessage() ?: (Response::$statusTexts[$status] ?? 'Ошибка запроса'),
                };
            }
        }

        $validationException = $this->findValidationException($exception);

        if ($validationException !== null && $status === 422) {
            $message = 'Ошибка валидации';

            foreach ($validationException->getViolations() as $violation) {
                $errors[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }
        }

        $event->setResponse(new JsonResponse(['message' => $message, 'errors' => $errors,], $status, $headers));
    }

    private function findValidationException(Throwable $exception,): ?ValidationFailedException
    {
        do {
            if ($exception instanceof ValidationFailedException) {
                return $exception;
            }

            $exception = $exception->getPrevious();
        } while ($exception !== null);

        return null;
    }
}
