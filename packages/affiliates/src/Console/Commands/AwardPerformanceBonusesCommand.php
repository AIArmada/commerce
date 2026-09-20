<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\Commissions\CommissionRuleEngine;
use AIArmada\Affiliates\Services\PerformanceBonusService;
use AIArmada\Affiliates\Support\BonusMonth;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

final class AwardPerformanceBonusesCommand extends Command
{
    protected $signature = 'affiliates:award-bonuses
        {--dry-run : Show what would be awarded without writing}
        {--month= : Month to calculate as YYYY-MM (default: current month)}
        {--type= : Only this bonus type: top_performer, recruitment, consistency, growth}';

    protected $description = 'Calculate and award monthly affiliate performance bonuses';

    public function __construct(
        private readonly CommissionRuleEngine $engine,
        private readonly PerformanceBonusService $bonuses,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $range = BonusMonth::parse($this->option('month'));

        if ($range === null) {
            $this->error('Invalid --month. Expected YYYY-MM, e.g. 2026-09.');

            return self::FAILURE;
        }

        $type = $this->parseType($this->option('type'));

        if ($type === false) {
            $this->error('Invalid --type. Expected one of: top_performer, recruitment, consistency, growth.');

            return self::FAILURE;
        }

        [$from, $to] = $range;

        $runner = new OwnerBatchRunner(
            Affiliate::class,
            ['enabled' => 'affiliates.owner.enabled', 'include_global' => 'affiliates.owner.include_global'],
        );
        $summary = $runner->run(fn (): array => $this->processScoped($from, $to, $type, $dryRun))
            ?? ['calculated' => 0, 'awarded' => 0, 'errors' => 0];

        $this->info("Calculated: {$summary['calculated']}");

        if (! $dryRun) {
            $this->info("Awarded: {$summary['awarded']}");
        }

        if ($summary['errors'] > 0) {
            $this->error("Errors: {$summary['errors']}");
        }

        return $summary['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function parseType(mixed $option): CommissionRuleType | false | null
    {
        if ($option === null || $option === '') {
            return null;
        }

        if (! is_string($option)) {
            return false;
        }

        $type = CommissionRuleType::tryFrom($option);

        return $type instanceof CommissionRuleType && $type->isPerformanceBonus() ? $type : false;
    }

    /**
     * @return array{calculated:int,awarded:int,errors:int}
     */
    private function processScoped(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?CommissionRuleType $type,
        bool $dryRun,
    ): array {
        $summary = ['calculated' => 0, 'awarded' => 0, 'errors' => 0];
        $types = $type === null ? CommissionRuleType::performanceBonusCases() : [$type];

        foreach ($types as $bonusType) {
            try {
                $bonuses = $this->engine->calculatePerformanceBonuses($bonusType, $from, $to);
            } catch (Throwable) {
                $summary['errors']++;

                continue;
            }

            $summary['calculated'] += count($bonuses);
            $this->line(sprintf('%s: %d bonus(es)%s', $bonusType->value, count($bonuses), $dryRun ? ' (dry run)' : ''));

            if ($dryRun || $bonuses === []) {
                continue;
            }

            try {
                $summary['awarded'] += $this->bonuses->awardBonuses($bonuses);
            } catch (Throwable) {
                $summary['errors']++;
            }
        }

        return $summary;
    }
}
