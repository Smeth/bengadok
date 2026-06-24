<?php

namespace App\Http\Responses;

use App\Support\AuthRedirectPaths;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $target = AuthRedirectPaths::resolveDestination($request);
        $separator = str_contains($target, '?') ? '&' : '?';

        return redirect($target.$separator.'verified=1');
    }
}
