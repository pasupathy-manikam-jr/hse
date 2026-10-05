<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhotoController extends Controller
{
    /**
     * Serve a private photo to anyone who may open the record it belongs to.
     */
    public function __invoke(Request $request, Photo $photo): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $record = $photo->photoable;
        abort_unless($record !== null && method_exists($record, 'canBeViewedBy') && $record->canBeViewedBy($user), 404);

        return Storage::disk('local')->response($photo->path, headers: ['Cache-Control' => 'private, max-age=86400']);
    }
}
