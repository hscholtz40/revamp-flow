<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Illuminate\View\ViewException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Handle missing Laravel framework CSS files gracefully
        if ($e instanceof \ErrorException && 
            str_contains($e->getMessage(), 'Failed to open stream') && 
            str_contains($e->getMessage(), 'styles.css')) {
            
            // Return a simple error page without the problematic CSS
            return response()->view('errors::minimal', [
                'exception' => $e,
                'message' => 'Application error occurred. Please try again.',
            ], 500);
        }

        // Handle ViewException that might contain the CSS error
        if ($e instanceof ViewException && 
            str_contains($e->getMessage(), 'styles.css')) {
            
            return response()->view('errors::minimal', [
                'exception' => $e,
                'message' => 'Application error occurred. Please try again.',
            ], 500);
        }

        return parent::render($request, $e);
    }

    /**
     * Render the given HttpException.
     */
    protected function renderHttpException(HttpException $e)
    {
        // Override to use our custom error view for all HTTP exceptions
        if ($e->getStatusCode() >= 500) {
            return response()->view('errors::minimal', [
                'exception' => $e,
                'message' => 'Server error occurred. Please try again.',
            ], $e->getStatusCode());
        }

        return parent::renderHttpException($e);
    }
}