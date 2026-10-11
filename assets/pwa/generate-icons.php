<?php
/**
 * PWA Icon Generator
 * Run this script once to generate all required PWA icon sizes from the logo
 * 
 * Usage: php generate-icons.php
 * Or visit: http://localhost/dumaran_hiring/assets/pwa/generate-icons.php
 */

// Icon sizes required for PWA
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

// Source logo path
$sourceLogo = __DIR__ . '/../logo.png';

// Check if GD library is available
if (!extension_loaded('gd')) {
    die('GD library is not installed. Please install php-gd extension.');
}

// Check if source exists
if (!file_exists($sourceLogo)) {
    die('Source logo not found at: ' . $sourceLogo);
}

// Get source image info
$sourceInfo = getimagesize($sourceLogo);
if ($sourceInfo === false) {
    die('Unable to read source image.');
}

// Create source image based on type
$sourceImage = null;
switch ($sourceInfo['mime']) {
    case 'image/png':
        $sourceImage = imagecreatefrompng($sourceLogo);
        break;
    case 'image/jpeg':
        $sourceImage = imagecreatefromjpeg($sourceLogo);
        break;
    case 'image/gif':
        $sourceImage = imagecreatefromgif($sourceLogo);
        break;
    default:
        die('Unsupported image format: ' . $sourceInfo['mime']);
}

if (!$sourceImage) {
    die('Failed to load source image.');
}

$sourceWidth = imagesx($sourceImage);
$sourceHeight = imagesy($sourceImage);

echo "<h2>PWA Icon Generator</h2>";
echo "<p>Source: {$sourceLogo} ({$sourceWidth}x{$sourceHeight})</p>";
echo "<hr>";

$generated = 0;
$failed = 0;

foreach ($sizes as $size) {
    $outputPath = __DIR__ . "/icon-{$size}x{$size}.png";
    
    // Create new image with transparent background
    $newImage = imagecreatetruecolor($size, $size);
    
    // Enable alpha blending
    imagealphablending($newImage, false);
    imagesavealpha($newImage, true);
    
    // Fill with transparent background
    $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
    imagefill($newImage, 0, 0, $transparent);
    
    // Calculate dimensions to maintain aspect ratio and center
    $ratio = min($size / $sourceWidth, $size / $sourceHeight);
    $newWidth = (int)($sourceWidth * $ratio);
    $newHeight = (int)($sourceHeight * $ratio);
    $x = (int)(($size - $newWidth) / 2);
    $y = (int)(($size - $newHeight) / 2);
    
    // Resize and copy
    imagealphablending($newImage, true);
    $result = imagecopyresampled(
        $newImage, $sourceImage,
        $x, $y, 0, 0,
        $newWidth, $newHeight, $sourceWidth, $sourceHeight
    );
    
    if ($result) {
        // Save as PNG
        imagesavealpha($newImage, true);
        if (imagepng($newImage, $outputPath, 9)) {
            echo "<p style='color: green;'>✓ Generated: icon-{$size}x{$size}.png</p>";
            $generated++;
        } else {
            echo "<p style='color: red;'>✗ Failed to save: icon-{$size}x{$size}.png</p>";
            $failed++;
        }
    } else {
        echo "<p style='color: red;'>✗ Failed to resize for: {$size}x{$size}</p>";
        $failed++;
    }
    
    imagedestroy($newImage);
}

// Clean up
imagedestroy($sourceImage);

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p>Generated: {$generated} icons</p>";
echo "<p>Failed: {$failed} icons</p>";

if ($generated > 0) {
    echo "<p style='color: green; font-weight: bold;'>PWA icons are ready!</p>";
}
?>

