<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class WrapWriteRequestsInTransaction
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        return DB::transaction(fn() => $next($request));
    }
}
