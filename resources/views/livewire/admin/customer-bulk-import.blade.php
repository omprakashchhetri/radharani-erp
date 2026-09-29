<div>
    <x-ui.page-header title="Bulk Import Customers" subtitle="Upload a CSV or XLSX with columns: name, phone, address, email, gstin. Only name and phone are required. Duplicate phone numbers are skipped, not overwritten."
        :crumbs="[['label' => 'Customers', 'href' => route('admin.customers')], ['label' => 'Bulk Import']]" />

    <x-ui.card class="max-w-[560px] mb-6" title="Upload file" icon="upload">
        <form wire:submit="import">
            <div class="mb-4">
                <label class="rj-label">File</label>
                <input type="file" wire:model="file" accept=".csv,.txt,.xlsx" class="rj-input w-full">
                @error('file') <p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p> @enderror
            </div>
            <x-ui.button type="submit" target="import" variant="primary" icon="upload">Import</x-ui.button>
        </form>
    </x-ui.card>

    @if ($done)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 max-w-[900px]">
            <x-ui.card :padding="false" title="Imported" :subtitle="count($imported).' customer(s)'" icon="check-circle">
                <div class="max-h-[320px] overflow-y-auto">
                    @forelse ($imported as $row)
                        <div class="px-5 py-2 text-[12.5px] text-ink_text-primary border-b border-line-light">{{ $row }}</div>
                    @empty
                        <div class="px-5 py-3 text-[12.5px] text-ink_text-secondary">None.</div>
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card :padding="false" title="Skipped" :subtitle="count($skipped).' row(s)'" icon="alert-triangle">
                <div class="max-h-[320px] overflow-y-auto">
                    @forelse ($skipped as $row)
                        <div class="px-5 py-2 text-[12.5px] text-ink_text-primary border-b border-line-light">{{ $row }}</div>
                    @empty
                        <div class="px-5 py-3 text-[12.5px] text-ink_text-secondary">None.</div>
                    @endforelse
                </div>
            </x-ui.card>
        </div>
    @endif
</div>
