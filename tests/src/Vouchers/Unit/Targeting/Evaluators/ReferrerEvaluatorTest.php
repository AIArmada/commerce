<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Targeting\Evaluators;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\Evaluators\ReferrerEvaluator;
use AIArmada\Vouchers\Targeting\TargetingContext;
use Illuminate\Http\Request;

beforeEach(function (): void {
    $this->evaluator = new ReferrerEvaluator();
});

/**
 * Creates a cart for testing.
 */
function createCartForReferrerTest(): Cart
{
    return new Cart(new InMemoryStorage(), 'referrer-test');
}

/**
 * Creates a mock request with referrer header.
 */
function createMockRequestWithReferrer(?string $referrer = null, array $utmParams = []): Request
{
    $request = Request::create('/', 'GET', $utmParams);

    if ($referrer !== null) {
        $request->headers->set('referer', $referrer);
    }

    return $request;
}

describe('ReferrerEvaluator supports', function (): void {
    it('supports referrer rule type', function (): void {
        expect($this->evaluator->supports(TargetingRuleType::Referrer->value))->toBeTrue();
    });

    it('does not support other rule types', function (): void {
        expect($this->evaluator->supports('cart_value'))->toBeFalse();
        expect($this->evaluator->supports('user_segment'))->toBeFalse();
        expect($this->evaluator->supports('channel'))->toBeFalse();
    });
});

describe('ReferrerEvaluator getType', function (): void {
    it('returns referrer type', function (): void {
        expect($this->evaluator->getType())->toBe('referrer');
    });
});

describe('ReferrerEvaluator validate', function (): void {
    it('requires values to be an array', function (): void {
        $errors = $this->evaluator->validate([]);
        expect($errors)->toContain('Values must be an array');
    });

    it('accepts valid rule with array values', function (): void {
        $errors = $this->evaluator->validate(['values' => ['google.com']]);
        expect($errors)->toBeEmpty();
    });

    it('validates rule type', function (): void {
        $errors = $this->evaluator->validate([
            'values' => ['value'],
            'type' => 'invalid_type',
        ]);
        expect($errors)->toHaveCount(1);
        expect($errors[0])->toContain('Invalid type');
    });

    it('accepts all valid types', function (): void {
        $validTypes = ['referrer', 'referrer_url', 'utm_source', 'utm_medium', 'utm_campaign', 'channel'];

        foreach ($validTypes as $type) {
            $errors = $this->evaluator->validate([
                'values' => ['value'],
                'type' => $type,
            ]);
            expect($errors)->toBeEmpty();
        }
    });
});

describe('ReferrerEvaluator evaluate with empty values', function (): void {
    it('returns true when values array is empty', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://google.com');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate(['values' => []], $context);
        expect($result)->toBeTrue();
    });
});

describe('ReferrerEvaluator evaluate referrer URL matching', function (): void {
    it('matches referrer URL with contains operator', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.google.com/search?q=test');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'contains',
            'values' => ['google.com'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('matches referrer URL with referrer_url type', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.facebook.com/');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer_url',
            'operator' => 'contains',
            'values' => ['facebook.com'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match referrer with contains when value not present', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.bing.com/');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'contains',
            'values' => ['google.com'],
        ], $context);

        expect($result)->toBeFalse();
    });

    it('matches with not_contains operator when referrer does not contain value', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.bing.com/');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'not_contains',
            'values' => ['google.com'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match with not_contains operator when referrer contains value', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.google.com/');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'not_contains',
            'values' => ['google.com'],
        ], $context);

        expect($result)->toBeFalse();
    });

    it('matches with in operator for exact match', function (): void {
        $cart = createCartForReferrerTest();
        $referrer = 'https://www.google.com/';
        $request = createMockRequestWithReferrer($referrer);
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'in',
            'values' => [$referrer, 'https://www.bing.com/'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match with in operator when exact match not found', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.google.com/search');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'in',
            'values' => ['https://www.google.com/', 'https://www.bing.com/'],
        ], $context);

        expect($result)->toBeFalse();
    });

    it('matches with not_in operator when referrer not in list', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.yahoo.com/');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'not_in',
            'values' => ['https://www.google.com/', 'https://www.bing.com/'],
        ], $context);

        expect($result)->toBeTrue();
    });
});

describe('ReferrerEvaluator evaluate with domain operator', function (): void {
    it('matches exact domain', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://google.com/search');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'domain',
            'values' => ['google.com'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('matches subdomain', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.google.com/search');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'domain',
            'values' => ['google.com'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match different domain', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.bing.com/search');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'domain',
            'values' => ['google.com'],
        ], $context);

        expect($result)->toBeFalse();
    });
});

describe('ReferrerEvaluator evaluate with empty referrer', function (): void {
    it('returns true for not_contains when referrer is empty', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'not_contains',
            'values' => ['google.com'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('returns true for not_in when referrer is empty', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'not_in',
            'values' => ['https://google.com'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('returns false for contains when referrer is empty', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'contains',
            'values' => ['google.com'],
        ], $context);

        expect($result)->toBeFalse();
    });
});

describe('ReferrerEvaluator evaluate UTM source matching', function (): void {
    it('matches utm_source from query parameter', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null, ['utm_source' => 'newsletter']);
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'utm_source',
            'operator' => 'in',
            'values' => ['newsletter', 'email'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('matches utm_source from metadata', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request, [
            'utm_source' => 'google',
        ]);

        $result = $this->evaluator->evaluate([
            'type' => 'utm_source',
            'operator' => 'in',
            'values' => ['google', 'bing'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match when utm_source not in values', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null, ['utm_source' => 'facebook']);
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'utm_source',
            'operator' => 'in',
            'values' => ['google', 'bing'],
        ], $context);

        expect($result)->toBeFalse();
    });

    it('matches with not_in operator', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null, ['utm_source' => 'facebook']);
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'utm_source',
            'operator' => 'not_in',
            'values' => ['google', 'bing'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('returns false for not_in when value is null', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'utm_source',
            'operator' => 'not_in',
            'values' => ['google', 'bing'],
        ], $context);

        // When value is null/empty, not_in returns true
        expect($result)->toBeTrue();
    });
});

