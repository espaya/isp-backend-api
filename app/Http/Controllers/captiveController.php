<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class captiveController extends Controller
{
    public function captive(Request $request)
    {
        return view('captive.index', [
            'mac' => $request->mac,
            'ip' => $request->ip,
        ]);
    }
}
