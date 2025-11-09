<?php

namespace App\Exceptions;

use Exception;

class AIServiceUnavailableException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'AI service is currently unavailable', int $code = 503, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render($request)
    {
        if ($request->expectsJson() || $request->inertia()) {
            return response()->json([
                'message' => $this->getMessage(),
                'error' => 'AI_SERVICE_UNAVAILABLE',
                'details' => 'Please check your AI service configuration or try again later.'
            ], $this->getCode());
        }

        return redirect()->back()->with('error', $this->getMessage());
    }
}
