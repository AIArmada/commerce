<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\CommerceSupport\Targeting\TargetingEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

function targetingSpoofedRequest(): Request
{
    $request = Request::create('/shop', 'GET');
    $request->headers->set('X-Channel', 'pos');
    $request->headers->set('CF-IPCountry', 'US');
    $request->headers->set('X-Region', 'California');
    $request->headers->set('Referer', 'https://evil.test/lure');

    return $request;
}

it('ignores spoofable proxy headers by default', function (): void {
    Config::set('commerce-support.targeting.trust_proxy_headers', false);

    $context = new TargetingContext(null, null, targetingSpoofedRequest());

    expect($context->getChannel())->toBe('web')
        ->and($context->getCountry())->toBeNull()
        ->and($context->getRegion())->toBeNull();
});

it('honours proxy headers only when explicitly trusted', function (): void {
    Config::set('commerce-support.targeting.trust_proxy_headers', true);

    $context = new TargetingContext(null, null, targetingSpoofedRequest());

    expect($context->getChannel())->toBe('pos')
        ->and($context->getCountry())->toBe('US')
        ->and($context->getRegion())->toBe('California');
});

it('prefers server-side metadata over headers even when trusted', function (): void {
    Config::set('commerce-support.targeting.trust_proxy_headers', true);

    $context = new TargetingContext(null, null, targetingSpoofedRequest(), [
        'channel' => 'web',
        'country' => 'MY',
    ]);

    expect($context->getChannel())->toBe('web')
        ->and($context->getCountry())->toBe('MY');
});

it('queries the order count once per context no matter how often it is read', function (): void {
    TargetingCounterUser::reset();

    $context = new TargetingContext(null, new TargetingCounterUser);

    expect(TargetingCounterUser::$orderQueries)->toBe(1);

    expect($context->isFirstPurchase())->toBeFalse()
        ->and($context->isFirstPurchase())->toBeFalse()
        ->and($context->getOrderCount())->toBe(5)
        ->and(TargetingCounterUser::$orderQueries)->toBe(1);
});

it('rejects over-deep custom expressions during validation and evaluation', function (): void {
    $engine = new TargetingEngine;

    $expression = ['type' => 'channel', 'operator' => '=', 'value' => 'web'];
    for ($i = 0; $i <= TargetingEngine::MAX_EXPRESSION_DEPTH + 2; $i++) {
        $expression = ['not' => $expression];
    }

    $targeting = ['mode' => 'custom', 'expression' => $expression];

    expect($engine->validate($targeting))->not->toBeEmpty()
        ->and($engine->evaluate($targeting, new TargetingContext(null)))->toBeFalse()
        ->and($engine->evaluateExpression($expression, new TargetingContext(null)))->toBeFalse();
});

it('rejects custom expressions with too many nodes', function (): void {
    $engine = new TargetingEngine;

    $rules = [];
    for ($i = 0; $i <= TargetingEngine::MAX_EXPRESSION_NODES; $i++) {
        $rules[] = ['type' => 'channel', 'operator' => '=', 'value' => 'web'];
    }

    $targeting = ['mode' => 'custom', 'expression' => ['and' => $rules]];

    expect($engine->validate($targeting))->not->toBeEmpty()
        ->and($engine->evaluate($targeting, new TargetingContext(null)))->toBeFalse();
});

final class TargetingCounterUser extends Model
{
    public static int $orderQueries = 0;

    public static function reset(): void
    {
        self::$orderQueries = 0;
    }

    /**
     * @return object{count(): int}
     */
    public function orders(): object
    {
        self::$orderQueries++;

        return new class
        {
            public function count(): int
            {
                return 5;
            }
        };
    }
}
