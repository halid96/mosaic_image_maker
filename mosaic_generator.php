<?php

function createMosaicPortrait($imageFolder, $outputFile, $requiredCount = 40)
{
    $portraitWidth = 1242;
    $portraitHeight = 2688;
    $columns = 5;
    $rows = 8;

    if (($columns * $rows) !== $requiredCount) {
        throw new Exception("Grid does not match required image count.");
    }

    $images = glob(rtrim($imageFolder, "/") . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE);
    if (!$images || count($images) < $requiredCount) {
        $found = is_array($images) ? count($images) : 0;
        throw new Exception("Not enough images. Found {$found}, required {$requiredCount}.");
    }

    // Keep output reproducible but not always the same first 40.
    sort($images);
    shuffle($images);
    $images = array_slice($images, 0, $requiredCount);

    // Square tile size (requested): based on width so all 5 columns fit.
    $tileSize = (int) floor($portraitWidth / $columns); // 248

    // Distribute remaining area as spacing (tolerance accepted for fill).
    $remainingX = max(0, $portraitWidth - ($columns * $tileSize));
    $remainingY = max(0, $portraitHeight - ($rows * $tileSize));
    $gapX = $remainingX / max(1, $columns + 1);
    $gapY = $remainingY / max(1, $rows + 1);

    $filterComplex = '';

    // Convert each input to exactly square tile.
    for ($i = 0; $i < $requiredCount; $i++) {
        $filterComplex .= "[{$i}:v]scale={$tileSize}:{$tileSize}:force_original_aspect_ratio=increase,crop={$tileSize}:{$tileSize},setsar=1[img{$i}];";
    }

    // Dark background canvas.
    $filterComplex .= "color=c=black:s={$portraitWidth}x{$portraitHeight}:d=1[base];";

    $prevLabel = 'base';
    for ($i = 0; $i < $requiredCount; $i++) {
        $row = (int) floor($i / $columns);
        $col = $i % $columns;
        $x = (int) round($gapX + ($col * ($tileSize + $gapX)));
        $y = (int) round($gapY + ($row * ($tileSize + $gapY)));
        $nextLabel = ($i === ($requiredCount - 1)) ? 'out' : "tmp{$i}";
        $filterComplex .= "[{$prevLabel}][img{$i}]overlay={$x}:{$y}[{$nextLabel}];";
        $prevLabel = $nextLabel;
    }

    $inputs = '';
    foreach ($images as $image) {
        $inputs .= '-i ' . escapeshellarg($image) . ' ';
    }

    $outputDir = dirname($outputFile);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    if (!is_writable($outputDir)) {
        throw new Exception("Output directory is not writable: {$outputDir}");
    }

    $command = get_ffmpeg_path()
        . " {$inputs}"
        . "-filter_complex " . escapeshellarg($filterComplex)
        . " -map '[out]' -frames:v 1 -update 1 -q:v 2 -y " . escapeshellarg($outputFile)
        . " 2>&1";

    exec($command, $ffmpegOutput, $returnCode);
    if ($returnCode !== 0 || !file_exists($outputFile)) {
        throw new Exception("FFmpeg failed while generating mosaic: " . implode("\n", $ffmpegOutput));
    }

    return $outputFile;
}


try {
    $imageFolder = PROJECT_PATH . '/selected_user_profile_pics';
    // Web server user (_www) can write here in this project setup.
    $outputFile = PROJECT_PATH . '/backend/storage/ugc/temp/mosaic.jpg';

    $result = createMosaicPortrait($imageFolder, $outputFile);
    echo "Mosaic created successfully: $result\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
