<?php

namespace Tests\Feature\Settings;

use App\Concerns\BusinessCalendar;
use App\Concerns\BusinessDateRules;
use App\Models\MessSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BusinessDateRulesTest extends TestCase
{
    use RefreshDatabase;

    private function passes(string $date): bool
    {
        $rules = new class
        {
            use BusinessDateRules;

            /** @return array<string, mixed> */
            public function rules(): array
            {
                return ['date' => $this->businessDateRules()];
            }
        };

        return Validator::make(['date' => $date], $rules->rules())->passes();
    }

    public function test_today_is_evaluated_in_dhaka()
    {
        // 01:00 on 19 Oct in Dhaka, still 18 Oct in UTC.
        $this->travelTo('2026-10-18 19:00:00');

        $this->assertSame('2026-10-19', BusinessCalendar::today()->toDateString());
        $this->assertTrue($this->passes('2026-10-19'));
        $this->assertFalse($this->passes('2026-10-20'));
    }

    public function test_a_future_date_in_dhaka_is_rejected()
    {
        // 23:00 on 18 Oct in Dhaka.
        $this->travelTo('2026-10-18 17:00:00');

        $this->assertTrue($this->passes('2026-10-18'));
        $this->assertFalse($this->passes('2026-10-19'));
    }

    public function test_a_date_before_the_fund_start_date_is_rejected()
    {
        $this->travelTo('2026-10-18 06:00:00');
        MessSetting::current()->update(['fund_start_date' => '2026-10-01']);

        $this->assertFalse($this->passes('2026-09-30'));
        $this->assertTrue($this->passes('2026-10-01'));
    }

    public function test_any_past_date_passes_until_the_fund_start_date_is_set()
    {
        $this->travelTo('2026-10-18 06:00:00');

        $this->assertTrue($this->passes('2020-01-01'));
    }

    public function test_a_date_must_be_given_as_y_m_d()
    {
        $this->travelTo('2026-10-18 06:00:00');

        $this->assertFalse($this->passes('18/10/2026'));
        $this->assertFalse($this->passes(''));
    }

    public function test_month_boundaries_are_in_dhaka()
    {
        $this->assertSame(['2026-02-01', '2026-02-28'], BusinessCalendar::monthDates(2026, 2));

        $range = BusinessCalendar::monthRange(2026, 10);

        $this->assertSame('2026-09-30 18:00:00', $range['start']->toDateTimeString());
        $this->assertSame('2026-10-31 18:00:00', $range['end']->toDateTimeString());
        $this->assertSame('UTC', $range['start']->getTimezone()->getName());
    }

    public function test_an_instant_falls_on_its_dhaka_date()
    {
        $this->assertSame('2026-11-01', BusinessCalendar::dateOf(now()->setDateTime(2026, 10, 31, 18, 0)));
        $this->assertSame('2026-10-31', BusinessCalendar::dateOf(now()->setDateTime(2026, 10, 31, 17, 59)));
    }
}
