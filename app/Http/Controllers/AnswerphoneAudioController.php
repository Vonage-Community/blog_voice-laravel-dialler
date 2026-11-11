<?php

namespace App\Http\Controllers;

use App\Models\VonageSetting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnswerphoneAudioController extends Controller
{
    /**
     * Serve the uploaded answerphone greeting so Vonage can stream it to callers.
     */
    public function __invoke(): StreamedResponse
    {
        $settings = VonageSetting::current();

        abort_unless($settings->hasAnswerphoneGreeting() && Storage::disk('local')->exists((string) $settings->answerphone_path), 404);

        return Storage::disk('local')->response((string) $settings->answerphone_path, 'greeting.mp3', [
            'Content-Type' => 'audio/mpeg',
        ]);
    }
}
