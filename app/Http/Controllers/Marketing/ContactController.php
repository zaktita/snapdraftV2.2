<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('website.contact');
    }

    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        try {
            Mail::to(config('app.feedback_email', 'contact@snapdraft.com'))
                ->send(new ContactMessageMail(
                    senderName: $validated['name'],
                    senderEmail: $validated['email'],
                    contactMessage: $validated['message'],
                ));
        } catch (\Throwable $e) {
            Log::error('Contact message failed to send', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'message' => 'Something went wrong sending your message. Please try again or email us directly.',
            ])->withInput();
        }

        return back()->with('success', true);
    }
}
