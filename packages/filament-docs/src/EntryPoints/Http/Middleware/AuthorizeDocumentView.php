<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\EntryPoints\Http\Middleware;

use AIArmada\FilamentDocs\Support\DocPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeDocumentView
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(DocPermissions::allows(DocPermissions::DOCUMENT, 'view'), 403);

        return $next($request);
    }
}
