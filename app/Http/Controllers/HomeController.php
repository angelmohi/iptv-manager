<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     */
    public function index() : View
    {
        $cdnToken = null;
        $expiredDate = null;
        if (Storage::disk('local')->exists('cdn_token.txt')) {
            $cdnToken = trim(Storage::disk('local')->get('cdn_token.txt'));
            $timestamp = Storage::disk('local')->lastModified('cdn_token.txt');
            $expiredDate = Carbon::createFromTimestamp($timestamp)->addDay()->format('d/m/Y H:i:s');
        }

        return view('home', compact('cdnToken', 'expiredDate'));
    }
}
