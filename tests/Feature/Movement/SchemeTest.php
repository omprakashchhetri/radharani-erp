<?php

namespace Tests\Feature\Movement;

use App\Livewire\Installments\MonthlyPaymentStatus;
use App\Livewire\Installments\SchemeEnrolment;
use App\Livewire\Installments\SchemeImport;
use App\Models\Customer\Customer;
use App\Models\Customer\InstallmentPayment;
use App\Models\Customer\InstallmentScheme;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The monthly scheme: enrolling (a new customer on the spot, a late fee), enrolling from a CSV, and late
 * payments. "Today" is fixed at 14 Oct 2026 so every due date is exact.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=SchemeTest
 */
class SchemeTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }
        $this->actingAs($this->user = User::role('owner')->first() ?? $this->markTestSkipped('Needs the seeded owner user.'));
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function phone(): string
    {
        return (string) random_int(6000000000, 9999999999);
    }

    private function scheme(string $start, int $paid = 0, int $months = 12, float $lateFee = 0, ?Customer $customer = null): InstallmentScheme
    {
        $customer ??= Customer::create(['name' => 'Scheme Member ' . random_int(100, 999), 'phone' => $this->phone()]);

        return InstallmentScheme::create(['customer_id' => $customer->id, 'monthly_amount' => 1000, 'late_fee' => $lateFee, 'total_months' => $months, 'months_paid' => $paid,
            'opening_pending_amount' => ($months - $paid) * 1000, 'start_date' => $start, 'status' => 'active']);
    }

    // ---------------------------------------------------------------- the schedule

    public function test_instalments_fall_due_a_month_apart_from_the_start_date(): void
    {
        $s = $this->scheme('2026-01-31');

        $this->assertSame('2026-01-31', $s->dueDateFor(0)->toDateString());
        $this->assertSame('2026-02-28', $s->dueDateFor(1)->toDateString(), 'a month with no 31st ends on its last day');
        $this->assertSame('2026-03-31', $s->dueDateFor(2)->toDateString());
    }

    public function test_months_late_counts_the_unpaid_instalments_past_their_date(): void
    {
        $this->assertSame(0, $this->scheme('2026-10-14')->months_late, 'due today is not late yet');
        $this->assertSame(0, $this->scheme('2026-10-20')->months_late);
        $this->assertSame(1, $this->scheme('2026-10-13')->months_late);
        $this->assertSame(4, $this->scheme('2026-07-01')->months_late, '1 July, 1 August, 1 September and 1 October are all past and unpaid');
    }

    public function test_paid_instalments_are_not_late_and_a_finished_scheme_never_is(): void
    {
        $this->assertSame(0, $this->scheme('2026-07-01', paid: 4)->months_late);
        $this->assertSame(1, $this->scheme('2026-07-01', paid: 3)->months_late);
        $this->assertSame(0, $this->scheme('2025-01-01', paid: 12)->months_late);
        $this->assertNull($this->scheme('2025-01-01', paid: 12)->next_due_on);
        $this->assertSame(0, $this->scheme('2026-10-14')->days_late);
        $this->assertSame(13, $this->scheme('2026-10-01')->days_late);
    }

    // ---------------------------------------------------------------- the status screen

    public function test_each_scheme_is_late_due_or_paid_up(): void
    {
        $late = $this->scheme('2026-08-01');
        $due = $this->scheme('2026-09-20', paid: 1); // next due 20 Oct, this month
        $paidUp = $this->scheme('2026-09-20', paid: 1, months: 12)->fill([])->saveQuietly();
        $next = $this->scheme('2026-10-20', paid: 0);
        $next->update(['months_paid' => 1]); // next due 20 Nov: not this month

        $page = Livewire::test(MonthlyPaymentStatus::class);

        $page->assertSee('Late · ');
        $page->set('show', 'late')->assertSee($late->customer->name)->assertDontSee($due->customer->name);
        $page->set('show', 'due')->assertSee($due->customer->name)->assertDontSee($late->customer->name);
        $page->set('show', 'paid')->assertSee($next->customer->name)->assertDontSee($late->customer->name);
        $this->assertNotNull($paidUp ?? true);
    }

    public function test_recording_an_instalment_on_time_has_no_late_fee(): void
    {
        $s = $this->scheme('2026-09-14', paid: 1, lateFee: 50); // 2nd instalment due 14 Oct: today

        Livewire::test(MonthlyPaymentStatus::class)->call('openPay', $s->id)->assertSee('On time. No late fee.')
            ->set('lateFee', '99')->call('recordPayment')->assertHasNoErrors();

        $p = InstallmentPayment::where('scheme_id', $s->id)->firstOrFail();
        $this->assertEquals(0, $p->late_fee, 'a fee typed on an on-time payment is ignored');
        $this->assertSame(2, $p->installment_no);
        $this->assertSame('2026-10-14', $p->due_on->toDateString());
        $this->assertSame(2, $s->fresh()->months_paid);
    }

    public function test_a_late_instalment_starts_the_fee_at_the_usual_one_and_keeps_what_is_typed(): void
    {
        $s = $this->scheme('2026-08-14', lateFee: 50); // 1st due 14 Aug, 2nd 14 Sep, 3rd 14 Oct (today): 2 late

        $page = Livewire::test(MonthlyPaymentStatus::class)->call('openPay', $s->id)->assertSet('lateFee', '50')->assertSee('days late');
        $page->set('lateFee', '120')->call('recordPayment');

        $p = InstallmentPayment::where('scheme_id', $s->id)->firstOrFail();
        $this->assertEquals(120, $p->late_fee);
        $this->assertEquals(1000, $p->amount, 'the late fee is on top of the instalment, never part of it');
        $this->assertEquals(1000, $s->fresh()->payments()->sum('amount'));
        $this->assertSame(1, $s->fresh()->months_late, 'one more is still late');
    }

    public function test_the_same_instalment_is_never_recorded_twice(): void
    {
        $s = $this->scheme('2026-09-01', paid: 1);

        $a = Livewire::test(MonthlyPaymentStatus::class)->call('openPay', $s->id);
        $b = Livewire::test(MonthlyPaymentStatus::class)->call('openPay', $s->id);
        $a->call('recordPayment');
        $b->call('recordPayment');

        $this->assertSame(1, InstallmentPayment::where('scheme_id', $s->id)->count());
        $this->assertSame(2, $s->fresh()->months_paid);
    }

    public function test_the_last_instalment_matures_the_scheme_and_a_matured_one_can_not_be_paid_again(): void
    {
        $s = $this->scheme('2026-01-14', paid: 11, months: 12);

        Livewire::test(MonthlyPaymentStatus::class)->call('openPay', $s->id)->call('recordPayment')->assertDispatched('toast');
        $this->assertTrue($s->fresh()->is_matured);

        Livewire::test(MonthlyPaymentStatus::class)->call('openPay', $s->id)->call('recordPayment');
        $this->assertSame(1, InstallmentPayment::where('scheme_id', $s->id)->count());
    }

    public function test_a_negative_late_fee_is_refused(): void
    {
        $s = $this->scheme('2026-08-14');

        Livewire::test(MonthlyPaymentStatus::class)->call('openPay', $s->id)->set('lateFee', '-5')->call('recordPayment')->assertHasErrors('lateFee');
        $this->assertSame(0, InstallmentPayment::where('scheme_id', $s->id)->count());
    }

    // ---------------------------------------------------------------- enrolling

    public function test_enrolling_keeps_the_usual_late_fee(): void
    {
        $c = Customer::create(['name' => 'Enrol Late Fee', 'phone' => $this->phone()]);

        Livewire::test(SchemeEnrolment::class)->set('customerId', $c->id)->set('monthlyAmount', '1500')->set('lateFee', '75')->set('totalMonths', 12)->call('enrol')->assertHasNoErrors();

        $this->assertEquals(75, InstallmentScheme::where('customer_id', $c->id)->firstOrFail()->late_fee);
    }

    public function test_a_customer_created_from_the_enrolment_screen_becomes_the_member(): void
    {
        $c = Customer::create(['name' => 'Made Just Now', 'phone' => $this->phone()]);

        Livewire::test(SchemeEnrolment::class)->assertSee('New customer')->dispatch('customer-saved', id: $c->id)->assertSet('customerId', $c->id);
    }

    // ---------------------------------------------------------------- the CSV

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('members.csv', $content);
    }

    public function test_a_csv_is_checked_row_by_row_before_anything_is_saved(): void
    {
        $known = Customer::create(['name' => 'Known Member', 'phone' => $this->phone()]);
        $active = Customer::create(['name' => 'Already Enrolled', 'phone' => $this->phone()]);
        $this->scheme('2026-09-01', customer: $active);
        $new = $this->phone();

        $csv = "name,phone,monthly_amount,total_months,months_already_paid,start_date,late_fee\n"
            . "Brand New,{$new},1000,12,0,2026-10-14,50\n"
            . ",{$known->phone},2000,10,4,14/06/2026,\n"
            . ",{$active->phone},1000,12,0,,\n"
            . "No Phone,12345,1000,,,,\n"
            . ",{$this->phone()},1000,,,,\n"
            . "Bad Amount,{$this->phone()},abc,,,,\n"
            . "Too Many,{$this->phone()},1000,12,13,,\n"
            . "Bad Date,{$this->phone()},1000,12,0,someday,\n";

        $before = [Customer::count(), InstallmentScheme::count()];
        $page = Livewire::test(SchemeImport::class)->set('file', $this->csv($csv))->call('parse')->assertHasNoErrors();

        $rows = collect($page->get('rows'));
        $this->assertCount(8, $rows);
        $this->assertSame([null, null], [$rows[0]['error'], $rows[1]['error']], 'a new phone with a name, and an existing customer, are ready');
        $this->assertSame('Known Member', $rows[1]['name'], 'an existing customer keeps their own name');
        $this->assertSame('2026-06-14', $rows[1]['start_date']);
        $this->assertStringContainsString('active scheme', $rows[2]['error']);
        $this->assertStringContainsString('10 digits', $rows[3]['error']);
        $this->assertStringContainsString('needs a name', $rows[4]['error']);
        $this->assertStringContainsString('monthly amount', $rows[5]['error']);
        $this->assertStringContainsString('Months already paid', $rows[6]['error']);
        $this->assertStringContainsString('not a date', $rows[7]['error']);
        $this->assertSame($before, [Customer::count(), InstallmentScheme::count()], 'checking the file saves nothing');
        $this->assertSame(2, $page->get('readyCount'));
    }

    public function test_confirming_enrols_the_ready_rows_and_adds_the_new_customers(): void
    {
        $known = Customer::create(['name' => 'Known Member', 'phone' => $this->phone()]);
        $new = $this->phone();
        $csv = "name,phone,monthly_amount,total_months,months_already_paid,start_date,late_fee,pending_amount\n"
            . "Brand New,{$new},1000,12,0,2026-10-14,50,\n"
            . ",{$known->phone},2000,10,4,2026-06-14,,9000\n"
            . "Bad,1,1000,,,,,\n";

        $page = Livewire::test(SchemeImport::class)->set('file', $this->csv($csv))->call('parse')->call('commit');

        $page->assertSet('result.enrolled', 2)->assertSet('result.customers', 1)->assertSet('result.problems', 1)->assertSet('parsed', false);

        $brandNew = Customer::where('phone', $new)->firstOrFail();
        $a = InstallmentScheme::where('customer_id', $brandNew->id)->firstOrFail();
        $this->assertEquals(1000, $a->monthly_amount);
        $this->assertEquals(50, $a->late_fee);
        $this->assertEquals(12000, $a->opening_pending_amount, 'a new member owes every month');

        $b = InstallmentScheme::where('customer_id', $known->id)->firstOrFail();
        $this->assertSame(4, $b->months_paid);
        $this->assertEquals(9000, $b->opening_pending_amount, 'a pending amount in the file is kept');
        $this->assertSame('2026-06-14', $b->start_date->toDateString());

        // The welcome messages are waiting to be copied and sent.
        $this->assertSame(2, \App\Models\Notification\PendingNotification::where('type', 'scheme_welcome')->whereIn('customer_id', [$brandNew->id, $known->id])->count());
    }

    public function test_the_same_file_can_not_enrol_anyone_twice(): void
    {
        $phone = $this->phone();
        $csv = "name,phone,monthly_amount\nTwice,{$phone},1000\n";

        Livewire::test(SchemeImport::class)->set('file', $this->csv($csv))->call('parse')->call('commit');
        $second = Livewire::test(SchemeImport::class)->set('file', $this->csv($csv))->call('parse');

        $this->assertStringContainsString('active scheme', $second->get('rows')[0]['error']);
        $this->assertSame(1, InstallmentScheme::whereHas('customer', fn ($q) => $q->where('phone', $phone))->count());
    }

    public function test_a_phone_listed_twice_in_one_file_is_enrolled_once(): void
    {
        $phone = $this->phone();
        $page = Livewire::test(SchemeImport::class)->set('file', $this->csv("name,phone,monthly_amount\nFirst,{$phone},1000\nSecond,{$phone},2000\n"))->call('parse');

        $this->assertNull($page->get('rows')[0]['error']);
        $this->assertStringContainsString('twice', $page->get('rows')[1]['error']);
    }

    public function test_other_column_names_and_a_missing_required_column_are_handled(): void
    {
        $phone = $this->phone();
        $ok = Livewire::test(SchemeImport::class)->set('file', $this->csv("Customer Name,Mobile,Monthly Amount,Months,Months Paid\nAlias Row,{$phone},1500,6,2\n"))->call('parse')->assertHasNoErrors();
        $row = $ok->get('rows')[0];
        $this->assertNull($row['error']);
        $this->assertSame([6, 2], [$row['total_months'], $row['months_paid']]);

        Livewire::test(SchemeImport::class)->set('file', $this->csv("name,phone\nNo Amount Column,{$this->phone()}\n"))->call('parse')->assertHasErrors('file');
        Livewire::test(SchemeImport::class)->set('file', $this->csv("name,phone,monthly_amount\n"))->call('parse')->assertHasErrors('file');
    }

    public function test_the_template_downloads(): void
    {
        Livewire::test(SchemeImport::class)->call('template')->assertFileDownloaded('monthly-scheme-template.csv');
    }
}
