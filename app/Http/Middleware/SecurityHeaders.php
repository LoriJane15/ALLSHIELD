<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers the legacy app set in its root .htaccess. Apache is no longer
 * in the picture (artisan serve / nginx), so they are applied here instead.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($this->isInlineRcspEvidence($request, $response)) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        } else {
            $response->headers->set('X-Frame-Options', 'DENY');
        }
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // X-XSS-Protection is deprecated and unsafe in older browsers; modern
        // guidance is to disable the auditor and rely on Blade escaping + CSP.
        $response->headers->set('X-XSS-Protection', '0');

        return $response;
    }

    private function isInlineRcspEvidence(Request $request, Response $response): bool
    {
        if (! $request->user() || ! $request->routeIs('rcsp.evidence') || ! $response->isSuccessful()) {
            return false;
        }

        $contentType = strtolower(trim(explode(';', (string) $response->headers->get('Content-Type'), 2)[0]));
        if (! in_array($contentType, [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ], true)) {
            return false;
        }

        $disposition = strtolower(trim(explode(';', (string) $response->headers->get('Content-Disposition'), 2)[0]));

        return $disposition === 'inline';
    }
}
