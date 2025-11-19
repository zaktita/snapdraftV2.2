<?php
// Quick smoke test for CanvasEditorService methods (inpaint, outpaint, generateFromPrompt)
// Creates synthetic images + masks, invokes service, saves output PNGs to storage/app/tmp.

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\CanvasEditorService;
use Illuminate\Support\Facades\Storage;

$service = app(CanvasEditorService::class);

function ensureTmpDir(): string {
    $path = storage_path('app/tmp');
    if (!is_dir($path)) {
        @mkdir($path, 0777, true);
    }
    return $path;
}

function makeBase64Png(callable $draw, int $w = 256, int $h = 256): string {
    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, true);
    imagesavealpha($img, true);
    $bg = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $w, $h, $bg);
    $draw($img, $w, $h);
    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    imagedestroy($img);
    return base64_encode($data);
}

$tmpDir = ensureTmpDir();

// Original image: simple gradient rectangle
$originalBase64 = makeBase64Png(function($img, $w, $h) {
    for ($y=0; $y<$h; $y++) {
        $c = imagecolorallocate($img, (int)(255*$y/$h), 64, 180);
        imageline($img, 0, $y, $w, $y, $c);
    }
}, 384, 384);

// Mask for inpaint/generate: white circle center
$maskBase64 = makeBase64Png(function($img, $w, $h) {
    $black = imagecolorallocate($img, 0,0,0);
    imagefilledrectangle($img, 0,0,$w,$h,$black);
    $white = imagecolorallocate($img, 255,255,255);
    imagefilledellipse($img, $w/2, $h/2, $w/2, $h/2, $white);
}, 384, 384);

// Expanded canvas (outpaint) - place original centered on larger white canvas
$expandedBase64 = makeBase64Png(function($img, $w, $h) use ($originalBase64) {
    $origData = base64_decode($originalBase64);
    $orig = imagecreatefromstring($origData);
    $ow = imagesx($orig); $oh = imagesy($orig);
    $ox = (int)(($w - $ow)/2); $oy = (int)(($h - $oh)/2);
    imagecopy($img, $orig, $ox, $oy, 0,0,$ow,$oh);
    imagedestroy($orig);
}, 640, 640);

// Mask for outpaint: white borders, black where original lives
$outpaintMaskBase64 = makeBase64Png(function($img, $w, $h) use ($originalBase64) {
    $white = imagecolorallocate($img, 255,255,255);
    imagefilledrectangle($img,0,0,$w,$h,$white);
    $origData = base64_decode($originalBase64);
    $orig = imagecreatefromstring($origData);
    $ow = imagesx($orig); $oh = imagesy($orig);
    $ox = (int)(($w - $ow)/2); $oy = (int)(($h - $oh)/2);
    $black = imagecolorallocate($img,0,0,0);
    imagefilledrectangle($img,$ox,$oy,$ox+$ow,$oy+$oh,$black);
    imagedestroy($orig);
}, 640, 640);

function savePng(string $base64, string $tmpDir, string $label): string {
    $path = $tmpDir . '/' . $label . '-' . time() . '.png';
    file_put_contents($path, base64_decode($base64));
    return $path;
}

try {
    echo "Inpainting test...\n";
    $inpainted = $service->inpaint($originalBase64, $maskBase64, 'Fill the circle with a detailed blue flower');
    $inpaintPath = savePng($inpainted, $tmpDir, 'inpaint');
    echo "Saved inpainted: $inpaintPath\n";
} catch (Throwable $e) {
    echo "Inpaint error: " . $e->getMessage() . "\n";
}

try {
    echo "Outpainting test...\n";
    $outpainted = $service->outpaint($expandedBase64, $outpaintMaskBase64, 'Extend the scene with natural landscape and soft sky gradients');
    $outpaintPath = savePng($outpainted, $tmpDir, 'outpaint');
    echo "Saved outpainted: $outpaintPath\n";
} catch (Throwable $e) {
    echo "Outpaint error: " . $e->getMessage() . "\n";
}

try {
    echo "Prompt generate test...\n";
    $generated = $service->generateFromPrompt($originalBase64, $maskBase64, 'Replace the circle with a crystal sphere reflecting desert dunes');
    $genPath = savePng($generated, $tmpDir, 'prompt');
    echo "Saved prompt-generated: $genPath\n";
} catch (Throwable $e) {
    echo "Generate error: " . $e->getMessage() . "\n";
}

echo "Done. Check storage/app/tmp for outputs.\n";
