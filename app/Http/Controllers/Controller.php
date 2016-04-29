<?php

namespace App\Http\Controllers;

use App\Models\Tasks;
use App\Models\Users;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    public function index(Request $request)
    {
        if ($request->input('date')) {
            $date = Carbon::parse($request->input('date'));
        } else {
            $date = Carbon::now();
        }

        $dateStart = $date->copy()->startOfMonth();
        $dateEnd = $date->copy()->endOfMonth();
        
        // Get this months developer stats
        $userMonthly = Users::monthlyStats($dateStart, $dateEnd);
        $taskMonthly = Tasks::monthlyStats($dateStart, $dateEnd);
        
        return view('stats')->with(['date' => $dateStart, 'monthlyUserStats' => $userMonthly, 'monthlyTaskStats' => $taskMonthly]);
    }
}
