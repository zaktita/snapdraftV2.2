<x-mail::message>
# Beta Feedback Received

**From:** {{ $userName }} ({{ $userEmail }})
**Rating:** {{ $rating }}/10
**Category:** {{ $category }}

---

## Message

{{ $feedbackMessage }}

---

*Reply directly to this email to respond to {{ $userName }}.*
</x-mail::message>
