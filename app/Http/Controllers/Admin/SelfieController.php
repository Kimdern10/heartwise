<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VerificationRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SelfieController extends Controller
{
    public function __invoke(VerificationRequest $verificationRequest): StreamedResponse
    {
        $path = $verificationRequest->selfie_path;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'no-store',
        ]);
    }
}
