<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin UI entrypoint.
 * The UI is optional and is intended to be provided by the host application.
 * This endpoint is a minimal bootstrap surface that can later return an
 * Inertia response once the package ships full UI pages.
 */
final readonly class SsoAdminHomeController
{
    public function __invoke(Request $request): Response
    {
        return response()->json([
          'message' => 'SSO Admin UI scaffold',
        ]);
    }
}
