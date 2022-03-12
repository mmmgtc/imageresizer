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


        return view('home', compact('stats'));
    }
}
