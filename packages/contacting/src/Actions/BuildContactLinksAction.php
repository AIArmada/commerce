<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Data\ContactLinksData;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Support\NormalizesUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class BuildContactLinksAction
{
    private const array TELEGRAM_HOSTS = ['t.me', 'telegram.me', 'telegram.dog'];

    public function __construct(
        private readonly NormalizesUrl $urlNormalizer = new NormalizesUrl,
    ) {}

    public function forContactable(Model $contactable, ?int $limit = null): ContactLinksData
    {
        if (! method_exists($contactable, 'contactMethods')) {
            return $this->execute([]);
        }

        $contactMethods = call_user_func([$contactable, 'contactMethods']);

        if (! $contactMethods instanceof MorphMany) {
            return $this->execute([]);
        }

        if ($limit !== null) {
            $contactMethods = $contactMethods->limit($limit);
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
        if ($email === '' || preg_match('/\s/', $email) === 1) {
            return null;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return 'mailto:' . $email;
    }

    private function buildTel(string $phone): ?string
    {
        if ($phone === '' || preg_match('/[\r\n]/', $phone) === 1) {
            return null;
        }

        $phone = str_replace(' ', '', $phone);

        if ($phone === '' || preg_match('/^[+\d][\d().\-.]*$/', $phone) !== 1) {
            return null;
        }

        return 'tel:' . $phone;
    }

    private function buildWhatsapp(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        return 'https://wa.me/' . $digits;
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

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*:\/\//', $value) === 1 || str_contains($value, '.')) {
            $normalized = $this->urlNormalizer->normalize($value);

            if ($normalized === null) {
                return null;
            }

            $host = mb_strtolower((string) parse_url($normalized, PHP_URL_HOST));

            if (! in_array($host, self::TELEGRAM_HOSTS, true)) {
                return null;
            }

            return $normalized;
        }

        $handle = rawurlencode(mb_ltrim($value, '@'));

        return $handle === '' ? null : 'https://t.me/' . $handle;
    }
}
