<div>
<section class="pt-auth wrap">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a><i class="ph ph-caret-right"></i>
        <a href="{{ route('portal.dashboard') }}">My account</a><i class="ph ph-caret-right"></i>
        <span aria-current="page">Change password</span>
    </nav>

    <div class="pt-auth__grid pt-auth__grid--single">
        <div class="pt-card pt-auth__card">
            <span class="eyebrow">Account security</span>
            <h1 class="h-lg">Change <em>password.</em></h1>
            <p class="pt-auth__lede">Enter your current password, then choose a new one of at least 8 characters.</p>

            @if ($saved)
                <div class="pt-notice pt-notice--ok" role="status">
                    <i class="ph ph-check-circle"></i>
                    <span>Password updated. Use the new one next time you sign in.</span>
                </div>
            @endif

            <form wire:submit="update" class="pt-form" novalidate>
                @foreach ([
                    ['current_password', 'Current password', 'current-password', null],
                    ['password', 'New password', 'new-password', 'At least 8 characters.'],
                    ['password_confirmation', 'Type the new password again', 'new-password', null],
                ] as [$field, $label, $autocomplete, $hint])
                    <div class="pt-field" x-data="{ show: false }">
                        <label for="pw-{{ $field }}">{{ $label }}</label>
                        <div class="pt-input pt-input--suffix @error($field) is-invalid @enderror">
                            <input id="pw-{{ $field }}" :type="show ? 'text' : 'password'" type="password" wire:model="{{ $field }}" autocomplete="{{ $autocomplete }}" @if ($loop->first) autofocus @endif>
                            <button type="button" class="pt-input__eye" x-on:click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password">
                                <i class="ph" :class="show ? 'ph-eye-slash' : 'ph-eye'"></i>
                            </button>
                        </div>
                        @error($field)
                            <p class="pt-error"><i class="ph ph-warning-circle"></i>{{ $message }}</p>
                        @else
                            @if ($hint)<p class="pt-hint">{{ $hint }}</p>@endif
                        @enderror
                    </div>
                @endforeach

                <div class="pt-form__actions">
                    <a href="{{ route('portal.dashboard') }}" class="btn btn--ghost">Back to my account</a>
                    <button type="submit" class="btn btn--solid" wire:loading.attr="disabled" wire:target="update">
                        <span wire:loading.remove wire:target="update">Update password</span>
                        <span wire:loading wire:target="update">Saving…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
</div>
