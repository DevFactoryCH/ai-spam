<?php

namespace DevFactory\AiSpam\Http\Middleware;

use Closure;
use DevFactory\AiSpam\Events\SpamDetected;
use DevFactory\AiSpam\Honeypot;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the honeypot pre-filter on classic (non-Livewire) form posts.
 */
class ProtectAgainstSpam
{
    public function __construct(protected Honeypot $honeypot) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        $reason = $this->honeypot->check($request->all());

        if ($reason === null) {
            return $next($request);
        }

        event(new SpamDetected($reason, $request));

        return app(config('ai-spam.honeypot.respond_to_spam_with'))->respond($request, $next);
    }
}
