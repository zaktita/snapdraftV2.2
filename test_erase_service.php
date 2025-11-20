<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\CanvasEditorService;

function makeImage($w=384,$h=384){
    $img=imagecreatetruecolor($w,$h);$bg=imagecolorallocate($img,40,90,150);imagefilledrectangle($img,0,0,$w,$h,$bg);$white=imagecolorallocate($img,255,255,255);imagefilledellipse($img,$w/2,$h/2,160,160,$white);ob_start();imagepng($img);$data=ob_get_clean();imagedestroy($img);return base64_encode($data);} 
function makeMask($w=384,$h=384){$m=imagecreatetruecolor($w,$h);$black=imagecolorallocate($m,0,0,0);$white=imagecolorallocate($m,255,255,255);imagefilledrectangle($m,0,0,$w,$h,$black);imagefilledellipse($m,$w/2,$h/2,160,160,$white);ob_start();imagepng($m);$data=ob_get_clean();imagedestroy($m);return base64_encode($data);} 

$imageBase64 = makeImage();
$maskBase64 = makeMask();

$service = app(CanvasEditorService::class);
$prompt = 'Erase the white masked region and fill with seamless background.';

try {
    $resultBase64 = $service->inpaint($imageBase64, $maskBase64, $prompt);
    file_put_contents(__DIR__.'/erase_service_result.png', base64_decode($resultBase64));
    echo "Saved erase_service_result.png (".strlen($resultBase64)." chars)\n";
} catch (Throwable $e){
    echo "ERROR: ".$e->getMessage()."\n";
}
