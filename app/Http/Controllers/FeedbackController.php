<?php

namespace App\Http\Controllers;

use App\Models\FeedbackSubmission;
use App\Mail\FeedbackMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('feedback');
    }

    public function thankYou(): Response
    {
        return Inertia::render('feedback-thank-you');
    }

    public function submit(Request $request)
    {
        $request->validate([
            'rating'   => ['required', 'integer', 'min:1', 'max:10'],
            'category' => ['required', 'string', 'in:Bug Report,Feature Request,General Feedback,UX / Design'],
            'message'  => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $user = Auth::user();

        try {
            FeedbackSubmission::create([
                'user_id' => $user->id,
                'rating' => (int) $request->rating,
                'category' => (string) $request->category,
                'message' => (string) $request->message,
            ]);
        } catch (\Throwable $e) {
            Log::error('Feedback DB save failed', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            Mail::to(config('app.feedback_email', 'contact@snapdraft.com'))
                ->send(new FeedbackMail(
                    userName: $user->name,
                    userEmail: $user->email,
                    rating: (int) $request->rating,
                    category: $request->category,
                    feedbackMessage: $request->message,
                ));
        } catch (\Throwable $e) {
            Log::error('Feedback email send failed', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('feedback.thank-you');
    }

}
