<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\FilamentCart\Models\Cart;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CartDownloadService
{
    public function download(Cart $cart): StreamedResponse
    {
        $payload = $this->payload($cart);

        return response()->streamDownload(
            static function () use ($payload): void {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
            },
            $this->filename($cart),
            ['Content-Type' => 'application/json'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Cart $cart): array
    {
        return [
            'id' => $cart->id,
            'identifier' => $cart->identifier,
            'instance' => $cart->instance,
            'owner_type' => $cart->owner_type,
            'owner_id' => $cart->owner_id,
            'items' => $cart->items,
            'conditions' => $cart->conditions,
            'metadata' => $cart->metadata,
            'exported_at' => now()->toISOString(),
        ];
    }

    public function filename(Cart $cart): string
    {
        $instance = $this->normalizeFileComponent($cart->instance, 'default');
        $identifier = $this->normalizeFileComponent($cart->identifier, 'cart');

        return sprintf('cart-%s-%s-%s.json', $instance, $identifier, $cart->id);
    }

    private function normalizeFileComponent(string $value, string $fallback): string
    {
        $normalized = Str::of($value)
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-_.')
            ->toString();

        return $normalized === '' ? $fallback : $normalized;
    }
}
