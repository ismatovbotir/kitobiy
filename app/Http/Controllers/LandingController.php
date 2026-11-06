<?php

namespace App\Http\Controllers;

use App\Support\CommunityStats;
use Illuminate\Contracts\View\View;

class LandingController extends Controller
{
    public function __invoke(): View
    {
        return view('landing', ['stats' => CommunityStats::current()]);
    }
}
