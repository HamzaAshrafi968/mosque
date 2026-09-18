<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\WorkSlot;
use App\Services\WorkHoursSettingsService;
use Tests\TestCase;

/**
 * مدة الفترة وتنسيقها والحد الأقصى القابل للتهيئة (BR-04/05/06/08).
 */
class WorkSlotDurationTest extends TestCase
{
    public function test_duration_minutes_are_computed_from_times(): void
    {
        $this->assertSame(120, WorkSlot::durationMinutes('06:00', '08:00'));
        $this->assertSame(360, WorkSlot::durationMinutes('14:00', '20:00'));
        $this->assertSame(30, WorkSlot::durationMinutes('09:15', '09:45'));
    }

    public function test_zero_and_reversed_ranges_yield_zero_duration(): void
    {
        $this->assertSame(0, WorkSlot::durationMinutes('10:00', '10:00'));
        $this->assertSame(0, WorkSlot::durationMinutes('22:00', '02:00'));
    }

    public function test_format_minutes_uses_arabic_hours_and_minutes(): void
    {
        $this->assertSame('8س', WorkSlot::formatMinutes(480));
        $this->assertSame('8س 30د', WorkSlot::formatMinutes(510));
        $this->assertSame('30د', WorkSlot::formatMinutes(30));
        $this->assertSame('0س', WorkSlot::formatMinutes(0));
    }

    public function test_max_slot_hours_defaults_to_twelve(): void
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        $this->assertSame(12.0, app(WorkHoursSettingsService::class)->maxSlotHours());
    }

    public function test_max_slot_hours_can_be_configured_per_mosque(): void
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        $settings = app(WorkHoursSettingsService::class);
        $settings->setMaxSlotHours(8);

        $this->assertSame(8.0, $settings->maxSlotHours());
    }
}
