<div style="text-align: center; padding: 48px 20px;">
    <div style="margin-bottom: 16px; opacity: 0.2;">
        <x-icon path="ph.regular.database" style="font-size: 48px; color: var(--text-500);" />
    </div>
    <h4 style="color: var(--text); margin-bottom: 8px;">{{ __('givecore.custom_drivers.empty') }}</h4>
    <p style="color: var(--text-500); font-size: 14px; margin-bottom: 20px; text-align: center;">{{ __('givecore.custom_drivers.empty_hint') }}</p>
    <a href="{{ url('/admin/givecore/custom-drivers/add') }}" class="btn btn-primary btn-small">
        <x-icon path="ph.bold.plus-bold" /> {{ __('givecore.custom_drivers.add') }}
    </a>
</div>
