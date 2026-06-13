<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Data\ContactLinksData;
use AIArmada\Contacting\Models\ContactMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class BuildContactLinksAction
{
    /**
     * @param  Model  $contactable
     */
    public function forContactable(Model $contactable): ContactLinksData
    {
        if (! method_exists($contactable, 'contactMethods')) {
            return $this->execute([]);
        }

        $contactMethods = call_user_func([$contactable, 'contactMethods']);

        if (! $contactMethods instanceof MorphMany) {
            return $this->execute([]);
        }

        return $this->execute($contactMethods->get());
    }

    /**
     * @param  iterable<ContactMethod>  $contactMethods
     */
    public function execute(iterable $contactMethods): ContactLinksData
    {
        $mailtoUrl = null;
        $telUrl = null;
        $whatsappUrl = null;
        $websiteUrl = null;
        $links = [];

        foreach ($contactMethods as $cm) {
            $normalized = $cm->normalized_value ?? $cm->value;

            $link = match ($cm->type) {
                'email' => $this->buildMailto($normalized),
                'phone', 'mobile' => $this->buildTel($normalized),
                'whatsapp' => $this->buildWhatsapp($normalized),
                'website' => $this->buildWebsite($normalized),
                'telegram' => $this->buildTelegram($normalized),
                default => null,
            };

            if ($link !== null) {
                $links[$cm->type][] = $link;
            }

            match ($cm->type) {
                'email' => $mailtoUrl ??= $link,
                'phone', 'mobile' => $telUrl ??= $link,
                'whatsapp' => $whatsappUrl ??= $link,
                'website' => $websiteUrl ??= $link,
                default => null,
            };
        }

        return new ContactLinksData(
            mailtoUrl: $mailtoUrl,
            telUrl: $telUrl,
            whatsappUrl: $whatsappUrl,
            websiteUrl: $websiteUrl,
            links: $links,
        );
    }

    private function buildMailto(string $email): ?string
    {
        if ($email === '') {
            return null;
        }

        return 'mailto:' . $email;
    }

    private function buildTel(string $phone): ?string
    {
        if ($phone === '') {
            return null;
        }

        return 'tel:' . $phone;
    }

    private function buildWhatsapp(string $phone): ?string
    {
        if ($phone === '') {
            return null;
        }

        // Remove + for wa.me path
        $cleaned = mb_ltrim($phone, '+');

        return 'https://wa.me/' . $cleaned;
    }

    private function buildWebsite(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return 'https://' . $url;
        }

        return $url;
    }

    private function buildTelegram(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        // If it looks like a URL, use it directly
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_contains($value, '.')) {
            return $value;
        }

        // Otherwise treat as handle
        $handle = mb_ltrim($value, '@');

        return 'https://t.me/' . $handle;
    }
}
