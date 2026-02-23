error_reporting(0); $log=file_get_contents(storage_path("logs/laravel.log")); echo substr($log, -6000);
