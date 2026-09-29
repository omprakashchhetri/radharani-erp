<?php
namespace App\Livewire\Admin;

use App\Models\Customer\Customer;
use App\Support\SpreadsheetReader;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Bulk Customer Import — CSV/XLSX upload via SpreadsheetReader (the one
 * mandated spreadsheet-input path — see CLAUDE.md — same reader Stock's
 * own bulk import uses).
 *
 * customers.imported_from_tally already exists as a real column, which is
 * exactly what this screen is for: bringing in a customer list from Tally
 * (or any other export) in one go. Expected columns: name, phone, address,
 * email, gstin — matching Add/Edit Customer's own fields, so nothing is
 * invented beyond what that screen already collects. Rows missing a name
 * or phone, or whose phone already exists, are skipped and listed so
 * nothing is silently overwritten (rule 3 — real user_id — is respected
 * too: created customers get no owner attribution, since customers aren't
 * user-scoped the way staff actions are).
 */
class CustomerBulkImport extends Component
{
    use WithFileUploads;

    public $file;
    public array $imported = [];
    public array $skipped = [];
    public bool $done = false;

    public function import()
    {
        $this->validate(['file' => 'required|file|mimes:csv,txt,xlsx|max:2048']);

        [$headers, $rows] = SpreadsheetReader::read($this->file->getRealPath(), $this->file->getClientOriginalExtension(), 1000);
        $header = array_map(fn ($h) => strtolower(trim($h)), $headers);

        $this->imported = [];
        $this->skipped = [];

        foreach ($rows as $row) {
            $data = array_combine($header, array_pad($row, count($header), null));
            $name = trim((string) ($data['name'] ?? ''));
            $phone = trim((string) ($data['phone'] ?? ''));

            if ($name === '' || $phone === '') {
                $this->skipped[] = ($name ?: '(no name)').' — missing name or phone';
                continue;
            }

            if (Customer::where('phone', $phone)->exists()) {
                $this->skipped[] = "{$name} ({$phone}) — phone already in the system";
                continue;
            }

            do {
                $code = strtoupper(Str::random(6));
            } while (Customer::where('referral_code', $code)->exists());

            Customer::create([
                'name' => $name,
                'phone' => $phone,
                'address' => $data['address'] ?? null,
                'email' => $data['email'] ?? null,
                'gstin' => $data['gstin'] ?? null,
                'status' => 'past_customer',
                'referral_code' => $code,
                'imported_from_tally' => true,
            ]);

            $this->imported[] = "{$name} ({$phone})";
        }

        $this->done = true;
        $this->reset('file');
        $this->dispatch('toast', message: count($this->imported).' customer(s) imported.', type: 'success');
    }

    public function render()
    {
        return view('livewire.admin.customer-bulk-import')->layout('components.layouts.app', ['title' => 'Bulk Import Customers — Radharani Jewellery']);
    }
}
