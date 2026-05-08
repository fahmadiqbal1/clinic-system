<?php

namespace Tests\Feature\Attendance;

use App\Models\StaffShift;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    private User $staff;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create();
        $this->staff->assignRole('Doctor');

        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');
    }

    public function test_clock_in_creates_open_shift(): void
    {
        $r = $this->actingAs($this->staff)
            ->postJson('/attendance/clock-in');

        $r->assertOk()
          ->assertJsonStructure(['status', 'clocked_in_at']);

        $this->assertDatabaseHas('staff_shifts', [
            'user_id'        => $this->staff->id,
            'clocked_out_at' => null,
        ]);
    }

    public function test_clock_out_closes_open_shift(): void
    {
        StaffShift::create([
            'user_id'       => $this->staff->id,
            'clocked_in_at' => now(),
        ]);

        $r = $this->actingAs($this->staff)
            ->postJson('/attendance/clock-out');

        $r->assertOk()
          ->assertJsonStructure(['status', 'duration_hours']);

        $shift = StaffShift::where('user_id', $this->staff->id)->first();
        $this->assertNotNull($shift->clocked_out_at);
    }

    public function test_duplicate_clock_in_is_blocked(): void
    {
        // Create an already-open shift for today
        StaffShift::create([
            'user_id'       => $this->staff->id,
            'clocked_in_at' => now(),
        ]);

        $r = $this->actingAs($this->staff)
            ->postJson('/attendance/clock-in');

        $r->assertStatus(409);

        $this->assertSame(
            1,
            StaffShift::where('user_id', $this->staff->id)->whereNull('clocked_out_at')->count()
        );
    }

    public function test_owner_attendance_index_shows_shift_log(): void
    {
        $nurse = User::factory()->create(['is_active' => true]);
        $nurse->assignRole('Receptionist');

        StaffShift::create([
            'user_id'        => $nurse->id,
            'clocked_in_at'  => now()->subHours(6),
            'clocked_out_at' => now()->subHours(2),
        ]);

        $r = $this->actingAs($this->owner)->get('/owner/attendance');

        $r->assertOk()
          ->assertSee($nurse->name);
    }

    public function test_shift_duration_is_calculated_correctly(): void
    {
        $clockIn  = Carbon::now()->subMinutes(150);
        $clockOut = Carbon::now();

        $shift = StaffShift::create([
            'user_id'        => $this->staff->id,
            'clocked_in_at'  => $clockIn,
            'clocked_out_at' => $clockOut,
        ]);

        $this->assertEqualsWithDelta(150, $shift->durationMinutes(), 1);
    }

    public function test_owner_attendance_show_flags_unclosed_shift(): void
    {
        // Open shift started 13 hours ago — should appear as "Open" in the view
        StaffShift::create([
            'user_id'       => $this->staff->id,
            'clocked_in_at' => now()->subHours(13),
        ]);

        $r = $this->actingAs($this->owner)
            ->get("/owner/attendance/{$this->staff->id}");

        $r->assertOk()
          ->assertSee('On Shift');
    }
}
