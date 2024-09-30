<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

class ConfigController extends Controller
{
    public function initial()
    {
        return response()->json([
            'user' => auth()->user()
        ], 201);
    }
}
