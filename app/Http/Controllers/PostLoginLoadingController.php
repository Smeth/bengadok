<?php

namespace App\Http\Controllers;

use App\Support\PostLoginRedirect;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostLoginLoadingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $target = PostLoginRedirect::resolveStoredTarget($request, $user);

        return Inertia::render('auth/PostLoginLoading', [
            'redirectTo' => $target,
        ]);
    }
}
