<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Mark tour as completed for current user.
     */
    public function completeTour(): JsonResponse
    {
        $user = Auth::user();
        if ($user instanceof User) {
            $user->has_completed_tour = true;
            $user->save();
        }

        return response()->json(['success' => true]);
    }
}
