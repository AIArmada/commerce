<?php

declare(strict_types=1);

namespace AIArmada\Cart\Http\Middleware;

use AIArmada\Cart\Security\CartRateLimiter;
use AIArmada\Cart\Security\CartRateLimitResult;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Middleware to throttle cart operations.
 *
 * Apply to cart routes to protect against abuse.
 */
final class ThrottleCartOperations
{
    public function __construct(
        private CartRateLimiter $rateLimiter
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $operation = null): Response
    {
        if (! config('cart.rate_limiting.enabled', true)) {
            return $next($request);
        }

        $identifier = $this->resolveIdentifier($request);
        $operation = $operation ?? $this->resolveOperation($request);

        $result = $this->rateLimiter->check($identifier, $operation);

        if (! $result->allowed) {
            throw new TooManyRequestsHttpException(
                $result->retryAfter,
                $result->getMessage()
            );
        }

        $response = $next($request);

        // Add rate limit headers to response
        return $this->addRateLimitHeaders($response, $result);
    }

    /**
     * Resolve identifier from request.
     */
    private function resolveIdentifier(Request $request): string
    {
        // Prefer authenticated user ID
        if ($request->user()) {
            return 'user:' . $request->user()->getAuthIdentifier();
        }

        // Fall back to session ID
        if ($request->hasSession()) {
            return 'session:' . $request->session()->getId();
        }

        // Last resort: IP address
        return 'ip:' . $request->ip();
    }

    /**
     * Resolve operation type from request.
     */
    private function resolveOperation(Request $request): string
    {
        // Map HTTP method + route to operation type
        $method = mb_strtoupper($request->method());
        $path = $request->path();

        // Common patterns
        if (str_contains($path, 'checkout')) {
            return 'checkout';
        }

        if (str_contains($path, 'merge')) {
            return 'merge_cart';
        }

        if (str_contains($path, 'condition')) {
            return $method === 'DELETE' ? 'remove_condition' : 'add_condition';
        }

        if (str_contains($path, 'clear')) {
            return 'clear_cart';
        }

        if (str_contains($path, 'item')) {
            return match ($method) {
                'POST' => 'add_item',
                'PUT', 'PATCH' => 'update_item',
                'DELETE' => 'remove_item',
                default => 'get_cart',
            };
        }

        return match ($method) {
            'GET' => 'get_cart',
            'POST' => 'add_item',
            'PUT', 'PATCH' => 'update_item',
            'DELETE' => 'remove_item',
            default => 'default',
        };
    }

    /**
     * Add rate limit headers to response.
     */
    private function addRateLimitHeaders(Response $response, CartRateLimitResult $result): Response
    {
        if ($result->remainingMinute >= 0) {
            $response->headers->set('X-RateLimit-Remaining', (string) $result->remainingMinute);
        }

        if ($result->remainingHour >= 0) {
            $response->headers->set('X-RateLimit-Remaining-Hour', (string) $result->remainingHour);
        }

        return $response;
    }
}
