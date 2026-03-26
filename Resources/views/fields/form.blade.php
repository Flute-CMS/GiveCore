@props(['fields' => [], 'values' => [], 'prefix' => ''])

@foreach($fields as $fieldName => $field)
    @php
        $inputName = $prefix ? "{$prefix}[{$fieldName}]" : $fieldName;
        $dotName = $prefix ? str_replace(['[', ']'], ['.', ''], $prefix) . ".{$fieldName}" : $fieldName;
        $currentValue = $values[$fieldName] ?? ($field['default'] ?? '');
        $isRequired = $field['required'] ?? false;
    @endphp

    <x-admin::forms.field
        name="{{ $dotName }}"
        label="{{ $field['label'] ?? $fieldName }}"
        :required="$isRequired"
        :small="$field['help'] ?? null"
    >
        @if(($field['type'] ?? 'text') === 'select')
            <x-admin::fields.select name="{{ $dotName }}">
                @if(!$isRequired)
                    <option value="">—</option>
                @endif
                @foreach($field['options'] ?? [] as $optValue => $optLabel)
                    <option value="{{ $optValue }}" @selected((string) $currentValue === (string) $optValue)>{{ $optLabel }}</option>
                @endforeach
            </x-admin::fields.select>

        @elseif(($field['type'] ?? 'text') === 'number')
            <x-admin::fields.input
                type="number"
                name="{{ $dotName }}"
                value="{{ $currentValue }}"
                :min="$field['min'] ?? null"
                :max="$field['max'] ?? null"
                placeholder="{{ $field['placeholder'] ?? '' }}"
            />

        @elseif(($field['type'] ?? 'text') === 'textarea')
            <x-admin::fields.textarea
                name="{{ $dotName }}"
                placeholder="{{ $field['placeholder'] ?? '' }}"
            >{{ $currentValue }}</x-admin::fields.textarea>

        @elseif(($field['type'] ?? 'text') === 'toggle')
            <x-admin::fields.toggle
                name="{{ $dotName }}"
                :checked="(bool) $currentValue"
            />

        @else
            <x-admin::fields.input
                type="text"
                name="{{ $dotName }}"
                value="{{ $currentValue }}"
                placeholder="{{ $field['placeholder'] ?? '' }}"
            />
        @endif
    </x-admin::forms.field>
@endforeach
