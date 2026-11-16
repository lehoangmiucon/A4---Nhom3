<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SwaggerTokenRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        // Swagger tests roles with Bearer tokens, independently of any web login.
        // Removing cookies and the frontend indicators before Sanctum's stateful
        // middleware means this request cannot authenticate via a browser session.
        if ($request->header('X-Helpdesk-Client') !== 'swagger-ui') {
            return $next($request);
        }
        $request->cookies->replace([]);
        foreach (['cookie', 'origin', 'referer'] as $header) {
            $request->headers->remove($header);
        }

        // Sanctum must validate the supplied personal access token even when a
        // web guard has already resolved a user in this request's application.
        $sessionGuards = config('sanctum.guard');
        config(['sanctum.guard' => []]);
        try {
            return $next($request);
        } finally {
            config(['sanctum.guard' => $sessionGuards]);
        }
    }
}
