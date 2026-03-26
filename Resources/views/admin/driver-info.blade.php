<div class="alert alert-info border" style="margin-bottom: var(--space-md); font-size: var(--p-small);">
    <x-icon path="{{ $driver->icon() }}" />
    <div>
        <strong>{{ $driver->name() }}</strong>
        @if ($driver->category() && $driver->category() !== 'other')
            <span class="badge primary" style="font-size: 10px; margin-left: 4px;">{{ $driver->category() }}</span>
        @endif
        <div style="margin-top: 2px; opacity: 0.8;">{{ $driver->description() }}</div>
        @if ($driver->dbConnectionKey())
            <div style="margin-top: 4px; font-size: var(--small); opacity: 0.7;">
                {{ __('givecore.admin.requires_connection') }}: <code>{{ $driver->dbConnectionKey() }}</code>
            </div>
        @endif
    </div>
</div>
