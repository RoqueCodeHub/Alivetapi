<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    protected function invalidJson($request, ValidationException $exception): JsonResponse
    {
        return ApiResponse::error(
            'Los datos enviados no son válidos',
            $exception->status,
            $exception->errors()
        );
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($this->isApiRequest($request)) {
            return ApiResponse::error('No autenticado', 401);
        }

        return redirect()->guest($exception->redirectTo() ?? '/login');
    }

    public function render($request, Throwable $e)
    {
        if ($this->isApiRequest($request)) {
            if ($e instanceof ValidationException) {
                return $this->invalidJson($request, $e);
            }

            if ($e instanceof AuthenticationException) {
                return $this->unauthenticated($request, $e);
            }

            if ($e instanceof AuthorizationException) {
                return ApiResponse::error('No autorizado', 403);
            }

            if ($e instanceof StockException) {
                return ApiResponse::error($e->getMessage(), 422);
            }

            if ($e instanceof ModelNotFoundException) {
                return ApiResponse::error('Recurso no encontrado', 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                return ApiResponse::error(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Error HTTP',
                    $e->getStatusCode()
                );
            }

            $message = config('app.debug')
                ? $e->getMessage()
                : 'Error interno del servidor';

            return ApiResponse::error($message, 500);
        }

        return parent::render($request, $e);
    }

    private function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
