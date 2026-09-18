<?php

declare(strict_types=1);

namespace AIArmada\Links\Support;

use AIArmada\Links\Contracts\BotDetectorInterface;

final class DefaultBotDetector implements BotDetectorInterface
{
    public function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || mb_trim($userAgent) === '') {
            return false;
        }

        return (bool) preg_match('/bot|crawl|slurp|spider|mediapartners|baidu|yandex|facebookexternalhit|twitterbot|linkedinbot|embedly|quora|pinterest|slackbot|telegrambot|whatsapp|discordbot|applebot|semrush|ahrefs|mj12bot|dotbot|petalbot|bytespider|gptbot|claudebot|ccbot|headless|phantomjs|selenium|playwright|puppeteer/i', $userAgent);
    }
}
