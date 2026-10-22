<?php

namespace App\Http\Controllers;

use App\Traits\EncryptTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ToolController extends Controller
{

    use EncryptTrait;

    public function getS3File(Request $request, $token)
    {
        header('Access-Control-Allow-Origin: *');
        try {
            $token = $this->decryptKey($token);
            $fileInfo = explode(",",$token);
            $filePath = $fileInfo[0];
            $fileName = $fileInfo[1];
            $pathInfo = pathinfo($filePath);

            if (!Storage::disk('s3')->exists($filePath)) {
                return response()->json(['error' => 'File not found'], 404);
            }

            /** @var \Illuminate\Filesystem\FilesystemManager $disk */
            $disk = Storage::disk('s3');
            $mime = $disk->mimeType($filePath);
            $extension = !empty($pathInfo['extension']) ? "." . $pathInfo['extension'] : '';
            if ($request->query('mode') !== 'download') {
                $stream = $disk->getDriver()->readStream($filePath);

                return response()->stream(
                    function () use ($stream) {
                        fpassthru($stream);
                        fclose($stream);
                    },
                    200,
                    [
                        'Content-Type' => $mime,
                        'Content-Disposition' => 'inline; filename="' . $fileName . $extension . '"',
                    ]
                );
            } else {
                $file = $disk->get($filePath);
                return response($file, 200)
                    ->header('Content-Type', $mime)
                    ->header('Content-Disposition', 'attachment; filename="' . $fileName . $extension . '"');
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token or file not found'], 404);
        }
    }

    public function getS3Photo(Request $request, $token)
    {
        header('Access-Control-Allow-Origin: *');
        try {
            $secretKey = config('app.encrypt_key');
            $token = $this->decryptDataURL($token, $secretKey);
            $fileInfo = explode(",",$token);
            $filePath = $fileInfo[0];
            $fileName = $fileInfo[1];
            $pathInfo = pathinfo($filePath);

            if (!Storage::disk('s3')->exists($filePath)) {
                return response()->json(['error' => 'File not found'], 404);
            }

            /** @var \Illuminate\Filesystem\FilesystemManager $disk */
            $disk = Storage::disk('s3');
            $mime = $disk->mimeType($filePath);
            $extension = !empty($pathInfo['extension']) ? "." . $pathInfo['extension'] : '';
            if ($request->query('mode') !== 'download') {
                $stream = $disk->getDriver()->readStream($filePath);

                return response()->stream(
                    function () use ($stream) {
                        fpassthru($stream);
                        fclose($stream);
                    },
                    200,
                    [
                        'Content-Type' => $mime,
                        'Content-Disposition' => 'inline; filename="' . $fileName . $extension . '"',
                    ]
                );
            } else {
                $file = $disk->get($filePath);
                return response($file, 200)
                    ->header('Content-Type', $mime)
                    ->header('Content-Disposition', 'attachment; filename="' . $fileName . $extension . '"');
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token or file not found'], 404);
        }
    }
}