describe('ReferrerEvaluator evaluate UTM medium matching', function (): void {
    it('matches utm_medium from metadata', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request, [
            'utm_medium' => 'cpc',
        ]);

        $result = $this->evaluator->evaluate([
            'type' => 'utm_medium',
            'operator' => 'in',
            'values' => ['cpc', 'ppc'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match when utm_medium not in values', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request, [
            'utm_medium' => 'email',
        ]);

        $result = $this->evaluator->evaluate([
            'type' => 'utm_medium',
            'operator' => 'in',
            'values' => ['cpc', 'ppc'],
        ], $context);

        expect($result)->toBeFalse();
    });
});

describe('ReferrerEvaluator evaluate UTM campaign matching', function (): void {
    it('matches utm_campaign from metadata', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request, [
            'utm_campaign' => 'summer_sale',
        ]);

        $result = $this->evaluator->evaluate([
            'type' => 'utm_campaign',
            'operator' => 'in',
            'values' => ['summer_sale', 'winter_sale'],
        ], $context);

        expect($result)->toBeTrue();
    });
});

describe('ReferrerEvaluator evaluate channel detection', function (): void {
    it('detects direct channel when no referrer', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['direct'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects paid channel from utm_medium', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request, [
            'utm_medium' => 'cpc',
        ]);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['paid'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects paid channel from ppc utm_medium', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request, [
            'utm_medium' => 'ppc',
        ]);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['paid'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects paid channel from paid_search utm_medium', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request, [
            'utm_medium' => 'paid_search',
        ]);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['paid'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects email channel from utm_medium', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request, [
            'utm_medium' => 'email',
        ]);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['email'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects email channel from newsletter utm_medium', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request, [
            'utm_medium' => 'newsletter',
        ]);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['email'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects social channel from utm_medium', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request, [
            'utm_medium' => 'social',
        ]);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['social'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects affiliate channel from utm_medium', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer(null);
        $context = new TargetingContext($cart, null, $request, [
            'utm_medium' => 'affiliate',
        ]);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['affiliate'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects social channel from Facebook referrer', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.facebook.com/');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['social'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects social channel from Twitter referrer', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://twitter.com/');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['social'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects social channel from Instagram referrer', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.instagram.com/');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['social'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects social channel from LinkedIn referrer', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.linkedin.com/');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['social'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects social channel from TikTok referrer', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.tiktok.com/');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['social'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects social channel from YouTube referrer', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.youtube.com/');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['social'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects organic channel from Google referrer', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.google.com/search');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['organic'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects organic channel from Bing referrer', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.bing.com/search');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['organic'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects organic channel from Yahoo referrer', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://search.yahoo.com/');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['organic'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects organic channel from DuckDuckGo referrer', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://duckduckgo.com/');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['organic'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('detects referral channel from unknown website', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://some-blog.com/article');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['referral'],
        ], $context);

        expect($result)->toBeTrue();
    });

    it('does not match wrong channel', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://www.google.com/search');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'channel',
            'values' => ['paid', 'social'],
        ], $context);

        expect($result)->toBeFalse();
    });
});

describe('ReferrerEvaluator evaluate with invalid operator', function (): void {
    it('returns false for invalid referrer operator', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://google.com');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'invalid_operator',
            'values' => ['google.com'],
        ], $context);

        expect($result)->toBeFalse();
    });

    it('returns false for invalid type', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://google.com');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'unknown_type',
            'values' => ['google.com'],
        ], $context);

        expect($result)->toBeFalse();
    });
});

describe('ReferrerEvaluator evaluate without request', function (): void {
    it('handles missing request gracefully', function (): void {
        $cart = createCartForReferrerTest();
        $context = new TargetingContext($cart, null, null);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'contains',
            'values' => ['google.com'],
        ], $context);

        expect($result)->toBeFalse();
    });

    it('uses utm_source from metadata when no request', function (): void {
        $cart = createCartForReferrerTest();
        $context = new TargetingContext($cart, null, null, [
            'utm_source' => 'newsletter',
        ]);

        $result = $this->evaluator->evaluate([
            'type' => 'utm_source',
            'operator' => 'in',
            'values' => ['newsletter'],
        ], $context);

        expect($result)->toBeTrue();
    });
});

describe('ReferrerEvaluator multiple value matching', function (): void {
    it('matches any value in contains check', function (): void {
        $cart = createCartForReferrerTest();
        $request = createMockRequestWithReferrer('https://m.facebook.com/story');
        $context = new TargetingContext($cart, null, $request);

        $result = $this->evaluator->evaluate([
            'type' => 'referrer',
            'operator' => 'contains',
            'values' => ['google.com', 'facebook.com', 'twitter.com'],
        ], $context);

        expect($result)->toBeTrue();
    });
});
