<?php

namespace App\Http\Middleware;

use App\Models\GpsDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateGpsDevice
{
    /**
     * Identify the tracker from its "Authorization: Bearer <token>" header and
     * make it available to the request as the `gpsDevice` attribute.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $device = filled($token) ? GpsDevice::findByToken($token) : null;

        if ($device === null) {
            return response()->json(['message' => 'Unknown or missing device token.'], 401);
        }

        $request->attributes->set('gpsDevice', $device);

        return $next($request);
    }
}
