<?php

namespace App\Exceptions;

use Exception;

class FileUploadException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'File upload failed', int $code = 400, ?\Throwable $previous = null)
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
                'error' => 'FILE_UPLOAD_FAILED',
                'details' => 'Please check the file type, size, and try again.'
            ], $this->getCode());
        }

        return redirect()->back()->with('error', $this->getMessage())->withInput();
    }
}
