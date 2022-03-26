<?php

namespace App\Http\Controllers;

use App\Models\Image;
use Illuminate\Http\Request;

class HomeController extends Controller
{

    public function home()
    {
        $stats = ['nr_processed' => 0, 'nr_unprocessed' => 0];

        $stats['nr_processed'] = Image::whereNotNull('processed_at')->count();
        $stats['nr_unprocessed'] = Image::where('processed_at')->count();
        $stats['unprocessable_images'] = Image::where('nr_times_processed', '>', '3')->orderBy('created_at', 'desc')->limit(50)->get();

        return view('home', compact('stats'));
    }
}
