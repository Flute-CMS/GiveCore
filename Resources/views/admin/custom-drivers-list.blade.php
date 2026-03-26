<div style="display: flex; flex-direction: column; gap: 8px;" hx-boost="true" yoyo:ignore hx-target="#main" hx-swap="outerHTML transition:true">
    @foreach ($drivers as $driver)
        <div class="card" style="margin: 0;">
            <div class="card-body" style="display: flex; align-items: center; gap: 16px; padding: 16px 20px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--transp-05); border: 1px solid var(--transp-1); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <x-icon path="{{ $driver->icon }}" style="font-size: 18px; color: var(--text-400);" />
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
                        <strong style="font-size: 14px;">{{ $driver->name }}</strong>
                        <span class="badge primary" style="font-size: 10px;">{{ $driver->category }}</span>
                        @if ($driver->mod_key)
                            <span class="badge info" style="font-size: 10px;">{{ $driver->mod_key }}</span>
                        @endif
                    </div>
                    <div style="font-size: 12px; color: var(--text-500);">
                        <code style="font-size: 11px; color: var(--text-400);">{{ $driver->alias }}</code>
                        @if ($driver->description)
                            &middot; {{ $driver->description }}
                        @endif
                    </div>
                </div>
                <a href="{{ url('/admin/givecore/custom-drivers/' . $driver->alias . '/edit') }}" class="btn btn-outline-primary btn-small">
                    <x-icon path="ph.bold.pencil-bold" /> {{ __('givecore.buttons.edit') }}
                </a>
            </div>
        </div>
    @endforeach
</div>
