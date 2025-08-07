<?php

namespace CapsuleCmdr\SeatOsmm\Http\Controllers;

use Illuminate\Routing\Controller;

class HomeOverrideController extends Controller
{
    public function index()
    {
        $homeElements = collect(config('osmm.home_elements', []))->sortBy('order');

        return view('seat-osmm::home', compact('homeElements'));
    }
}
