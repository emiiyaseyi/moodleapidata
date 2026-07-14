<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('_log_api_started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $query = $request->query();
        unset($query['wstoken'], $query['token']);

        $startedAt = $request->attributes->get('_log_api_started_at', microtime(true));

        ApiRequestLog::create([
            'api_consumer_id' => $request->user()?->getAuthIdentifier(),
            'method' => $request->method(),
            'path' => $request->path(),
            'query_params' => $query,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
