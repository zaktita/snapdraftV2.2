<?php

namespace App\Http\Controllers;

use App\Mail\FeedbackMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('feedback');
    }

    public function submit(Request $request)
    {
        $request->validate([
            'rating'   => ['required', 'integer', 'min:1', 'max:10'],
            'category' => ['required', 'string', 'in:Bug Report,Feature Request,General Feedback,UX / Design'],
            'message'  => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $user = Auth::user();

        Mail::to(config('app.feedback_email', 'contact@snapdraft.com'))
            ->send(new FeedbackMail(
                userName: $user->name,
                userEmail: $user->email,
                rating: (int) $request->rating,
                category: $request->category,
                feedbackMessage: $request->message,
            ));

        return redirect()->route('feedback')->with('success', 'Thanks for your feedback! We really appreciate it.');
    }
}
