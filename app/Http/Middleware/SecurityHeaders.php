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
        } elseif ($this->isInlineCdrPreview($request, $response)
            || $this->isInlineJapicFinalPreview($request, $response)
            || $this->isEmbeddedJapicDraftPreview($request, $response)
            || $this->isInlineFeaPreview($request, $response)
            || $this->isInlinePswdoDocumentPreview($request, $response)) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
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

    private function isInlineCdrPreview(Request $request, Response $response): bool
    {
        if (! $request->user()
            || ! $request->routeIs('cdr.documents.preview', 'cdr.draft.preview')
            || ! $response->isSuccessful()) {
            return false;
        }

        $contentType = strtolower(trim(explode(';', (string) $response->headers->get('Content-Type'), 2)[0]));
        if ($contentType === 'text/html') {
            return true;
        }
        if (! in_array($contentType, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            return false;
        }

        $disposition = strtolower(trim(explode(';', (string) $response->headers->get('Content-Disposition'), 2)[0]));

        return $disposition === 'inline';
    }

    private function isInlineJapicFinalPreview(Request $request, Response $response): bool
    {
        if (! $request->user()
            || ! $request->routeIs('japic.certifications.document-versions.preview')
            || ! $response->isSuccessful()) {
            return false;
        }

        $contentType = strtolower(trim(explode(';', (string) $response->headers->get('Content-Type'), 2)[0]));
        $disposition = strtolower(trim(explode(';', (string) $response->headers->get('Content-Disposition'), 2)[0]));

        return $contentType === 'application/pdf' && $disposition === 'inline';
    }

    private function isEmbeddedJapicDraftPreview(Request $request, Response $response): bool
    {
        return $request->user() !== null
            && $request->routeIs('japic.certifications.preview')
            && $request->boolean('embedded')
            && $response->isSuccessful()
            && str_starts_with(strtolower((string) $response->headers->get('Content-Type')), 'text/html');
    }

    private function isInlineFeaPreview(Request $request, Response $response): bool
    {
        if (! $request->user()
            || ! $request->routeIs(
                'fea.documents.draft.preview',
                'japic.fea.documents.versions.preview',
                'pswdo.fea.documents.versions.preview',
            )
            || ! $response->isSuccessful()) {
            return false;
        }

        $contentType = strtolower(trim(explode(';', (string) $response->headers->get('Content-Type'), 2)[0]));
        if ($request->routeIs('fea.documents.draft.preview')) {
            return $contentType === 'text/html';
        }

        $disposition = strtolower(trim(explode(';', (string) $response->headers->get('Content-Disposition'), 2)[0]));

        return in_array($contentType, ['application/pdf', 'image/jpeg', 'image/png'], true)
            && $disposition === 'inline';
    }

    private function isInlinePswdoDocumentPreview(Request $request, Response $response): bool
    {
        if (! $request->user()
            || ! $request->routeIs(
                'ib39.pswdo-enrollment-documents.preview',
                'japic.pswdo-enrollment-documents.preview',
            )
            || ! $response->isSuccessful()) {
            return false;
        }

        $contentType = strtolower(trim(explode(';', (string) $response->headers->get('Content-Type'), 2)[0]));
        $disposition = strtolower(trim(explode(';', (string) $response->headers->get('Content-Disposition'), 2)[0]));

        return $contentType === 'application/pdf' && $disposition === 'inline';
    }
}
