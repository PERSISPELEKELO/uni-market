<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A real (1x1) PNG upload that needs no GD extension. Pass $kilobytes to pad it to a given size.
 */
function fakePhoto(string $name = 'photo.png', int $kilobytes = 1): File
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    return UploadedFile::fake()->createWithContent($name, str_pad($png, max(strlen($png), $kilobytes * 1024), "\0"));
}

/**
 * A genuine $width x $height PNG, for tests exercising a minimum-dimension
 * rule (e.g. student ID photos) - unlike fakePhoto(), this needs GD.
 */
function fakeIdPhoto(string $name = 'id-card.png', int $width = 900, int $height = 900): File
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 200, 200));

    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);

    return UploadedFile::fake()->createWithContent($name, $png);
}

/**
 * A genuine JPEG carrying a real, readable EXIF segment (not just bytes
 * that look like one) - for proving a storage pipeline actually strips
 * EXIF, rather than merely never having added any in the first place.
 */
function fakeIdPhotoWithExif(string $name = 'id-card-with-location.jpg', int $width = 900, int $height = 900): File
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 200, 200));

    ob_start();
    imagejpeg($image, null, 90);
    $jpegBytes = ob_get_clean();
    imagedestroy($image);

    // A minimal TIFF/EXIF structure with a single tag (Orientation = 1),
    // spliced in right after the SOI marker.
    $tiff = "\x49\x49\x2A\x00\x08\x00\x00\x00"
        ."\x01\x00"
        ."\x12\x01\x03\x00\x01\x00\x00\x00\x01\x00\x00\x00"
        ."\x00\x00\x00\x00";
    $exifPayload = "Exif\0\0".$tiff;
    $app1 = "\xFF\xE1".pack('n', strlen($exifPayload) + 2).$exifPayload;

    $withExif = substr($jpegBytes, 0, 2).$app1.substr($jpegBytes, 2);

    return UploadedFile::fake()->createWithContent($name, $withExif);
}
