<?php

declare(strict_types=1);

use AIArmada\Contacting\Actions\NormalizeSocialProfileAction;
use AIArmada\Contacting\Models\SocialProfile;
use AIArmada\Contacting\Support\NormalizesSocialHandle;
use AIArmada\Contacting\Support\NormalizesUrl;
use AIArmada\Contacting\Support\SocialProfileConfig;

beforeEach(function (): void {
    $this->config = new SocialProfileConfig;
});

dataset('prefix_platforms', [
    ['facebook', 'www.facebook.com/'],
    ['instagram', 'www.instagram.com/'],
    ['tiktok', 'www.tiktok.com/@'],
    ['youtube', 'www.youtube.com/@'],
    ['x', 'x.com/'],
    ['linkedin', 'www.linkedin.com/in/'],
    ['threads', 'www.threads.net/@'],
    ['snapchat', 'www.snapchat.com/add/'],
    ['reddit', 'www.reddit.com/user/'],
    ['pinterest', 'www.pinterest.com/'],
    ['discord', 'discord.gg/'],
    ['twitch', 'www.twitch.tv/'],
    ['bluesky', 'bsky.app/profile/'],
    ['mastodon', 'mastodon.social/@'],
    ['tumblr', 'www.tumblr.com/'],
    ['behance', 'www.behance.net/'],
    ['lemon8', 'www.lemon8-app.com/@'],
    ['pinkary', 'pinkary.com/@'],
    ['truth_social', 'truthsocial.com/@'],
    ['quora', 'www.quora.com/profile/'],
    ['flickr', 'www.flickr.com/'],
    ['deviantart', 'www.deviantart.com/'],
    ['whatsapp', 'wa.me/'],
    ['telegram', 't.me/'],
    ['line', 'line.me/R/ti/p/'],
    ['substack', 'substack.com/@'],
    ['patreon', 'www.patreon.com/'],
    ['ko_fi', 'ko-fi.com/'],
    ['buymeacoffee', 'buymeacoffee.com/'],
    ['github', 'github.com/'],
    ['gitlab', 'gitlab.com/'],
    ['vk', 'vk.com/'],
    ['weibo', 'weibo.com/u/'],
    ['douyin', 'www.douyin.com/user/'],
    ['xiaohongshu', 'www.xiaohongshu.com/user/profile/'],
]);

dataset('suffix_platforms', [
    ['medium', '.medium.com'],
    ['blogger', '.blogspot.com'],
    ['wordpress', '.wordpress.com'],
]);

dataset('no_pattern_platforms', [
    'signal', 'wechat', 'kakaotalk', 'viber', 'website', 'other',
]);

test('prefix returns correct value for prefix platforms', function (string $platform, string $expected): void {
    expect($this->config->prefix($platform))->toBe($expected);
})->with('prefix_platforms');

test('prefix returns null for suffix platforms', function (string $platform, string $_suffix): void {
    expect($this->config->prefix($platform))->toBeNull();
})->with('suffix_platforms');

test('prefix returns null for no-pattern platforms', function (string $platform): void {
    expect($this->config->prefix($platform))->toBeNull();
})->with('no_pattern_platforms');

test('suffix returns correct value for suffix platforms', function (string $platform, string $expected): void {
    expect($this->config->suffix($platform))->toBe($expected);
})->with('suffix_platforms');

test('suffix returns null for prefix platforms', function (string $platform, string $_prefix): void {
    expect($this->config->suffix($platform))->toBeNull();
})->with('prefix_platforms');

test('suffix returns null for no-pattern platforms', function (string $platform): void {
    expect($this->config->suffix($platform))->toBeNull();
})->with('no_pattern_platforms');

test('hasUrlPattern returns true for prefix platforms', function (string $platform): void {
    expect($this->config->hasUrlPattern($platform))->toBeTrue();
})->with('prefix_platforms', 'suffix_platforms');

test('hasUrlPattern returns false for no-pattern platforms', function (string $platform): void {
    expect($this->config->hasUrlPattern($platform))->toBeFalse();
})->with('no_pattern_platforms');

test('buildUrl constructs correct URL for prefix platforms', function (string $platform, string $prefix): void {
    expect($this->config->buildUrl($platform, 'myhandle'))->toBe('https://' . $prefix . 'myhandle');
})->with('prefix_platforms');

test('buildUrl constructs correct URL for suffix platforms', function (string $platform, string $suffix): void {
    expect($this->config->buildUrl($platform, 'myhandle'))->toBe('https://' . 'myhandle' . $suffix);
})->with('suffix_platforms');

test('buildUrl returns null for no-pattern platforms', function (string $platform): void {
    expect($this->config->buildUrl($platform, 'myhandle'))->toBeNull();
})->with('no_pattern_platforms');

test('extractHandle extracts handle from standard URL for prefix platforms', function (string $platform, string $prefix): void {
    $url = 'https://' . $prefix . 'thehandle';
    expect($this->config->extractHandle($platform, $url))->toBe('thehandle');
})->with('prefix_platforms');

test('extractHandle handles URL with extra path segments', function (): void {
    expect($this->config->extractHandle('facebook', 'https://www.facebook.com/zuck/about'))->toBe('zuck');
    expect($this->config->extractHandle('instagram', 'https://www.instagram.com/user123/photos/'))->toBe('user123');
    expect($this->config->extractHandle('reddit', 'https://www.reddit.com/user/spez/comments/'))->toBe('spez');
});

test('extractHandle handles URL without www prefix', function (): void {
    expect($this->config->extractHandle('instagram', 'https://instagram.com/user123'))->toBe('user123');
    expect($this->config->extractHandle('facebook', 'https://facebook.com/zuck'))->toBe('zuck');
    expect($this->config->extractHandle('tiktok', 'https://tiktok.com/@therock'))->toBe('therock');
});

