<?php

namespace App\Http\Middleware;

use App\Models\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Read-only impersonation: support can look at everything in a merchant's app but change nothing. */
class BlockReadOnlyImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->attributes->get('impersonation');
        if ($session instanceof Impersonation && $session->mode !== 'write' && ! $request->isMethodSafe()) {
            return response()->json(['message' => 'Read-only support session: changes are disabled.'], 403);
        }

        return $next($request);
    }
}
