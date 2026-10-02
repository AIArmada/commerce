<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\CommerceSupport\Support\PublicHandle;
use AIArmada\Links\Contracts\SlugGeneratorInterface;
use AIArmada\Links\Models\Link;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class CreatePublicLink
{
    use AsAction;

    public function __construct(private readonly SlugGeneratorInterface $slugs) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>|null  $allowedHosts
     */
    public function handle(array $attributes, string $style = 'short', ?string $handle = null, ?string $label = null, ?bool $requireHttps = null, ?array $allowedHosts = null): Link
    {
        Validator::make(['link_style' => $style], ['link_style' => ['required', 'in:short,branded']])->validate();
        $prefix = null;
        $base = '';

        if ($style === 'branded') {
            $prefix = $handle === null ? null : PublicHandle::normalize($handle);
            Validator::make(['handle' => $prefix], ['handle' => PublicHandle::rules()])->validate();
            $base = mb_rtrim(mb_substr(Str::slug($label ?? $attributes['name'] ?? 'link'), 0, 60), '-');
            $base = $base !== '' ? $base . '-' : 'link-';
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $slug = $base . $this->slugs->generate(10);

            try {
                return DB::transaction(fn (): Link => CreateLink::run(array_merge($attributes, [
                    'slug' => $slug,
                    'slug_prefix' => $prefix,
                    'require_signature' => false,
                ]), $requireHttps, $allowedHosts));
            } catch (ValidationException $exception) {
                if (! isset($exception->errors()['slug'])) {
                    throw $exception;
                }
            } catch (QueryException $exception) {
                if (! in_array((string) ($exception->errorInfo[0] ?? ''), ['23000', '23505'], true)
                    || ! Link::query()->withoutOwnerScope()->where('slug', $slug)->exists()) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Unable to mint a unique public link slug.');
    }
}
