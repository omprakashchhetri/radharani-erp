<?php
namespace App\Livewire\Installments;

use App\Models\Customer\Customer;
use App\Models\Customer\InstallmentScheme;
use App\Support\MessageTemplates;
use App\Support\Phone;
use App\Support\SpreadsheetReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Enrol many members into the monthly scheme from one CSV or XLSX (11 Oct meeting). The upload is read and
 * checked first and shown row by row; nothing is saved until the ready rows are confirmed. A phone number that
 * is not a customer yet becomes one (the name is then required). A member who already has an active scheme is
 * left alone and the row says so, so the same upload can never enrol anyone twice.
 */
class SchemeImport extends Component
{
    use WithFileUploads;

    public const MAX_ROWS = 500;

    public const COLUMNS = ['name', 'phone', 'monthly_amount', 'total_months', 'months_already_paid', 'start_date', 'late_fee', 'pending_amount'];

    // What a column may be called in a sheet, so a file from Excel or Tally still reads.
    private const ALIASES = [
        'name' => ['name', 'customer', 'customer name', 'member'],
        'phone' => ['phone', 'mobile', 'mobile number', 'phone number'],
        'monthly_amount' => ['monthly_amount', 'monthly amount', 'amount', 'instalment', 'installment', 'monthly'],
        'total_months' => ['total_months', 'total months', 'months', 'months in the scheme', 'length'],
        'months_already_paid' => ['months_already_paid', 'months already paid', 'months paid', 'paid', 'months_paid'],
        'start_date' => ['start_date', 'start date', 'start', 'joined'],
        'late_fee' => ['late_fee', 'late fee'],
        'pending_amount' => ['pending_amount', 'pending amount', 'pending', 'amount pending'],
    ];

    public $file;

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    public bool $parsed = false;
    public array $result = [];

    public function updatedFile(): void
    {
        $this->reset(['rows', 'parsed', 'result']);
    }

