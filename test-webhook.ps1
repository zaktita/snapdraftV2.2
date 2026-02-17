# Test Lemon Squeezy Webhook Locally
# This script sends test webhook payloads to your local application

param(
    [Parameter(Mandatory=$false)]
    [string]$Event = "subscription_created",
    
    [Parameter(Mandatory=$false)]
    [string]$Url = "http://localhost:8000/webhook/lemonsqueezy"
)

Write-Host "=== Lemon Squeezy Webhook Tester ===" -ForegroundColor Cyan
Write-Host ""

# Check if test payload exists
$payloadPath = "tests/webhooks/$Event.json"

if (-not (Test-Path $payloadPath)) {
    Write-Host "ERROR: Payload file not found: $payloadPath" -ForegroundColor Red
    Write-Host "Available test payloads:" -ForegroundColor Yellow
    Get-ChildItem "tests/webhooks/*.json" | ForEach-Object { Write-Host "  - $($_.BaseName)" }
    exit 1
}

# Read payload
$payload = Get-Content $payloadPath -Raw

Write-Host "Event: $Event" -ForegroundColor Green
Write-Host "URL: $Url" -ForegroundColor Green
Write-Host "Payload: $payloadPath" -ForegroundColor Green
Write-Host ""

# Send request
Write-Host "Sending webhook..." -ForegroundColor Yellow

try {
    $response = Invoke-WebRequest -Uri $Url `
        -Method POST `
        -ContentType "application/json" `
        -Headers @{
            "X-Signature" = "test_signature_for_local_testing"
        } `
        -Body $payload `
        -UseBasicParsing

    Write-Host ""
    Write-Host "✅ SUCCESS" -ForegroundColor Green
    Write-Host "Status: $($response.StatusCode)" -ForegroundColor Green
    Write-Host "Response:" -ForegroundColor Cyan
    Write-Host $response.Content
} catch {
    Write-Host ""
    Write-Host "❌ FAILED" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "Response:" -ForegroundColor Yellow
        Write-Host $responseBody
    }
    exit 1
}

Write-Host ""
Write-Host "Check application logs:" -ForegroundColor Cyan
Write-Host "  tail -f storage/logs/laravel.log | grep 'Lemon Squeezy'" -ForegroundColor White
