<?php

namespace App\Http\Controllers;

use App\Events\ImageAddedEvent;
use App\Models\Image;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        $imageId = $this->getImageIdentifier($request->url);

        // Where the original image will be stored
        $storeLocation = '/app/images/original/' . $imageId . '.gif';

        // Ensure we have an original image to work with or pause until there is one.  Prevent multiple requests for the same image.
        $this->downloadOriginalImageOrWait(urldecode($request->url), $storeLocation, 60);
        if (!file_exists(storage_path() . $storeLocation)) {
            throw new Exception("Original image does not exist for " . $request->url, 1);
        }

        $image = Image::where('url', urldecode($request->url))
            ->where('width', $request->width)
            ->where('height', $request->height)
            ->where('quality', $request->quality)->first();

        if ($image) {
            if ($image->processed_at) {
                // Serve the processed (resized) image
                return $this->serveImage($image->path);
            } else {
                // Serve the original
                return $this->serveImage($storeLocation);
            }
        } else {
            // Initialize the image for processing and return the original until we have a resized image
            $image = new Image();
            $image->url = urldecode($request->url);
            $image->width = $request->width ?? $request->width;
            $image->height = $request->height ?? $request->height;
            $image->quality = $request->quality ?? $request->quality;
            $image->save();

            ImageAddedEvent::dispatch($image);

            // Serve the original
            return $this->serveImage($storeLocation);
        }


        // Serve the original
        return $this->serveImage($storeLocation);
    }

    /**
     * Run through and process all the images that have not yet been resized.  Ensure the function doesn't try and process in parallel
     */
    public function processUnresizedImages($lockTimeout = 3)
    {
        $cacheName = 'ImageController::processUnresizedImages';
        Cache::forget($cacheName);
        if (!Cache::has($cacheName)) {
            Cache::put($cacheName, true, $lockTimeout);

            // process some images
            $images = Image::whereNull('processed_at')->whereNull('processing_at')->get();
            foreach ($images as $key => $image) {

                $imageId = $this->getImageIdentifier($image->url);

                $this->resizeImageFromOriginalIfNeeded($image);
            }

            Cache::forget($cacheName);
        } else {
            return;
        }
    }

    /**
     * Gets a unique representation of the image, based on the original image url
     */
    private function getImageIdentifier($url)
    {
        return md5(urldecode($url));
    }

    /**
     * Download $url and store it as storeLocation, waiting up to $seconds for concurrent requests to complete and retrying $retries times
     */
    private function downloadOriginalImageOrWait($url, $storeLocation, $seconds = 60, $retries = 3)
    {
        $cacheName = 'ImageController::downloadOriginalImageOrWait-' . md5($storeLocation);

        for ($i = 1; $i <= $retries; $i++) {
            try {
                if (!Cache::has($cacheName)) {
                    // Get a local copy of the image to work with asap and ensure multiple requests to download a local copy don't happen
                    Cache::put($cacheName, true, 30);
                    $client = new \GuzzleHttp\Client();
                    $response = $client->request('GET', $url, ['sink' => storage_path() . $storeLocation, 'synchronous' => true]);
                    Cache::forget($cacheName);
                } else {
                    // Wait until we have an original message
                    for ($ii = 1; $ii <= $seconds; $ii++) {
                        if (!Cache::has($cacheName)) {
                            break;
                        }
                        sleep(1);
                    }
                }
                break;
            } catch (Exception $e) {
            }
        }
    }

    private function resizeImageFromOriginalIfNeeded(Image $image)
    {
        $manager = new ImageManager('imagick');

        $imageId = $this->getImageIdentifier($image->url);
        $cacheLocation = '/app/images/resized/' . $imageId . 'w-' . intval($image->width) . 'h-' . intval($image->height) . 'q-' . intval($image->quality ? $image->quality : 100) . '.gif';

        $originalLocation = storage_path() . '/app/images/original/' . $imageId . '.gif';

        if (!file_exists($cacheLocation)) {
            try {
                $image->processing_at = Carbon::now();
                $image->save();

                $imageMaker = $manager->make($originalLocation);
                if ($image->width && $image->height) {
                    $imageMaker->pad(intval($image->width), intval($image->height));
                } else if ($image->width) {
                    $imageMaker->scale(width: intval($image->width));
                } else if ($image->height) {
                    $imageMaker->scale(height: intval($image->height));
                }
                $imageMaker->toGif($image->quality ? $image->quality : 100)->save(storage_path() . $cacheLocation);

                $image->nr_times_processed += 1;
                $image->path = $cacheLocation;
                $image->processed_at = Carbon::now();
                $image->size_in_kb = intval(filesize(storage_path() . $cacheLocation) / 1024);
                $image->save();
            } catch (\Exception $e) {
                $image->nr_times_processed += 1;
                $image->save();

                throw new Exception('Unable to resize ' . $image->url . ' with id ' . $image->id, 1);
            }
        }
    }

    /**
     * Serve up a specific image.  The path should not include the storage_path()
     */
    private function serveImage($path, $cacheInSeconds = "29030400")
    {
        $file = File::get(storage_path() . $path);
        $type = File::mimeType(storage_path() . $path);
        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);
        $response->header("Cache-Control", " public, max-age=" . $cacheInSeconds . ", immutable");
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
