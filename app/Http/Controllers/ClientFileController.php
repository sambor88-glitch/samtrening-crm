<?php

namespace App\Http\Controllers;

use App\Domain\Clients\Models\ClientFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way to a file: a link signed for fourteen days, which a client opens without an
 * account, or a logged-in trainer who is allowed to see that client. Nothing sits under a public
 * URL, and an expired link is simply a wrong one.
 */
class ClientFileController extends Controller
{
    public function __invoke(Request $request, ClientFile $file): StreamedResponse
    {
        if (! $request->hasValidSignature()) {
            abort_unless($request->user()?->can('view', $file->client), 403);
        }

        return Storage::disk('local')->download($file->path, $file->name);
    }
}
