<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\DownloadLog;
use Carbon\Carbon;
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

        $start = Carbon::today()->subDays(6)->startOfDay();
        $end   = Carbon::today()->endOfDay();

        $accessDates = collect();
        for ($dt = $start->copy(); $dt->lte($end); $dt->addDay()) {
            $accessDates->push($dt->toDateString());
        }

        $accessRaw = DownloadLog::select(
                DB::raw('DATE(created_at) as date'),
                'account_id',
                DB::raw('COUNT(DISTINCT ip) as unique_count')
            )
            ->where('country', 'ES')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date', 'account_id')
            ->orderBy('date')
            ->get();

        $accountIds = $accessRaw->pluck('account_id')->unique()->filter();
        $listNames  = Account::whereIn('id', $accountIds)
                      ->pluck('name', 'id'); // [ account_id => name ]

        $accessDatasets = [];
        foreach ($listNames as $accountId => $name) {
            $data = $accessDates->map(function ($d) use ($accessRaw, $accountId) {
                $row = $accessRaw
                    ->first(fn($r) => $r->date === $d && $r->account_id == $accountId);
                return $row ? (int) $row->unique_count : 0;
            })->all();

            $color = '#'.substr(md5($name), 0, 6);
            $accessDatasets[] = [
                'label'               => $name,
                'data'                => $data,
                'borderColor'         => $color,
                'backgroundColor'     => 'transparent',
                'fill'                => false,
                'tension'             => 0.2,
                'pointBackgroundColor'=> $color,
            ];
        }

        return view('home', [
            'last7'            => $last7,
            'byList'           => $byList,
            'accessDates'      => $accessDates->toArray(),
            'accessDatasets'   => $accessDatasets,
        ]);
    }
}
