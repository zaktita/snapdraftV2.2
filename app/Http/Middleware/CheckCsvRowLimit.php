<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCsvRowLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass limit checks in local/testing environments
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Check if CSV file is uploaded (field name is 'csv_file')
        if ($request->hasFile('csv_file')) {
            $csv = $request->file('csv_file');
            $rows = $this->countCsvRows($csv);

            if (!SubscriptionService::canUploadCsvRows($user, $rows)) {
                $limits = SubscriptionService::getTierLimits($user->subscription_tier);
                $tierName = SubscriptionService::getTierDisplayName($user->subscription_tier);

                return back()->with('error', 
                    "Your CSV has {$rows} rows, but your {$tierName} allows up to {$limits['csv_max_rows']} rows. Please upgrade or reduce the number of rows."
                );
            }
        }

        // Check inline data rows
        if ($request->has('inline_data')) {
            $inlineData = $request->input('inline_data');
            if (is_array($inlineData)) {
                $rows = count($inlineData);

                if (!SubscriptionService::canUploadCsvRows($user, $rows)) {
                    $limits = SubscriptionService::getTierLimits($user->subscription_tier);
                    $tierName = SubscriptionService::getTierDisplayName($user->subscription_tier);

                    return back()->with('error', 
                        "Your data has {$rows} rows, but your {$tierName} allows up to {$limits['csv_max_rows']} rows. Please upgrade or reduce the number of rows."
                    );
                }
            }
        }

        return $next($request);
    }

    /**
     * Count rows in CSV file.
     */
    private function countCsvRows($file): int
    {
        $handle = fopen($file->getRealPath(), 'r');
        $rowCount = 0;

        // Skip header
        fgetcsv($handle);

        while (fgetcsv($handle) !== false) {
            $rowCount++;
        }

        fclose($handle);

        return $rowCount;
    }
}
