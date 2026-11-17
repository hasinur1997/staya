<?php

namespace App\Actions\Reports;

use App\Concerns\BusinessCalendar;
use App\Concerns\Money;
use App\Enums\LedgerDirection;
use App\Models\GroceryPurchase;
use App\Models\GroceryPurchaseItem;
use App\Models\LedgerEntry;
use App\Models\Meal;
use Carbon\CarbonImmutable;

/**
 * The figures behind the dashboard charts: six months of deposits, expenses
 * and the fund balance at each month end, plus the chosen month's spending
 * and meals by day and its most bought items. Money is decimal strings
 * added with bcmath; the page only draws them.
 */
class BuildDashboardInsights
{
    /** Months in the trend, ending with the chosen one. */
    public const int MONTHS = 6;

    /** Items shown in the "most bought" chart. */
    public const int TOP_ITEMS = 6;

    public function __construct(private CalculateMonthlyReport $calculate) {}

    /**
     * @return array{
     *     trend: list<array{month: string, deposits: string, expenses: string, fund_end: string}>,
     *     changes: array{deposits: string, expenses: string, fund: string},
     *     daily: list<array{day: int, expenses: string, meals: int}>,
     *     top_items: list<array{name: string, total: string}>,
     * }
     */
    public function handle(string $month): array
    {
        $end = CarbonImmutable::createFromFormat('!Y-m', $month, BusinessCalendar::TIMEZONE);

        $trend = $this->trend($end);

        return [
            'trend' => $trend,
            'changes' => $this->changes($trend),
            'daily' => $this->daily($end),
            'top_items' => $this->topItems($end),
        ];
    }

    /**
     * @return list<array{month: string, deposits: string, expenses: string, fund_end: string}>
     */
    private function trend(CarbonImmutable $end): array
    {
        $months = [];

        for ($step = self::MONTHS - 1; $step >= 0; $step--) {
            $months[] = $end->subMonthsNoOverflow($step);
        }

        // Net ledger movement per month, then a running total for the balance.
        $net = [];

        foreach (LedgerEntry::query()->where('entry_date', '<=', $end->endOfMonth()->toDateString())->orderBy('entry_date')->cursor() as $entry) {
            $key = substr($entry->entry_date->toDateString(), 0, 7);
            $amount = Money::numeric($entry->amount);
            $net[$key] = bcadd($net[$key] ?? '0.00', $entry->direction === LedgerDirection::Credit ? $amount : bcmul($amount, '-1', Money::SCALE), Money::SCALE);
        }

        $keys = array_keys($net);
        sort($keys);
        $rows = [];

        foreach ($months as $month) {
            $key = $month->format('Y-m');
            $balance = '0.00';

            foreach ($keys as $seen) {
                if ($seen <= $key) {
                    $balance = bcadd($balance, $net[$seen], Money::SCALE);
                }
            }

            $report = $this->calculate->handle($month->year, $month->month);
            $rows[] = [
                'month' => $key,
                'deposits' => $report->deposits,
                'expenses' => $report->expenses,
                'fund_end' => $balance,
            ];
        }

        return $rows;
    }

    /**
     * What changed against the month before: deposits, expenses and the
     * fund balance at month end. Negative means down.
     *
     * @param  list<array{month: string, deposits: string, expenses: string, fund_end: string}>  $trend
     * @return array{deposits: string, expenses: string, fund: string}
     */
    private function changes(array $trend): array
    {
        $now = $trend[count($trend) - 1];
        $before = $trend[count($trend) - 2] ?? ['deposits' => '0.00', 'expenses' => '0.00', 'fund_end' => '0.00'];

        return [
            'deposits' => bcsub(Money::numeric($now['deposits']), Money::numeric($before['deposits']), Money::SCALE),
            'expenses' => bcsub(Money::numeric($now['expenses']), Money::numeric($before['expenses']), Money::SCALE),
            'fund' => bcsub(Money::numeric($now['fund_end']), Money::numeric($before['fund_end']), Money::SCALE),
        ];
    }

    /**
     * @return list<array{day: int, expenses: string, meals: int}>
     */
    private function daily(CarbonImmutable $month): array
    {
        [$first, $last] = BusinessCalendar::monthDates($month->year, $month->month);
        $expenses = [];
        $meals = [];

        foreach (GroceryPurchase::query()->active()->whereBetween('purchase_date', [$first, $last])->get(['purchase_date', 'grand_total']) as $purchase) {
            $day = (int) $purchase->purchase_date->format('j');
            $expenses[$day] = bcadd($expenses[$day] ?? '0.00', Money::numeric($purchase->grand_total), Money::SCALE);
        }

        foreach (Meal::query()->whereBetween('meal_date', [$first, $last])->get(['meal_date', 'count']) as $meal) {
            $day = (int) $meal->meal_date->format('j');
            $meals[$day] = ($meals[$day] ?? 0) + $meal->count;
        }

        return array_map(fn (int $day): array => [
            'day' => $day,
            'expenses' => $expenses[$day] ?? '0.00',
            'meals' => $meals[$day] ?? 0,
        ], range(1, (int) substr($last, 8, 2)));
    }

    /**
     * @return list<array{name: string, total: string}>
     */
    private function topItems(CarbonImmutable $month): array
    {
        [$first, $last] = BusinessCalendar::monthDates($month->year, $month->month);
        $totals = [];

        $items = GroceryPurchaseItem::query()
            ->whereHas('purchase', fn ($purchases) => $purchases->active()->whereBetween('purchase_date', [$first, $last]))
            ->get(['item_name', 'line_total']);

        foreach ($items as $item) {
            $name = mb_strtolower(trim($item->item_name));
            $totals[$name]['name'] ??= trim($item->item_name);
            $totals[$name]['total'] = bcadd($totals[$name]['total'] ?? '0.00', Money::numeric($item->line_total), Money::SCALE);
        }

        usort($totals, fn (array $a, array $b): int => bccomp($b['total'], $a['total'], Money::SCALE));

        return array_slice($totals, 0, self::TOP_ITEMS);
    }
}
