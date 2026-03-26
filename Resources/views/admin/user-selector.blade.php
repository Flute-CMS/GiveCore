@php
    $mode = $userMode ?? 'cms';
@endphp

<div class="give-user-selector">
    <label class="form-label">{{ __('givecore.admin.user_mode') }}</label>
    <div class="btn-group mb-3" style="display: flex; gap: 0;">
        <button type="button"
            class="btn {{ $mode === 'cms' ? 'btn-primary' : 'btn-outline-primary' }} btn-sm give-mode-btn"
            data-mode="cms">
            <x-icon path="ph.bold.user-bold" />
            {{ __('givecore.admin.mode_cms') }}
        </button>
        <button type="button"
            class="btn {{ $mode === 'steam' ? 'btn-primary' : 'btn-outline-primary' }} btn-sm give-mode-btn"
            data-mode="steam">
            <x-icon path="ph.bold.game-controller-bold" />
            {{ __('givecore.admin.mode_steam') }}
        </button>
    </div>
    <input type="hidden" name="userMode" id="give-user-mode" value="{{ $mode }}">

    <div class="give-mode-cms" style="{{ $mode !== 'cms' ? 'display:none' : '' }}">
        <label class="form-label">{{ __('givecore.admin.select_user') }}</label>
        <div class="select-wrapper">
            <select id="give-user-select" name="give[user_id]" class="select__field"
                data-select
                data-mode="async"
                data-max-items="1"
                data-plugins='["clear_button"]'
                data-searchable="true"
                data-search-url="/admin/select/search"
                data-search-min-length="2"
                data-search-delay="300"
                data-search-fields='["name","email","login"]'
                data-entity="users"
                data-display-field="name"
                data-value-field="id"
                data-preload="false"
                data-allow-empty="true"
                data-render-option="giveUserRenderOption"
                data-render-item="giveUserRenderItem"
                placeholder="{{ __('givecore.admin.search_user_placeholder') }}">
            </select>
        </div>
    </div>

    <div class="give-mode-steam" style="{{ $mode !== 'steam' ? 'display:none' : '' }}">
        <label class="form-label">{{ __('givecore.admin.steam_id') }}</label>
        <div class="input-wrapper">
            <div class="input__field-container">
                <input type="text" name="give[steam_id]" class="input__field"
                    placeholder="{{ __('givecore.admin.steam_id_placeholder') }}"
                    value="{{ request()->input('give.steam_id', '') }}">
            </div>
        </div>
        <small class="text-muted">{{ __('givecore.admin.steam_id_help') }}</small>
    </div>
</div>

<script>
(function() {
    document.querySelectorAll('.give-mode-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var mode = this.dataset.mode;
            document.getElementById('give-user-mode').value = mode;

            document.querySelectorAll('.give-mode-btn').forEach(function(b) {
                b.classList.remove('btn-primary');
                b.classList.add('btn-outline-primary');
            });
            this.classList.remove('btn-outline-primary');
            this.classList.add('btn-primary');

            document.querySelector('.give-mode-cms').style.display = mode === 'cms' ? '' : 'none';
            document.querySelector('.give-mode-steam').style.display = mode === 'steam' ? '' : 'none';
        });
    });

    window.giveUserRenderOption = function(data, escape) {
        return '<div class="d-flex align-items-center gap-2 py-1">'
            + '<span class="fw-semibold">' + escape(data.text) + '</span>'
            + '<span class="text-muted small">#' + escape(String(data.value)) + '</span>'
            + '</div>';
    };

    window.giveUserRenderItem = function(data, escape) {
        return '<div>' + escape(data.text) + ' <span class="text-muted">#' + escape(String(data.value)) + '</span></div>';
    };
})();
</script>
