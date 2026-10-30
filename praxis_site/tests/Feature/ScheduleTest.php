<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** /schedule: month view from GET /booking/slots, pick a day and a time (WAT), then POST /bookings. */
class ScheduleTest extends TestCase
{
    private string $month;

    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00', 'Africa/Lagos'));
        $this->month = '2026-11';
        $this->day = '2026-11-05';
    }

    private function slots(): array
    {
        return ['data' => ['month' => $this->month, 'timezone' => 'Africa/Lagos', 'days' => [
            ['date' => '2026-11-04', 'slots' => ['09:00']],
            ['date' => $this->day, 'slots' => ['10:30', '14:30']],
        ]]];
    }

    public function test_the_month_view_links_only_the_days_with_free_times(): void
    {
        $this->fakeHq(['booking/slots*' => Http::response($this->slots())]);

        $response = $this->get('/schedule')->assertOk();

        $response->assertSee('November 2026')
            ->assertSee('Times are in West Africa Time (WAT)')
            ->assertSee('href="' . url('/schedule?month=2026-11&amp;date=2026-11-05') . '#step-time"', false)
            ->assertSee('Thursday 5 November 2026, 2 times available')
            ->assertSee('Choose a day in the calendar to see its free times.');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/booking/slots?month=2026-11'));
    }

    public function test_picking_a_day_shows_its_times_and_the_form(): void
    {
        $this->fakeHq(['booking/slots*' => Http::response($this->slots())]);

        $this->get('/schedule?month=2026-11&date=2026-11-05')->assertOk()
            ->assertSee('Free times on Thursday 5 November 2026 (WAT)')
            ->assertSee('value="10:30"', false)
            ->assertSee('value="14:30"', false)
            ->assertSee('name="date" value="2026-11-05"', false)
            ->assertSee('What would you like to focus on?');
    }

    public function test_months_before_this_one_are_not_offered(): void
    {
        $this->fakeHq(['booking/slots*' => Http::response($this->slots())]);

        $this->get('/schedule?month=2025-01')->assertOk()->assertDontSee('Previous month');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), 'month=2026-11'));
    }

    public function test_a_booking_is_sent_and_the_requested_time_shown_in_wat(): void
    {
        $this->fakeHq([
            'bookings' => Http::response(['data' => ['start' => '2026-11-05T10:30:00+01:00', 'focus' => 'Custom Software']], 201),
            'booking/slots*' => Http::response($this->slots()),
        ]);

        $this->from('/schedule?month=2026-11&date=2026-11-05')->post('/schedule', [
            'full_name' => 'Ada Test', 'email' => 'ada@example.com', 'phone' => '+234 800 000 0000', 'enterprise_name' => 'Example Ltd',
            'focus' => 'Custom Software', 'custom_focus' => 'dropped', 'date' => '2026-11-05', 'time' => '10:30',
        ])->assertRedirect('/schedule');

        $sent = $this->sentJson('/bookings');
        $this->assertSame('2026-11-05', $sent['date']);
        $this->assertSame('10:30', $sent['time']);
        $this->assertSame('Custom Software', $sent['focus']);
        $this->assertArrayNotHasKey('custom_focus', $sent);

        $this->get('/schedule')->assertSee('We’ve received your request for a free audit and demo')
            ->assertSee('Requested time: Thursday 5 November 2026, 10:30 (WAT).');
    }

    public function test_a_time_taken_meanwhile_asks_for_another_time(): void
    {
        $this->fakeHq([
            'bookings' => Http::response(['error' => ['code' => 'slot_taken']], 409),
            'booking/slots*' => Http::response($this->slots()),
        ]);

        $this->from('/schedule?month=2026-11&date=2026-11-05')
            ->post('/schedule', ['full_name' => 'Ada Test', 'date' => '2026-11-05', 'time' => '10:30'])
            ->assertRedirect('/schedule?month=2026-11&date=2026-11-05')
            ->assertSessionHasErrors(['time' => 'That time was booked a moment ago. Choose another time.']);

        $this->get('/schedule?month=2026-11&date=2026-11-05')
            ->assertSee('That time was booked a moment ago.')
            ->assertSee('value="Ada Test"', false);
    }

    public function test_hq_field_errors_land_next_to_the_fields(): void
    {
        $this->fakeHq([
            'bookings' => Http::response(['error' => ['code' => 'invalid', 'message' => 'Check the fields.', 'fields' => [
                'phone' => ['Enter a phone number.'], 'custom_focus' => ['Describe your focus in 40 characters or fewer.'],
            ]]], 422),
            'booking/slots*' => Http::response($this->slots()),
        ]);

        $this->from('/schedule?month=2026-11&date=2026-11-05')
            ->post('/schedule', ['full_name' => 'Ada Test', 'focus' => 'Other', 'custom_focus' => str_repeat('x', 50), 'date' => '2026-11-05', 'time' => '14:30'])
            ->assertSessionHasErrors(['phone', 'custom_focus']);

        $this->get('/schedule?month=2026-11&date=2026-11-05')
            ->assertSee('Enter a phone number.')
            ->assertSee('Describe your focus in 40 characters or fewer.')
            ->assertSee('value="14:30" checked', false);
    }

    public function test_a_second_open_request_shows_hqs_already_booked_message_above_the_form(): void
    {
        $message = 'You already have a free audit and demo request with us. We’ll confirm it by email.';
        $this->fakeHq([
            'bookings' => Http::response(['error' => ['code' => 'already_booked', 'message' => $message]], 409),
            'booking/slots*' => Http::response($this->slots()),
        ]);

        $this->from('/schedule?month=2026-11&date=2026-11-05')
            ->post('/schedule', ['full_name' => 'Ada Test', 'email' => 'ada@example.com', 'date' => '2026-11-05', 'time' => '10:30'])
            ->assertRedirect('/schedule?month=2026-11&date=2026-11-05')
            ->assertSessionHas('hq_error', $message)
            ->assertSessionDoesntHaveErrors('time');

        $this->get('/schedule?month=2026-11&date=2026-11-05')
            ->assertSee('<div class="alert alert-warning" role="alert"><i data-lucide="triangle-alert"></i><p>' . e($message) . '</p></div>', false)
            ->assertSee('value="Ada Test"', false)
            ->assertSee('value="10:30" checked', false)
            ->assertDontSee('That time was booked a moment ago.');
    }

    public function test_already_booked_without_a_message_from_hq_uses_the_sites_words(): void
    {
        $this->fakeHq([
            'bookings' => Http::response(['error' => ['code' => 'already_booked']], 409),
            'booking/slots*' => Http::response($this->slots()),
        ]);

        $this->get('/schedule?month=2026-11&date=2026-11-05'); // the visitor loads the page first: settings are read
        $this->from('/schedule?month=2026-11&date=2026-11-05')
            ->post('/schedule', ['full_name' => 'Ada Test', 'date' => '2026-11-05', 'time' => '10:30']);

        $this->get('/schedule?month=2026-11&date=2026-11-05')
            ->assertSee('You already have a request for a free audit and demo with us. We’ll confirm it by email. To change it, email us at info@jbspraxis.com.');
    }

    public function test_the_calendar_stops_92_days_ahead(): void
    {
        // Today is 2 November 2026 (setUp), so the last bookable day is 2 February 2027
        $this->fakeHq(['booking/slots*' => Http::response(['data' => ['month' => '2027-02', 'timezone' => 'Africa/Lagos', 'days' => [
            ['date' => '2027-02-02', 'slots' => ['09:00']],
            ['date' => '2027-02-03', 'slots' => ['09:00']],
        ]]])]);

        $response = $this->get('/schedule?month=2027-02')->assertOk()->assertSee('February 2027')->assertDontSee('Next month');
        $response->assertSee('href="' . url('/schedule?month=2027-02&amp;date=2027-02-02') . '#step-time"', false)
            ->assertDontSee('date=2027-02-03', false)
            ->assertSee('Wednesday 3 February 2027, no times available');

        // A later month or a day beyond the window is never offered
        $this->get('/schedule?month=2027-05')->assertOk()->assertSee('February 2027');
        $this->get('/schedule?month=2027-02&date=2027-02-03')->assertOk()->assertDontSee('name="date" value="2027-02-03"', false);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'month=2027-05') || str_contains($r->url(), 'month=2027-03'));
    }

    public function test_hq_down_shows_the_calm_message(): void
    {
        Http::fake([self::HQ . '/booking/slots*' => $this->down(), self::HQ . '/settings' => $this->down()]);

        $this->get('/schedule')->assertStatus(503)
            ->assertSee('This page isn’t available right now')
            ->assertSee('fallback@jbspraxis.com');
    }
}