test('extractHandle handles X URLs', function (): void {
    expect($this->config->extractHandle('x', 'https://x.com/elonmusk'))->toBe('elonmusk');
});

test('extractHandle extracts handle from suffix platforms', function (): void {
    expect($this->config->extractHandle('medium', 'https://username.medium.com'))->toBe('username');
    expect($this->config->extractHandle('blogger', 'https://myblog.blogspot.com'))->toBe('myblog');
    expect($this->config->extractHandle('wordpress', 'https://site.wordpress.com'))->toBe('site');
});

test('extractHandle returns null for no-pattern platforms', function (string $platform): void {
    expect($this->config->extractHandle($platform, 'https://example.com'))->toBeNull();
})->with('no_pattern_platforms');

test('extractHandle returns null for non-matching URL on prefix platform', function (): void {
    expect($this->config->extractHandle('facebook', 'https://example.com/something'))->toBeNull();
});

test('normalize action builds URL when only handle is given', function (): void {
    $action = new NormalizeSocialProfileAction(
        new SocialProfileConfig,
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('facebook', 'myPage', null);
    expect($r['handle'])->toBe('myPage');
    expect($r['normalized_url'])->toBe('https://www.facebook.com/myPage');
});

test('normalize action extracts handle when only URL is given', function (): void {
    $action = new NormalizeSocialProfileAction(
        new SocialProfileConfig,
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('facebook', null, 'https://www.facebook.com/zuck');
    expect($r['handle'])->toBe('zuck');
    expect($r['normalized_url'])->toBe('https://www.facebook.com/zuck');
});

test('normalize action does not build URL for no-pattern platforms', function (): void {
    $action = new NormalizeSocialProfileAction(
        new SocialProfileConfig,
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('website', null, 'https://example.com');
    expect($r['handle'])->toBeNull();
    expect($r['normalized_url'])->toBe('https://example.com');

    $r2 = $action->execute('signal', 'myhandle', null);
    expect($r2['handle'])->toBe('myhandle');
    expect($r2['normalized_url'])->toBeNull();
});

test('normalize action handles TikTok URL with @ in prefix', function (): void {
    $action = new NormalizeSocialProfileAction(
        new SocialProfileConfig,
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('tiktok', null, 'https://www.tiktok.com/@therock');
    expect($r['handle'])->toBe('therock');
    expect($r['normalized_url'])->toBe('https://www.tiktok.com/@therock');
});

test('normalize action builds TikTok URL with clean handle', function (): void {
    $action = new NormalizeSocialProfileAction(
        new SocialProfileConfig,
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('tiktok', '@therock', null);
    expect($r['handle'])->toBe('therock');
    expect($r['normalized_url'])->toBe('https://www.tiktok.com/@therock');
});

test('normalize action handles LinkedIn URL with /in/ path segment', function (): void {
    $action = new NormalizeSocialProfileAction(
        new SocialProfileConfig,
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('linkedin', null, 'https://www.linkedin.com/in/janedoe');
    expect($r['handle'])->toBe('janedoe');
    expect($r['normalized_url'])->toBe('https://www.linkedin.com/in/janedoe');
});

test('normalize action builds LinkedIn URL', function (): void {
    $action = new NormalizeSocialProfileAction(
        new SocialProfileConfig,
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('linkedin', 'janedoe', null);
    expect($r['handle'])->toBe('janedoe');
    expect($r['normalized_url'])->toBe('https://www.linkedin.com/in/janedoe');
});

test('normalize action extracts handle from medium suffix URL', function (): void {
    $action = new NormalizeSocialProfileAction(
        new SocialProfileConfig,
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('medium', null, 'https://username.medium.com');
    expect($r['handle'])->toBe('username');
    expect($r['normalized_url'])->toBe('https://username.medium.com');
});

test('normalize action builds medium suffix URL from handle', function (): void {
    $action = new NormalizeSocialProfileAction(
        new SocialProfileConfig,
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('medium', 'myblog', null);
    expect($r['handle'])->toBe('myblog');
    expect($r['normalized_url'])->toBe('https://myblog.medium.com');
});

test('normalize action with both handle and URL keeps both', function (): void {
    $action = new NormalizeSocialProfileAction(
        new SocialProfileConfig,
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('facebook', 'myPage', 'https://www.facebook.com/myPage');
    expect($r['handle'])->toBe('myPage');
    expect($r['normalized_url'])->toBe('https://www.facebook.com/myPage');
});

test('normalize action handles @ prefix in extracted handle', function (): void {
    $action = new NormalizeSocialProfileAction(
        new SocialProfileConfig,
        new NormalizesSocialHandle,
        new NormalizesUrl,
    );

    $r = $action->execute('pinkary', null, 'https://pinkary.com/@user');
    expect($r['handle'])->toBe('user');
});

test('profileUrl returns built URL from handle', function (): void {
    $profile = new SocialProfile;
    $profile->platform = 'facebook';
    $profile->handle = 'myPage';

    expect($profile->profileUrl())->toBe('https://www.facebook.com/myPage');
});

test('profileUrl falls back to url when handle is null', function (): void {
    $profile = new SocialProfile;
    $profile->platform = 'website';
    $profile->handle = null;
    $profile->url = 'https://example.com';

    expect($profile->profileUrl())->toBe('https://example.com');
});

test('profileUrl returns null for no-pattern platform with no url', function (): void {
    $profile = new SocialProfile;
    $profile->platform = 'signal';
    $profile->handle = 'myuser';

    expect($profile->profileUrl())->toBeNull();
});
