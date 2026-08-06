<?php

namespace App\Http\Controllers;

use App\Exceptions\AgentException;
use App\Http\Requests\ScanBagRequest;
use App\Services\Gemini\GeminiAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads a photo of a coffee bag and returns the setup fields to prefill.
 *
 * The image is never written to disk: it is read from the temporary upload,
 * base64-encoded, sent to Gemini and discarded. Nothing about the user's beans
 * is retained here.
 */
class BeanScanController extends Controller
{
    public function __construct(private readonly GeminiAgent $agent) {}

    /** POST /api/beans/scan */
    public function __invoke(ScanBagRequest $request): JsonResponse
    {
        set_time_limit(config('gemini.request_time_limit'));

        $photo = $request->file('photo');

        try {
            $beans = $this->agent->scanBag(
                base64_encode((string) file_get_contents($photo->getRealPath())),
                (string) $photo->getMimeType(),
            );

            return response()->json(['beans' => $beans]);
        } catch (AgentException $e) {
            return response()->json([
                'error' => $e->errorCode,
                'message' => $e->getMessage(),
            ], $e->httpStatus);
        } catch (Throwable $e) {
            Log::error('Unhandled bag scan failure', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'UNKNOWN',
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }
}
