<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Intervention\Image\ImageManager;

class ImageController extends Controller
{
    /**
     * Takes a .gif url, width, height and quality as URL parameters.
     * Fetches the image and resizes according to the passed parameters.
     */
    public function process(Request $request)
    {
        $this->validate($request, [
            'url' => 'required',
            'width' => 'sometimes|numeric|max:2000|min:1',
            'height' => 'sometimes|numeric|max:2000|min:1',
            'quality' => 'sometimes|numeric|max:100|min:10',
        ]);

        $this->ensureDirectoriesExist();

        $url = urldecode($request->url);
        $imageId = md5($url);

        $manager = new ImageManager('imagick');

        $originalLocation = storage_path() . '/app/images/original/' . $imageId . '.gif';

        error_log('originalLocation: ' . $originalLocation);

        if (!file_exists($originalLocation)) {
            // Save a local copy
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url, ['sink' => $originalLocation]);
        }

        $cacheLocation = storage_path() . '/app/images/resized/' . $imageId . 'w-' . intval($request->width) . 'h-' . intval($request->height) . 'q-' . intval($request->quality ? $request->quality : 100) . '.gif';

        $this->resizeImageFromOriginalIfNeeded($manager, $request, $originalLocation, $cacheLocation);
        return $this->serveImageFromCache($cacheLocation);
    }

    private function resizeImageFromOriginalIfNeeded($manager, Request $request, $originalLocation, $cacheLocation)
    {
        if (!file_exists($cacheLocation)) {
            $image = $manager->make($originalLocation);
            if ($request->has('width') && $request->has('height')) {
                $image->fit(intval($request->width), intval($request->height));
            } else if ($request->has('width')) {
                $image->scale(width: intval($request->width));
            } else if ($request->has('height')) {
                $image->scale(height: intval($request->height));
            }
            $image->toGif($request->quality ? $request->quality : 100)->save($cacheLocation);
        }
    }

    private function serveImageFromCache($cacheLocation)
    {
        $file = File::get($cacheLocation);
        $type = File::mimeType($cacheLocation);
        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);
        $response->header("Cache-Control", " public, max-age=29030400, immutable");
        return $response;
    }

    private function ensureDirectoriesExist()
    {
        if (!File::exists(storage_path() . '/app/images')) {
            File::makeDirectory(storage_path() . '/app/images');
        }
        if (!File::exists(storage_path() . '/app/images/original')) {
            File::makeDirectory(storage_path() . '/app/images/original');
        }
        if (!File::exists(storage_path() . '/app/images/resized')) {
            File::makeDirectory(storage_path() . '/app/images/resized');
        }
    }
}
