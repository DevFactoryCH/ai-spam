<?php

namespace DevFactory\AiSpam\SpamResponders;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlankPageResponder implements SpamResponder
{
    public function respond(Request $request, Closure $next): Response
    {
        return response('');
    }
}
