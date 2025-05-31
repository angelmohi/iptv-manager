<?php

namespace App\Http\Controllers;

use App\Models\DownloadLog;
use Illuminate\Support\Facades\DB;
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
        $byRegion = DownloadLog::select(
                'region',
                DB::raw('COUNT(DISTINCT ip) as total')
            )
            ->where('country', 'ES')
            ->groupBy('region')
            ->orderByDesc('total')
            ->get();

        $byCity = DownloadLog::select(
                'city',
                DB::raw('COUNT(DISTINCT ip) as total')
            )
            ->where('country', 'ES')
            ->groupBy('city')
            ->orderByDesc('total')
            ->get();

        $last7 = DownloadLog::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(DISTINCT ip) as total')
            )
            ->where('country', 'ES')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $byList = DownloadLog::select(
                'list',
                DB::raw('COUNT(DISTINCT ip) AS total')
            )
            ->where('country', 'ES')
            ->groupBy('list')
            ->orderByDesc('total')
            ->get();

        return view('home', compact('byRegion', 'byCity', 'last7', 'byList'));
    }
}
