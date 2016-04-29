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

        return view('profile')->with(['user' => $user, 'stats' => $user->userStats()]);
        
        
    }

    public function store(Request $request, $userId)
    {
        $user = Users::findOrFail($userId);

    }
}
