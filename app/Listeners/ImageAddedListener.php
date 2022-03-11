<?php

namespace App\Listeners;

use App\Events\ImageAddedEvent;
use App\Http\Controllers\ImageController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ImageAddedListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\ImageAddedEvent  $event
     * @return void
     */
    public function handle(ImageAddedEvent $event)
    {
        $imageController = new ImageController();
        $imageController->processUnresizedImages();
    }
}
