<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /** Chequeo de salud: confirma que el servidor y la base de datos responden. */
    public function __invoke()
    {
        DB::select('select 1');

        return response()->json(['status' => 'ok']);
    }
}
