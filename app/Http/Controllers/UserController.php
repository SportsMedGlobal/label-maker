<?php

namespace App\Http\Controllers;

use App\Models\Tasks;
use App\Models\Users;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class UserController extends BaseController
{
    public function index(Request $request, $userId)
    {
        $user = Users::findOrFail($userId);
        $graphStats = ['labels' => [], 'crs_failed' => [], 'testing_failed' => [], 'completed' => []];
        $graphData = $user->graphStats();
        foreach ($graphData as $row) {
            $graphStats['labels'][] = $row->month_string;
            $graphStats['crs_failed'][] = $row->crs_failed;
            $graphStats['testing_failed'][] = $row->testing_failed;
            $graphStats['completed'][] = $row->completed;
        }

        return view('profile')->with(['user' => $user, 'stats' => $user->userStats(), 'graphStats' => $graphStats]);
        
        
    }

    public function store(Request $request, $userId)
    {
        $user = Users::findOrFail($userId);

    }
}
