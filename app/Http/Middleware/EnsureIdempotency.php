<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('Idempotency-Key');

        if (!$key || !preg_match('/^[A-Za-z0-9._:-]{8,191}$/', $key)) {
            return response()->json(['message' => 'Idempotency-Key is required for this operation.'], 422);
        }

        $scope = $request->method() . ':' . $request->path();
        $hash = hash('sha256', $request->getContent());

        $existing = IdempotencyKey::query()->where('key', $key)->where('scope', $scope)->first();

        if ($existing) {
            if (!hash_equals($existing->request_hash, $hash)) {
                return response()->json(['message' => 'The Idempotency-Key was already used with a different request.'], 409);
            }

            if ($existing->response_body !== null) {
                return response()->json($existing->response_body, $existing->status_code ?? 200);
            }
        } else {
            IdempotencyKey::create([
                'key' => $key,
                'scope' => $scope,
                'user_id' => $request->user()?->id,
                'request_hash' => $hash,
                'expires_at' => now()->addHours(24),
            ]);
        }

        $response = $next($request);

        $record = IdempotencyKey::query()->where('key', $key)->where('scope', $scope)->first();
        if ($record && $response->getStatusCode() < 500) {
            $body = json_decode($response->getContent(), true);
            $record->update([
                'status_code' => $response->getStatusCode(),
                'response_body' => is_array($body) ? $body : ['raw' => $response->getContent()],
            ]);
        }

        return $response;
    }
}
