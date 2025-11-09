<?php

namespace App\Exceptions;

use Exception;

class InsufficientCreditsException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'Insufficient credits to perform this operation', int $code = 403, ?\Throwable $previous = null)
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
                'error' => 'INSUFFICIENT_CREDITS',
                'details' => 'You need more credits to generate images. Please upgrade your plan or purchase additional credits.'
            ], $this->getCode());
        }

        return redirect()->route('subscription.plans')->with('error', $this->getMessage());
    }
}
