<?php
namespace App\Livewire\Website;

use App\Models\Storefront\StorefrontSetting;
use Livewire\Component;

/** Website > Settings: the shop details the public site shows everywhere. */
class SiteSettings extends Component
{
    public const FIELDS = ['whatsapp', 'phone', 'address', 'hours', 'parking', 'maps_query', 'instagram', 'facebook', 'youtube'];

    public string $whatsapp = '';
    public string $phone = '';
    public string $address = '';
    public string $hours = '';
    public string $parking = '';
    public string $maps_query = '';
    public string $instagram = '';
    public string $facebook = '';
    public string $youtube = '';

    protected function rules(): array
    {
        return [
            'whatsapp' => ['required', 'regex:/^\+?[\d\s-]{10,16}$/'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:160'],
            'hours' => ['required', 'string', 'max:80'],
            'parking' => ['nullable', 'string', 'max:80'],
            'maps_query' => ['required', 'string', 'max:160'],
            'instagram' => ['nullable', 'url', 'max:200'],
            'facebook' => ['nullable', 'url', 'max:200'],
            'youtube' => ['nullable', 'url', 'max:200'],
        ];
    }

    protected $messages = [
        'whatsapp.regex' => 'Enter the WhatsApp number with country code, e.g. 91 98765 43210.',
    ];

    protected $validationAttributes = ['maps_query' => 'map search'];

    // The contact defaults are placeholders ("91XXXXXXXXXX", "[City]"): start
    // those fields empty rather than making staff delete the placeholder.
    private const PLACEHOLDER_FIELDS = ['whatsapp', 'phone', 'address'];

    public function mount(): void
    {
        $values = StorefrontSetting::values();
        foreach (self::FIELDS as $field) {
            $value = (string) ($values[$field] ?? '');
            $isPlaceholder = in_array($field, self::PLACEHOLDER_FIELDS, true) && $value === config("storefront.defaults.{$field}");
            $this->{$field} = $isPlaceholder ? '' : $value;
        }
    }

    public function save(): void
    {
        $this->validate();

        // WhatsApp links need country code + number only; a bare 10-digit
        // Indian mobile gets 91 in front.
        $digits = preg_replace('/\D/', '', $this->whatsapp);
        $this->whatsapp = strlen($digits) === 10 ? '91'.$digits : $digits;

        StorefrontSetting::put(collect(self::FIELDS)->mapWithKeys(fn ($f) => [$f => trim($this->{$f})])->all(), auth()->id());

        $this->dispatch('toast', message: 'Website details saved. They show on the site straight away.', type: 'success');
    }

    public function render()
    {
        $saved = StorefrontSetting::values();
        $placeholders = collect(self::PLACEHOLDER_FIELDS)->contains(fn ($f) => ($saved[$f] ?? null) === config("storefront.defaults.{$f}"));

        return view('livewire.website.site-settings', ['placeholders' => $placeholders])
            ->layout('components.layouts.app', ['title' => 'Website settings — Radharani Jewellery']);
    }
}
