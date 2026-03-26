@php
    $fieldsConfig = json_encode([
        'existing' => $existingFields,
        'i18n' => [
            'name' => __('givecore.custom_drivers.field_name'),
            'label' => __('givecore.custom_drivers.field_label_title'),
            'type' => __('givecore.custom_drivers.field_type'),
            'required' => __('givecore.custom_drivers.field_required'),
        ],
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
@endphp

<div class="card mb-3 mt-3">
    <div class="card-header">
        <span class="card-title">{{ __('givecore.custom_drivers.sql_deliver') }}</span>
    </div>
    <div class="card-body">
        <div class="input-wrapper mb-3">
            <div class="input__field-container" style="min-height: 100px; align-items: flex-start;">
                <textarea name="sql_deliver" class="input__field" rows="5"
                          style="font-family: var(--font-mono); font-size: var(--p-small); resize: vertical;"
                          required>{{ $driver?->sql_deliver ?? '' }}</textarea>
            </div>
        </div>

        <div class="cdf-hints">
            <div class="cdf-hints__title">{{ __('givecore.custom_drivers.placeholders') }}</div>
            <div class="cdf-hints__tags">
                @foreach (['{steam_id}', '{steam32}', '{steam64}', '{account_id}', '{uuid}', '{name}', '{email}', '{time}', '{unix_expire}', '{unix_now}', '{prefix}'] as $ph)
                    <code class="cdf-tag">{{ $ph }}</code>
                @endforeach
                <code class="cdf-tag cdf-tag--warn">@{{ '{field:name}' }}</code>
            </div>
            <div class="cdf-hints__title mt-3">{{ __('givecore.custom_drivers.examples') }}</div>
            <div class="cdf-examples">
                <div class="cdf-example">
                    <span class="badge info">LuckPerms</span>
                    <pre class="cdf-example__code">INSERT INTO {prefix}user_permissions (uuid, permission, value, server, world, expiry, contexts) VALUES ('{uuid}', 'group.{field:group}', 1, 'global', 'global', {unix_expire}, '{}')</pre>
                </div>
                <div class="cdf-example">
                    <span class="badge info">AMX Mod X</span>
                    <pre class="cdf-example__code">INSERT INTO {prefix}amxadmins (username, access, flags, steamid, nickname, created, expired) VALUES ('{name}', '{field:access}', 'ce', '{steam32}', '{name}', {unix_now}, {unix_expire})</pre>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <div>
            <span class="card-title">{{ __('givecore.custom_drivers.sql_check') }}</span>
            <small class="d-block text-muted mt-1">{{ __('givecore.custom_drivers.sql_check_help') }}</small>
        </div>
    </div>
    <div class="card-body">
        <div class="input-wrapper">
            <div class="input__field-container" style="min-height: 60px; align-items: flex-start;">
                <textarea name="sql_check" class="input__field" rows="3"
                          style="font-family: var(--font-mono); font-size: var(--p-small); resize: vertical;"
                          placeholder="SELECT 1 FROM {prefix}users WHERE steam_id = '{steam32}' AND active = 1">{{ $driver?->sql_check ?? '' }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">{{ __('givecore.custom_drivers.fields_title') }}</span>
            <small class="d-block text-muted mt-1">{{ __('givecore.custom_drivers.fields_subtitle') }}</small>
        </div>
    </div>
    <div class="card-body" id="cdf-fields-editor" data-config="{{ $fieldsConfig }}">
        @foreach ($existingFields as $i => $field)
            <div class="cdf-row" data-index="{{ $i }}">
                <div class="cdf-col cdf-col--name">
                    <label class="form__label">{{ __('givecore.custom_drivers.field_name') }}</label>
                    <div class="input-wrapper">
                        <div class="input__field-container">
                            <input type="text" name="field_name[{{ $i }}]" class="input__field"
                                   value="{{ $field['name'] ?? '' }}" placeholder="group">
                        </div>
                    </div>
                </div>
                <div class="cdf-col cdf-col--label">
                    <label class="form__label">{{ __('givecore.custom_drivers.field_label_title') }}</label>
                    <div class="input-wrapper">
                        <div class="input__field-container">
                            <input type="text" name="field_label[{{ $i }}]" class="input__field"
                                   value="{{ $field['label'] ?? '' }}" placeholder="{{ __('givecore.custom_drivers.field_label_title') }}">
                        </div>
                    </div>
                </div>
                <div class="cdf-col cdf-col--type">
                    <label class="form__label">{{ __('givecore.custom_drivers.field_type') }}</label>
                    <div class="select-wrapper">
                        <div class="select__field-container">
                            <select name="field_type[{{ $i }}]" class="select__field" data-select
                                    data-initial-value="{{ json_encode($field['type'] ?? 'text') }}">
                                <option value="text" @selected(($field['type'] ?? 'text') === 'text')>text</option>
                                <option value="number" @selected(($field['type'] ?? '') === 'number')>number</option>
                                <option value="textarea" @selected(($field['type'] ?? '') === 'textarea')>textarea</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="cdf-col cdf-col--req">
                    <label class="form__label">{{ __('givecore.custom_drivers.field_required') }}</label>
                    <input type="hidden" name="field_required[{{ $i }}]" value="0">
                    <input type="checkbox" name="field_required[{{ $i }}]" value="1"
                           class="checkbox__field" id="cdf-req-{{ $i }}" @checked($field['required'] ?? false)>
                    <label for="cdf-req-{{ $i }}"></label>
                </div>
                <div class="cdf-col cdf-col--act">
                    <button type="button" class="btn btn-outline-error btn-tiny cdf-remove">
                        <x-icon path="ph.bold.x-bold" />
                    </button>
                </div>
            </div>
        @endforeach

        <div id="cdf-fields-list"></div>

        <button type="button" class="btn btn-outline-primary btn-small mt-2" id="cdf-add-field">
            <x-icon path="ph.bold.plus-bold" />
            {{ __('givecore.custom_drivers.add_field') }}
        </button>
    </div>
</div>
