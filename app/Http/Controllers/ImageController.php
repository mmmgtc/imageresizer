<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class ImageController extends Controller
{
    private $cacheExpiryInMinutes = 10080;

    // Process an image from a URL, /image-from-url?width=30&height=30&url=https://cdn.discordapp.com/attachments/906238436028059698/915897767832997898/hey.gif
    public function processFromURL(Request $request)
    {
        $this->validate($request, [
            'url' => 'required',
            'width' => 'sometimes|numeric|max:2000|min:1',
            'height' => 'sometimes|numeric|max:2000|min:1',
            'quality' => 'sometimes|numeric|max:100|min:10',
        ]);

        $this->ensureDirectoriesExist();

        $manager = new ImageManager('imagick');

        $imageId = md5($request->url);
        $originalLocation = storage_path() . '/app/images/original/' . $imageId . '.gif';

        if (!file_exists($originalLocation)) {
            // Save a local copy
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $request->url, ['sink' => $originalLocation]);
        }

        $cacheLocation = storage_path() . '/app/images/resized/' . $imageId . 'w-' . intval($request->width) . 'h-' . intval($request->height) . 'q-' . intval($request->quality ? $request->quality : 100) . '.gif';

        $this->resizeImageFromOriginalIfNeeded($manager, $request, $originalLocation, $cacheLocation);
        return $this->serveImageFromCache($cacheLocation);
    }

    // Process an image from an IPFs ID
    public function process(Request $request, $ipfsid)
    {
        $this->validate($request, [
            'width' => 'sometimes|numeric|max:2000|min:1',
            'height' => 'sometimes|numeric|max:2000|min:1',
            'quality' => 'sometimes|numeric|max:100|min:10',
        ]);

        $this->ensureDirectoriesExist();

        $url = 'https://' . $ipfsid . '.ipfs.dweb.link';

        $manager = new ImageManager('imagick');

        $originalLocation = storage_path() . '/app/images/original/' . $ipfsid . '.gif';

        if (!file_exists($originalLocation)) {
            // Save a local copy
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url, ['sink' => $originalLocation]);
        }

        $cacheLocation = storage_path() . '/app/images/resized/' . $ipfsid . 'w-' . intval($request->width) . 'h-' . intval($request->height) . 'q-' . intval($request->quality ? $request->quality : 100) . '.gif';

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
