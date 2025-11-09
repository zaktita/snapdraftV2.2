<x-mail::message>
    # Image Generation Failed

    We're sorry, but we encountered an error while generating images for your project **{{ $projectName }}**.

    ## Error Details

    **Job Type:** {{ $jobType }}
    **Attempt:** {{ $attemptNumber }} of 3
    **Error:** {{ $errorMessage }}

    ## What happened?

    The image generation process failed after {{ $attemptNumber }} attempts. This could be due to:

    - Temporary API service issues
    - Rate limiting from our AI provider
    - Invalid or corrupted input data
    - Network connectivity problems

    ## What you can do

    <x-mail::button :url="$projectUrl">
        View Your Project
    </x-mail::button>

    You can try the following:

    1. **Wait and retry** - If this was a temporary issue, trying again in a few minutes may resolve it
    2. **Check your inputs** - Ensure your brand references and prompts are valid
    3. **Contact support** - If the problem persists, our team is here to help

    <x-mail::button :url="$supportUrl" color="secondary">
        Contact Support
    </x-mail::button>

    ---

    Thanks for using {{ config('app.name') }}!

    If you have any questions, please don't hesitate to reach out to our support team.
</x-mail::message>