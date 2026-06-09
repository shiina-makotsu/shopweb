<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserProfileController extends Controller
{
    public function show(User $user): View
    {
        return view('users.show', [
            'profileUser' => $user
                ->load(['addresses' => fn ($query) => $query->where('is_visible', true)->latest()])
                ->loadCount(['productComments', 'forumThreads']),
        ]);
    }
}
