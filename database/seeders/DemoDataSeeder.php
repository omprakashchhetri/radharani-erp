<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerMaterialJob;
use App\Models\Customer\InstallmentPayment;
use App\Models\Customer\InstallmentScheme;
use App\Models\Customer\LoyaltyTransaction;
use App\Models\Employee;
use App\Models\Exchange\ExchangeTransaction;
use App\Models\Exchange\RefineryBatch;
use App\Models\Movement\KarigarRawBatch;
use App\Models\Movement\Movement;
use App\Models\Movement\RateLog;
use App\Models\Notification\PendingNotification;
use App\Models\Orders\Order;
use App\Models\Pricing\DiscountRule;
use App\Models\Pricing\GstRate;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseItem;
use App\Models\Purchase\Vendor;
use App\Models\Sales\Sale;
use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Realistic demo/test data across every module so pages aren't empty during
// manual testing. Safe to re-run: everything is created fresh under
// firstOrCreate/unique keys where it matters, and this seeder is additive
// (never call this against a production database with real records).
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $employees = $this->seedEmployeesAndUsers();
        $owner = User::where('email', 'echocrew@owner.com')->first();
        $manager = User::where('email', 'manager@radharanierp.com')->first();

        $this->seedRates($owner);
        [$boxes, $packets] = $this->seedBoxesAndPackets();
        $vendors = $this->seedVendors();
        $customers = $this->seedCustomers();
        $items = $this->seedItems($packets);
        $this->seedMovements($items, $vendors, $owner);
        $this->seedPurchases($vendors, $items, $owner);
        $this->seedSales($customers, $items, $owner);
        $this->seedLoyaltyAndInstallments($customers, $owner);
        $this->seedDiscountsAndGst($owner);
        $this->seedAccounts($owner);
        $this->seedKarigarRawBatches($vendors, $owner);
        $this->seedCustomerMaterialJobs($customers, $vendors, $owner);
        $this->seedExchangeAndRefinery($customers, $owner);
        $this->seedOrders($customers, $items, $owner);
        $this->seedNotifications($customers);
        $this->call(StorefrontDemoSeeder::class);

        $this->command?->info('Demo data seeded.');
    }

    private function seedEmployeesAndUsers()
    {
        $defs = [
            ['name' => 'Priya Sharma', 'designation' => 'Manager', 'email' => 'manager@radharanierp.com', 'phone' => '9830000001', 'role' => 'manager'],
            ['name' => 'Arun Ghosh', 'designation' => 'Accountant', 'email' => 'accountant@radharanierp.com', 'phone' => '9830000002', 'role' => 'accountant'],
            ['name' => 'Sunita Das', 'designation' => 'Counter Staff', 'email' => 'counter@radharanierp.com', 'phone' => '9830000003', 'role' => 'counter_staff'],
            ['name' => 'Bikash Roy', 'designation' => 'Karigar Handler', 'email' => 'karigar.handler@radharanierp.com', 'phone' => '9830000004', 'role' => 'karigar_handler'],
        ];

        foreach ($defs as $d) {
            $employee = Employee::firstOrCreate(
                ['phone' => $d['phone']],
                ['name' => $d['name'], 'designation' => $d['designation'], 'status' => 'active', 'joining_date' => now()->subYears(2)]
            );

            $user = User::firstOrCreate(
                ['email' => $d['email']],
                ['name' => $d['name'], 'phone' => $d['phone'], 'password' => bcrypt('password'), 'employee_id' => $employee->id, 'is_active' => true]
            );
            if (! $user->hasRole($d['role'])) {
                $user->assignRole($d['role']);
            }
        }

        // Give the owner a phone too, so phone login has a real demo path.
        User::where('email', 'echocrew@owner.com')->update(['phone' => '9830000000']);

        return Employee::all();
    }

    private function seedRates(?User $owner): void
    {
        if (RateLog::count() > 0) {
            return;
        }

        $base = ['gold' => 7250.00, 'silver' => 92.50, 'titanium' => 18.00, 'platinum' => 3100.00];

        for ($day = 6; $day >= 0; $day--) {
            foreach ($base as $metal => $rate) {
                $drift = $rate * (mt_rand(-150, 150) / 10000);
                RateLog::create([
                    'metal' => $metal,
                    'rate' => round($rate + $drift, 2),
                    'source' => 'manual',
                    'updated_by' => $owner?->id,
                    'created_at' => now()->subDays($day),
                ]);
            }
        }
    }

    private function seedBoxesAndPackets(): array
    {
        $boxNames = ['Vault Box A', 'Vault Box B', 'Counter Display Box'];
        $boxes = collect($boxNames)->map(fn ($label, $i) => Box::firstOrCreate(
            ['code' => 'BOX-'.str_pad($i + 1, 2, '0', STR_PAD_LEFT)],
            ['label' => $label]
        ));

        $packets = collect();
        foreach ($boxes as $bi => $box) {
            for ($p = 1; $p <= 3; $p++) {
                $packets->push(Packet::firstOrCreate(
                    ['code' => "PKT-{$box->id}-{$p}"],
                    ['label' => "Packet {$p}", 'box_id' => $box->id]
                ));
            }
        }

        return [$boxes, $packets];
    }

    private function seedVendors(): \Illuminate\Support\Collection
    {
        $defs = [
            ['name' => 'Rajesh Karigar', 'type' => 'karigar', 'phone' => '9831000001'],
            ['name' => 'Mohan Karigar', 'type' => 'karigar', 'phone' => '9831000002'],
            ['name' => 'Bengal Gold Suppliers', 'type' => 'supplier', 'phone' => '9831000003'],
            ['name' => 'City Hallmarking Centre', 'type' => 'hallmark_center', 'phone' => '9831000004'],
        ];

        return collect($defs)->map(fn ($d) => Vendor::firstOrCreate(
            ['phone' => $d['phone']],
            ['name' => $d['name'], 'type' => $d['type'], 'balance' => 0]
        ));
    }

    private function seedCustomers(): \Illuminate\Support\Collection
    {
        $names = [
            'Anjali Roy', 'Bikash Sarkar', 'Chaitali Dey', 'Dipankar Ghosh', 'Esha Paul',
            'Firoz Ali', 'Gopal Mondal', 'Hena Khatun', 'Indranil Sen', 'Jaya Chatterjee',
        ];

        $customers = collect();
        foreach ($names as $i => $name) {
            $phone = '98'.str_pad((string) (200000 + $i), 8, '0', STR_PAD_LEFT);
            $customers->push(Customer::firstOrCreate(
                ['phone' => $phone],
                [
                    'name' => $name,
                    'address' => 'Kolkata, West Bengal',
                    'password' => bcrypt('password'),
                    'balance' => 0,
                    'status' => 'past_customer',
                    'loyalty_points' => 0,
                    'referral_code' => strtoupper(substr(md5($phone), 0, 6)),
                ]
            ));
        }

        // One referral relationship for the Referral Overview screen.
        $customers[1]->update(['referred_by' => $customers[0]->id]);

        return $customers;
    }

    private function seedItems(\Illuminate\Support\Collection $packets): \Illuminate\Support\Collection
    {
        if (Item::count() > 0) {
            return Item::all();
        }

        $catalog = [
            ['category' => 'Necklace', 'metal' => 'gold', 'purity' => '22K'],
            ['category' => 'Ring', 'metal' => 'gold', 'purity' => '18K'],
            ['category' => 'Bangle', 'metal' => 'gold', 'purity' => '22K'],
            ['category' => 'Chudi', 'metal' => 'gold', 'purity' => '22K'],
            ['category' => 'Chain', 'metal' => 'gold', 'purity' => '22K'],
            ['category' => 'Earrings', 'metal' => 'gold', 'purity' => '18K'],
            ['category' => 'Chain', 'metal' => 'silver', 'purity' => '92.5'],
            ['category' => 'Bangle', 'metal' => 'silver', 'purity' => '92.5'],
            ['category' => 'Anklet', 'metal' => 'silver', 'purity' => '92.5'],
            ['category' => 'Ring', 'metal' => 'titanium', 'purity' => 'Grade 5'],
            ['category' => 'Pendant', 'metal' => 'platinum', 'purity' => '950'],
        ];

        $statuses = ['in_stock', 'in_stock', 'in_stock', 'in_stock', 'dispatched', 'pending_review', 'reserved'];
        $makingTypes = ['percentage', 'flat_per_piece', 'flat_per_gram'];

        $items = collect();
        for ($i = 0; $i < 40; $i++) {
            $spec = $catalog[$i % count($catalog)];
            $weight = round(mt_rand(300, 4500) / 100, 3);

            $items->push(Item::create([
                'packet_id' => $packets[$i % $packets->count()]->id,
                'metal' => $spec['metal'],
                'huid_code' => $spec['metal'] === 'gold' && $weight > 2 && $i % 3 === 0 ? 'HUID'.str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT) : null,
                'internal_code' => null,
                'category' => $spec['category'],
                'purity' => $spec['purity'],
                'weight' => $weight,
                'description' => "{$spec['category']} — demo stock item",
                'making_type' => $makingTypes[$i % 3],
                'making_value' => $makingTypes[$i % 3] === 'percentage' ? mt_rand(8, 18) : mt_rand(200, 900),
                'status' => $statuses[$i % count($statuses)],
            ]));
        }

        // Fill internal_code for anything without a HUID, via the real generator.
        Item::whereNull('huid_code')->whereNull('internal_code')->get()->each(function (Item $item) {
            $item->update(['internal_code' => Item::generateInternalCode()]);
        });

        return $items;
    }

    private function seedMovements(\Illuminate\Support\Collection $items, \Illuminate\Support\Collection $vendors, ?User $owner): void
    {
        if (Movement::count() > 0 || ! $owner) {
            return;
        }

        $karigar = $vendors->firstWhere('type', 'karigar');
        $hallmark = $vendors->firstWhere('type', 'hallmark_center');

        $dispatched = $items->where('status', 'dispatched')->first();
        if ($dispatched) {
            Movement::create([
                'trackable_type' => 'item', 'trackable_id' => $dispatched->id,
                'movement_type' => 'karigar_out', 'purpose_label' => 'Repair',
                'user_id' => $owner->id, 'counterparty' => $karigar?->name,
                'expected_return' => now()->addDays(5), 'weight_at_dispatch' => $dispatched->weight,
                'note' => 'Demo dispatch for repair.',
            ]);
        }

        $pending = $items->where('status', 'pending_review')->first();
        if ($pending) {
            Movement::create([
                'trackable_type' => 'item', 'trackable_id' => $pending->id,
                'movement_type' => 'hallmark_out', 'user_id' => $owner->id,
                'counterparty' => $hallmark?->name, 'weight_at_dispatch' => $pending->weight,
                'created_at' => now()->subDays(3),
            ]);
            Movement::create([
                'trackable_type' => 'item', 'trackable_id' => $pending->id,
                'movement_type' => 'hallmark_in', 'user_id' => $owner->id,
                'weight_at_return' => $pending->weight - 0.05, 'weight_loss' => 0.05,
                'tagged_by' => 'City Hallmarking Centre staff', 'actual_return' => now()->subDay(),
            ]);
        }

        // A vault<->counter pair on a couple of in-stock items for history.
        foreach ($items->where('status', 'in_stock')->take(4) as $item) {
            Movement::create([
                'trackable_type' => 'item', 'trackable_id' => $item->id,
                'movement_type' => 'vault_out', 'user_id' => $owner->id,
                'weight_at_dispatch' => $item->weight, 'created_at' => now()->subDays(10),
            ]);
            Movement::create([
                'trackable_type' => 'item', 'trackable_id' => $item->id,
                'movement_type' => 'vault_in', 'user_id' => $owner->id,
                'created_at' => now()->subDays(9),
            ]);
        }
    }

    private function seedPurchases(\Illuminate\Support\Collection $vendors, \Illuminate\Support\Collection $items, ?User $owner): void
    {
        if (Purchase::count() > 0 || ! $owner) {
            return;
        }

        $supplier = $vendors->firstWhere('type', 'supplier');
        $karigar = $vendors->firstWhere('type', 'karigar');

        $finished = Purchase::create([
            'vendor_id' => $supplier->id, 'type' => 'finished_product',
            'invoice_number' => 'PINV-1001', 'total_amount' => 185000, 'gst' => 5550,
            'payment_status' => 'paid', 'created_by' => $owner->id,
        ]);
        foreach ($items->take(2) as $item) {
            PurchaseItem::create([
                'purchase_id' => $finished->id, 'item_id' => $item->id,
                'rate' => 7200, 'weight' => $item->weight, 'tag_pending' => false,
            ]);
        }

        $raw = Purchase::create([
            'vendor_id' => $karigar->id, 'type' => 'raw_material',
            'total_weight' => 50.000, 'total_amount' => 362500,
            'payment_status' => 'partial', 'created_by' => $owner->id,
        ]);
        PurchaseItem::create([
            'purchase_id' => $raw->id, 'item_id' => null,
            'description' => 'Raw gold, 22K, awaiting tagging',
            'category' => 'Raw Material', 'metal' => 'gold', 'purity' => '22K',
            'rate' => 7250, 'weight' => 50.000, 'tag_pending' => true,
        ]);
    }

    private function seedSales(\Illuminate\Support\Collection $customers, \Illuminate\Support\Collection $items, ?User $owner): void
    {
        if (Sale::count() > 0 || ! $owner) {
            return;
        }

        $verified = Sale::create([
            'customer_id' => $customers[0]->id, 'invoice_number' => 'INV-2026-0001',
            'type' => 'sale', 'cgst' => 900, 'sgst' => 900, 'total' => 38800,
            'confirmed_by_accountant' => true, 'created_by' => $owner->id,
        ]);
        $soldItem = $items->where('status', 'in_stock')->skip(4)->first();
        if ($soldItem) {
            DB::table('sale_items')->insert(['sale_id' => $verified->id, 'item_id' => $soldItem->id, 'price_at_sale' => 37000]);
            $soldItem->update(['status' => 'sold']);
        }

        $pendingSale = Sale::create([
            'customer_id' => $customers[1]->id, 'invoice_number' => 'RESV-'.now()->format('YmdHis'),
            'type' => 'sale', 'total' => 24500,
            'confirmed_by_accountant' => false, 'created_by' => $owner->id,
        ]);
        $reservedItem = $items->where('status', 'reserved')->first();
        if ($reservedItem) {
            DB::table('sale_items')->insert(['sale_id' => $pendingSale->id, 'item_id' => $reservedItem->id, 'price_at_sale' => 24500]);
        }
    }

    private function seedLoyaltyAndInstallments(\Illuminate\Support\Collection $customers, ?User $owner): void
    {
        if (LoyaltyTransaction::count() === 0) {
            LoyaltyTransaction::create(['customer_id' => $customers[0]->id, 'points' => 388, 'reason' => 'purchase']);
            LoyaltyTransaction::create(['customer_id' => $customers[1]->id, 'points' => 100, 'reason' => 'referral']);
            $customers[0]->update(['loyalty_points' => 388]);
            $customers[1]->update(['loyalty_points' => 100]);
        }

        if (InstallmentScheme::count() === 0) {
            $scheme = InstallmentScheme::create([
                'customer_id' => $customers[2]->id, 'monthly_amount' => 5000,
                'months_paid' => 2, 'start_date' => now()->subMonths(2), 'status' => 'active',
            ]);
            InstallmentPayment::create(['scheme_id' => $scheme->id, 'amount' => 5000, 'paid_on' => now()->subMonths(2)]);
            InstallmentPayment::create(['scheme_id' => $scheme->id, 'amount' => 5000, 'paid_on' => now()->subMonth()]);
        }
    }

    private function seedDiscountsAndGst(?User $owner): void
    {
        if (DiscountRule::count() === 0 && $owner) {
            DiscountRule::create([
                'scope' => 'category', 'category' => 'Chain', 'discount_type' => 'percentage',
                'value' => 5, 'active' => true, 'created_by' => $owner->id,
            ]);
        }

        if (GstRate::count() === 0) {
            foreach (['Necklace' => 3, 'Ring' => 3, 'Bangle' => 3, 'Chudi' => 3, 'Chain' => 3] as $cat => $rate) {
                GstRate::create(['category' => $cat, 'rate_percent' => $rate]);
            }
        }
    }

    private function seedAccounts(?User $owner): void
    {
        // firstOrCreate per account (not a single Account::count() guard) so
        // this stays correct even if only some of the standard accounts
        // exist already — e.g. a custom account created by hand beforehand.
        $cash = Account::firstOrCreate(['name' => 'Cash'], ['type' => 'asset']);
        $bank = Account::firstOrCreate(['name' => 'Bank'], ['type' => 'asset']);
        $salesIncome = Account::firstOrCreate(['name' => 'Sales Income'], ['type' => 'income']);
        $purchases = Account::firstOrCreate(['name' => 'Purchases'], ['type' => 'expense']);
        $payable = Account::firstOrCreate(['name' => 'Accounts Payable'], ['type' => 'liability']);

        // Auto-posting from Sale/Purchase isn't wired yet (still open, see
        // CLAUDE.md) — these are a handful of sample rows so Ledger View
        // isn't permanently empty during review, not a real posting log.
        if (! $owner || Transaction::count() > 0) {
            return;
        }

        foreach ([
            ['account_id' => $bank->id, 'reference_type' => 'sale', 'debit' => 48500, 'credit' => 0, 'note' => 'Sample sale receipt'],
            ['account_id' => $salesIncome->id, 'reference_type' => 'sale', 'debit' => 0, 'credit' => 48500, 'note' => 'Sample sale income'],
            ['account_id' => $purchases->id, 'reference_type' => 'purchase', 'debit' => 32000, 'credit' => 0, 'note' => 'Sample purchase'],
            ['account_id' => $payable->id, 'reference_type' => 'purchase', 'debit' => 0, 'credit' => 32000, 'note' => 'Sample vendor payable'],
            ['account_id' => $cash->id, 'reference_type' => 'manual', 'debit' => 5000, 'credit' => 0, 'note' => 'Petty cash top-up'],
        ] as $t) {
            Transaction::create($t + ['created_by' => $owner->id]);
        }
    }

    private function seedKarigarRawBatches(\Illuminate\Support\Collection $vendors, ?User $owner): void
    {
        if (KarigarRawBatch::count() > 0 || ! $owner) {
            return;
        }

        KarigarRawBatch::create([
            'vendor_id' => $vendors->firstWhere('type', 'karigar')->id,
            'weight_out' => 20.000, 'metal' => 'gold', 'purity' => '22K',
            'purpose_label' => 'New bangles batch', 'expected_return' => now()->addDays(7),
            'status' => 'dispatched', 'user_id' => $owner->id,
        ]);
    }

    private function seedCustomerMaterialJobs(\Illuminate\Support\Collection $customers, \Illuminate\Support\Collection $vendors, ?User $owner): void
    {
        if (CustomerMaterialJob::count() > 0 || ! $owner) {
            return;
        }

        CustomerMaterialJob::create([
            'customer_id' => $customers[3]->id, 'vendor_id' => $vendors->firstWhere('type', 'karigar')->id,
            'description' => "Customer's own gold ring — resize", 'weight_out' => 6.500, 'metal' => 'gold',
            'expected_return' => now()->addDays(4), 'status' => 'out', 'user_id' => $owner->id,
        ]);
    }

    private function seedExchangeAndRefinery(\Illuminate\Support\Collection $customers, ?User $owner): void
    {
        if (! $owner) {
            return;
        }

        if (ExchangeTransaction::count() === 0) {
            ExchangeTransaction::create([
                'customer_id' => $customers[4]->id, 'gross_weight' => 18.400,
                'description' => 'Old gold necklace', 'net_weight' => 17.900,
                'purity_test_1' => 91.2, 'purity_test_2' => 91.6, 'purity_averaged' => 91.4,
                'preset_deduction_percent' => 2.0, 'deductable_weight' => 17.542,
                'stage' => 'tested', 'created_by' => $owner->id,
            ]);
            ExchangeTransaction::create([
                'customer_id' => $customers[5]->id, 'gross_weight' => 9.120,
                'net_weight' => 8.850, 'purity_test_1' => 76.0, 'purity_test_2' => 76.4,
                'purity_averaged' => 76.2, 'deductable_weight' => 8.673, 'final_value' => 62500,
                'stage' => 'settled', 'settled_by' => $owner->id, 'settled_at' => now()->subDays(3),
                'created_by' => $owner->id,
            ]);
        }

        if (RefineryBatch::count() === 0) {
            RefineryBatch::create([
                'weight' => 15.000, 'status' => 'sent', 'sent_at' => now()->subDays(2),
                'created_by' => $owner->id,
            ]);
        }
    }

    private function seedOrders(\Illuminate\Support\Collection $customers, \Illuminate\Support\Collection $items, ?User $owner): void
    {
        if (Order::count() > 0 || ! $owner) {
            return;
        }

        Order::create([
            'customer_id' => $customers[6]->id, 'product_description' => 'Custom gold necklace, 24 inch',
            'category' => 'Necklace', 'metal' => 'gold', 'estimated_weight' => 22.000,
            'estimated_value' => 165000, 'advance_amount' => 165000, 'full_payment_now' => true,
            'rate_locked' => true, 'locked_rate' => RateLog::latestFor('gold')?->rate, 'locked_at' => now()->subDays(1),
            'out_of_stock' => true, 'status' => 'confirmed', 'expected_ready_date' => now()->addDays(10),
            'created_by' => $owner->id,
        ]);

        Order::create([
            'customer_id' => $customers[7]->id, 'product_description' => 'Silver anklet pair',
            'category' => 'Anklet', 'metal' => 'silver', 'estimated_value' => 8500,
            'advance_amount' => 2000, 'status' => 'ready', 'expected_ready_date' => now()->subDay(),
            'created_by' => $owner->id,
        ]);
    }

    private function seedNotifications(\Illuminate\Support\Collection $customers): void
    {
        if (PendingNotification::count() > 0) {
            return;
        }

        PendingNotification::create([
            'customer_id' => $customers[7]->id, 'type' => 'order_ready',
            'recipient_name' => $customers[7]->name, 'recipient_phone' => $customers[7]->phone,
            'message' => "Hi {$customers[7]->name}, your silver anklet pair order is ready for pickup!",
            'status' => 'pending',
        ]);
        PendingNotification::create([
            'customer_id' => $customers[2]->id, 'type' => 'installment_reminder',
            'recipient_name' => $customers[2]->name, 'recipient_phone' => $customers[2]->phone,
            'message' => "Hi {$customers[2]->name}, your monthly instalment of ₹5,000 is due soon.",
            'status' => 'sent', 'sent_at' => now()->subDays(1),
        ]);
    }
}
