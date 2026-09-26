<?php

namespace App\Http\Controllers;

use App\Models\VerificationDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * Stream a privately stored verification document to an authorized viewer.
     */
    public function show(VerificationDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        $disk = Storage::disk(VerificationDocument::DISK);

        abort_unless($disk->exists($document->path), 404);

        return $disk->response($document->path, $document->original_name, [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
