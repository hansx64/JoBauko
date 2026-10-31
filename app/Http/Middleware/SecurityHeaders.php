<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers. HTML pages get a Content Security Policy that
 * only runs scripts carrying this request's nonce (Vite and Ziggy add it),
 * so injected markup cannot execute code.
 */
class SecurityHeaders
{
    /** Web fonts used by the layout. */
    private const FONT_HOST = 'https://fonts.bunny.net';

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Files (PDF previews, downloads) keep the browser's own viewers working.
        if (str_contains((string) $headers->get('Content-Type'), 'text/html')) {
            $headers->set('Content-Security-Policy', $this->policy($nonce));
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $dev = $this->viteDevServer();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$dev],
            // React and the chart library set inline style attributes.
            'style-src' => ["'self'", "'unsafe-inline'", self::FONT_HOST, ...$dev],
            'font-src' => ["'self'", self::FONT_HOST, 'data:'],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => ["'self'", ...$dev, ...array_map(fn (string $origin) => preg_replace('/^http/', 'ws', $origin), $dev)],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directives)->map(fn (array $sources, string $name) => $name.' '.implode(' ', $sources))->implode('; ');
    }

    /**
     * The Vite dev server origin while `npm run dev` is running locally.
     *
     * @return list<string>
     */
    private function viteDevServer(): array
    {
        $hot = public_path('hot');

        if (app()->isProduction() || ! File::exists($hot)) {
            return [];
        }

        $origin = rtrim(trim(File::get($hot)), '/');

        return $origin === '' ? [] : [$origin];
    }
}
