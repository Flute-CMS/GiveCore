@props(['settings'])

<div class="row g-3">
    <div class="col-md-12">
        <x-admin::forms.field name="sid" label="{{ __('givecore.settings.sid') }}" required>
            <x-admin::fields.input name="sid" id="sid"
                value="{{ request()->input('sid', $settings['sid'] ?? '') }}"
                placeholder="{{ __('givecore.settings.sid_placeholder') }}" required />
        </x-admin::forms.field>
    </div>
</div> 