<?php

namespace App\Http\Controllers;

use App\Enums\CallEventType;
use App\Models\CallEvent;
use App\Models\VonageSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VonageWebhookController extends Controller
{
    /**
     * Return the NCCO that tells Vonage how to handle a new call.
     *
     * Calls placed from the browser carry a `from_user`; everything else is an inbound PSTN call.
     */
    public function answer(Request $request): JsonResponse
    {
        $settings = VonageSetting::current();

        if ($request->filled('from_user')) {
            return $this->outbound($request, $settings);
        }

        return $this->inbound($request, $settings);
    }

    /**
     * Record call status updates from Vonage in the console.
     */
    public function event(Request $request): Response
    {
        $status = $request->string('status')->toString();

        if ($status !== '' && $request->filled('uuid')) {
            CallEvent::create([
                'type' => CallEventType::Status,
                'call_uuid' => $request->input('uuid'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'status' => $status,
                'message' => sprintf('Call %s', str_replace('_', ' ', $status)),
            ]);
        }

        return response()->noContent();
    }

    /**
     * Connect a call placed from the browser phone out to the dialled number.
     */
    private function outbound(Request $request, VonageSetting $settings): JsonResponse
    {
        $destination = preg_replace('/[^0-9]/', '', $request->string('to')->toString());

        CallEvent::create([
            'type' => CallEventType::Outbound,
            'call_uuid' => $request->input('uuid'),
            'from' => $settings->vonage_number,
            'to' => $destination,
            'message' => sprintf('Dialling %s', $destination),
        ]);

        return response()->json([
            [
                'action' => 'connect',
                'from' => $settings->vonage_number,
                'endpoint' => [['type' => 'phone', 'number' => $destination]],
            ],
        ]);
    }

    /**
     * Send an inbound call to the answerphone or ring the browser phone.
     */
    private function inbound(Request $request, VonageSetting $settings): JsonResponse
    {
        $caller = $request->input('from');

        CallEvent::create([
            'type' => CallEventType::Incoming,
            'call_uuid' => $request->input('uuid'),
            'from' => $caller,
            'to' => $request->input('to'),
            'message' => sprintf('Incoming call from %s', $caller ?? 'unknown'),
        ]);

        if ($settings->answerphone_enabled) {
            CallEvent::create([
                'type' => CallEventType::Answerphone,
                'call_uuid' => $request->input('uuid'),
                'from' => $caller,
                'to' => $request->input('to'),
                'message' => 'Answerphone is on: playing greeting to caller',
            ]);

            return response()->json([$this->answerphoneAction($settings)]);
        }

        CallEvent::create([
            'type' => CallEventType::RingingBrowser,
            'call_uuid' => $request->input('uuid'),
            'from' => $caller,
            'to' => $request->input('to'),
            'message' => 'Ringing the browser phone',
        ]);

        return response()->json([
            [
                'action' => 'connect',
                'from' => $caller,
                'endpoint' => [['type' => 'app', 'user' => VonageSetting::DIALLER_USER]],
            ],
        ]);
    }

    /**
     * Stream the uploaded greeting, or fall back to text-to-speech if none was uploaded.
     *
     * @return array<string, mixed>
     */
    private function answerphoneAction(VonageSetting $settings): array
    {
        if ($settings->hasAnswerphoneGreeting()) {
            return [
                'action' => 'stream',
                'streamUrl' => [$settings->publicRoute('answerphone.audio', ['v' => $settings->greetingVersion()])],
            ];
        }

        return [
            'action' => 'talk',
            'text' => 'Sorry, nobody is available to take your call right now. Please try again later.',
        ];
    }
}
