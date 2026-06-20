<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Enums;

enum SocialPlatform: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Tiktok = 'tiktok';
    case Youtube = 'youtube';
    case X = 'x';
    case Linkedin = 'linkedin';
    case Threads = 'threads';
    case Snapchat = 'snapchat';
    case Reddit = 'reddit';
    case Pinterest = 'pinterest';
    case Discord = 'discord';
    case Twitch = 'twitch';
    case Bluesky = 'bluesky';
    case Mastodon = 'mastodon';
    case Tumblr = 'tumblr';
    case Behance = 'behance';
    case Lemon8 = 'lemon8';
    case Pinkary = 'pinkary';
    case TruthSocial = 'truth_social';
    case Quora = 'quora';
    case Flickr = 'flickr';
    case DeviantArt = 'deviantart';
    case Telegram = 'telegram';
    case WhatsApp = 'whatsapp';
    case Signal = 'signal';
    case Line = 'line';
    case WeChat = 'wechat';
    case KakaoTalk = 'kakaotalk';
    case Viber = 'viber';
    case Medium = 'medium';
    case Substack = 'substack';
    case Blogger = 'blogger';
    case WordPress = 'wordpress';
    case Patreon = 'patreon';
    case KoFi = 'ko_fi';
    case BuyMeACoffee = 'buymeacoffee';
    case GitHub = 'github';
    case GitLab = 'gitlab';
    case VK = 'vk';
    case Weibo = 'weibo';
    case Douyin = 'douyin';
    case Xiaohongshu = 'xiaohongshu';
    case Website = 'website';
    case Other = 'other';

    public function label(): string
    {
        $config = config('contacting.social_profiles.platforms.' . $this->value);

        if (is_array($config) && isset($config['label'])) {
            return $config['label'];
        }

        return self::formatLabel($this->value);
    }

    public static function options(?array $allowedPlatforms = null): array
    {
        $platformConfig = config('contacting.social_profiles.platforms', []);
        $allowedPlatforms ??= array_keys($platformConfig);

        $options = [];

        foreach ($allowedPlatforms as $key => $value) {
            if (! is_int($key)) {
                if (is_array($value)) {
                    $options[(string) $key] = $value['label'] ?? self::formatLabel((string) $key);
                } else {
                    $options[(string) $key] = (string) $value;
                }

                continue;
            }

            $platform = (string) $value;
            $config = $platformConfig[$platform] ?? null;

            $options[$platform] = is_array($config) && isset($config['label'])
                ? $config['label']
                : self::formatLabel($platform);
        }

        return $options;
    }

    private static function formatLabel(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
