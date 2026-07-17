<x-mail::message>
# You're in - here's your beta code

Thanks for applying. Use this invite code when you create your SnapDraft account:

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

<x-mail::button :url="$registerUrl">
Create your account
</x-mail::button>

If the button does not work, copy this link into your browser:

{{ $registerUrl }}

This code is single-use and may expire - please register soon.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