    public function template()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::COLUMNS, ',', '"', '\\');
            fputcsv($out, ['Anita Roy', '9830012345', 1000, 12, 0, now()->toDateString(), 50, ''], ',', '"', '\\');
            fputcsv($out, ['Sumit Das', '9830054321', 2000, 12, 4, now()->subMonths(4)->toDateString(), '', ''], ',', '"', '\\');
            fclose($out);
        }, 'monthly-scheme-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function parse(): void
    {
        $this->validate(['file' => 'required|file|mimes:csv,txt,xlsx|max:2048'], ['file.required' => 'Choose the file to upload.']);

        [$headers, $raw] = SpreadsheetReader::read($this->file->getRealPath(), $this->file->getClientOriginalExtension(), self::MAX_ROWS + 1);
        $map = $this->columnMap($headers);

        if (! isset($map['phone']) || ! isset($map['monthly_amount'])) {
            $this->addError('file', 'The first row must name the columns, and "phone" and "monthly_amount" are needed. Download the template to see them.');

            return;
        }
        if (count($raw) > self::MAX_ROWS) {
            $this->addError('file', 'That is more than ' . self::MAX_ROWS . ' rows. Split it into smaller files.');

            return;
        }
        if (! $raw) {
            $this->addError('file', 'The file has no rows under the column names.');

            return;
        }

        $seen = [];
        $this->rows = [];
        foreach ($raw as $i => $cells) {
            $row = $this->checkRow($i + 2, array_map(fn ($col) => $col === null ? '' : trim((string) ($cells[$col] ?? '')), $map), $seen);
            if (! $row['error']) {
                $seen[$row['phone']] = true;
            }
            $this->rows[] = $row;
        }
        $this->parsed = true;
        $this->result = [];
    }

    /** @return array<string, int|null> column name => index in the sheet */
    private function columnMap(array $headers): array
    {
        $norm = array_map(fn ($h) => strtolower(trim(str_replace(['_', '-'], ' ', (string) $h))), $headers);
        $map = [];
        foreach (self::ALIASES as $col => $names) {
            $map[$col] = null;
            foreach ($names as $alias) {
                $at = array_search(strtolower(str_replace('_', ' ', $alias)), $norm, true);
                if ($at !== false) {
                    $map[$col] = $at;
                    break;
                }
            }
        }

        return array_filter($map, fn ($v) => $v !== null) + array_fill_keys(array_keys($map), null);
    }

    /** One row, checked on its own: its figures cleaned up, and the reason it can not be enrolled if there is one. */
    private function checkRow(int $line, array $c, array $seen): array
    {
        $phone = Phone::normalize($c['phone'] ?? '');
        $row = [
            'line' => $line, 'name' => $c['name'] ?? '', 'phone' => $phone, 'monthly_amount' => null, 'total_months' => 12, 'months_paid' => 0,
            'start_date' => today()->toDateString(), 'late_fee' => 0.0, 'pending' => null, 'customer_id' => null, 'error' => null,
        ];

        $fail = function (string $message) use (&$row) {
            $row['error'] ??= $message;
        };

        strlen($phone) === 10 ? null : $fail('The phone number is not 10 digits.');
        isset($seen[$phone]) ? $fail('This phone number is listed twice in the file.') : null;

        $customer = strlen($phone) === 10 ? Customer::where('phone', $phone)->first() : null;
        $row['customer_id'] = $customer?->id;
        if ($customer) {
            $row['name'] = $customer->name;
            if (InstallmentScheme::where('customer_id', $customer->id)->where('status', 'active')->exists()) {
                $fail('Already has an active scheme.');
            }
        } elseif (strlen($phone) === 10 && $row['name'] === '') {
            $fail('A new customer needs a name.');
        }

        is_numeric($c['monthly_amount'] ?? '') && (float) $c['monthly_amount'] >= 1 ? $row['monthly_amount'] = (float) $c['monthly_amount'] : $fail('The monthly amount is missing or not a number.');

        if (($c['total_months'] ?? '') !== '') {
            ctype_digit($c['total_months']) && (int) $c['total_months'] >= 1 && (int) $c['total_months'] <= 60 ? $row['total_months'] = (int) $c['total_months'] : $fail('Months in the scheme must be 1 to 60.');
        }
        if (($c['months_already_paid'] ?? '') !== '') {
            ctype_digit($c['months_already_paid']) && (int) $c['months_already_paid'] <= $row['total_months'] ? $row['months_paid'] = (int) $c['months_already_paid'] : $fail('Months already paid is not a number up to the months in the scheme.');
        }
        if (($c['start_date'] ?? '') !== '') {
            ($date = $this->date($c['start_date'])) ? $row['start_date'] = $date : $fail('The start date is not a date. Use 2026-10-14 or 14/10/2026.');
        }
        if (($c['late_fee'] ?? '') !== '') {
            is_numeric($c['late_fee']) && (float) $c['late_fee'] >= 0 ? $row['late_fee'] = (float) $c['late_fee'] : $fail('The late fee is not a number.');
        }
        if (($c['pending_amount'] ?? '') !== '') {
            is_numeric($c['pending_amount']) && (float) $c['pending_amount'] >= 0 ? $row['pending'] = (float) $c['pending_amount'] : $fail('The pending amount is not a number.');
        }

        return $row;
    }

    private function date(string $value): ?string
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j M Y', 'd M Y'] as $format) {
            try {
                $d = Carbon::createFromFormat('!' . $format, $value);
            } catch (\Throwable) {
                continue; // not this format
            }
            if ($d && $d->format($format) === $value) {
                return $d->toDateString();
            }
        }

        return null;
    }

    public function getReadyCountProperty(): int
    {
        return collect($this->rows)->whereNull('error')->count();
    }

    public function commit(): void
    {
        $enrolled = 0;
        $customers = 0;
        $skipped = [];

        foreach ($this->rows as $row) {
            if ($row['error']) {
                continue;
            }
            DB::transaction(function () use ($row, &$enrolled, &$customers, &$skipped) {
                $customer = Customer::where('phone', $row['phone'])->first();
                if (! $customer) {
                    $customer = Customer::create(['name' => $row['name'], 'phone' => $row['phone'], 'status' => 'past_customer']);
                    $customers++;
                } elseif (InstallmentScheme::where('customer_id', $customer->id)->where('status', 'active')->exists()) {
                    $skipped[] = "{$customer->name} ({$row['phone']}): already has an active scheme";

                    return;
                }

                $paid = (int) $row['months_paid'];
                $scheme = InstallmentScheme::create([
                    'customer_id' => $customer->id,
                    'monthly_amount' => $row['monthly_amount'],
                    'late_fee' => $row['late_fee'],
                    'total_months' => $row['total_months'],
                    'months_paid' => $paid,
                    'opening_pending_amount' => $row['pending'] ?? ($row['total_months'] - $paid) * $row['monthly_amount'],
                    'start_date' => $row['start_date'],
                    'status' => 'active',
                ]);
                MessageTemplates::queue('scheme_welcome', $customer, MessageTemplates::schemeWelcome($scheme), 'installment_scheme', $scheme->id);
                $enrolled++;
            });
        }

        $this->result = ['enrolled' => $enrolled, 'customers' => $customers, 'skipped' => $skipped, 'problems' => collect($this->rows)->whereNotNull('error')->count()];
        $this->reset(['file', 'rows', 'parsed']);
        $this->dispatch('toast', message: "{$enrolled} " . \Illuminate\Support\Str::plural('member', $enrolled) . ' enrolled.', type: 'success');
    }

    public function render()
    {
        return view('livewire.installments.scheme-import')->layout('components.layouts.app', ['title' => 'Enrol from a CSV — Radharani Jewellery ERP']);
    }
}
