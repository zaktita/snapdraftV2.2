<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

// Create simple test image and mask (center erase)
function makeImage($w=384,$h=384){
    $img=imagecreatetruecolor($w,$h);$bg=imagecolorallocate($img,30,120,200);imagefilledrectangle($img,0,0,$w,$h,$bg);$white=imagecolorallocate($img,255,255,255);imagefilledellipse($img,$w/2,$h/2,140,140,$white);ob_start();imagepng($img);$data=ob_get_clean();imagedestroy($img);return 'data:image/png;base64,'.base64_encode($data);} 
function makeMask($w=384,$h=384){$m=imagecreatetruecolor($w,$h);$black=imagecolorallocate($m,0,0,0);$white=imagecolorallocate($m,255,255,255);imagefilledrectangle($m,0,0,$w,$h,$black);imagefilledellipse($m,$w/2,$h/2,160,160,$white);ob_start();imagepng($m);$data=ob_get_clean();imagedestroy($m);return 'data:image/png;base64,'.base64_encode($data);} 

$image = makeImage();
$mask  = makeMask();
$prompt = 'Erase the circular region and reconstruct background seamlessly.';

$response = Http::post('http://127.0.0.1:8000/api/erase-image',[
    'image'=>$image,
    'mask'=>$mask,
    'prompt'=>$prompt,
]);

if(!$response->successful()){
    echo "ERROR: ".$response->status()."\n".$response->body();
    exit(1);
}
$data=$response->json();
file_put_contents(__DIR__.'/erase_original.png', base64_decode(substr($image,strpos($image,',')+1)));
file_put_contents(__DIR__.'/erase_generated.png', base64_decode(substr($data['generatedImage'],strpos($data['generatedImage'],',')+1)));
file_put_contents(__DIR__.'/erase_composite.png', base64_decode(substr($data['compositeImage'],strpos($data['compositeImage'],',')+1)));

echo "Saved erase_original.png, erase_generated.png, erase_composite.png\n";
print_r([
    'gap'=>$data['gap'],
    'origW'=>$data['originalWidth'],
    'genW'=>$data['generatedWidth'],
    'compW'=>$data['compositeWidth'],
    'height'=>$data['height'],
]);
