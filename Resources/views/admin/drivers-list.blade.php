<div class="givecore-drivers-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
    @foreach($driverItems as $item)
        <div class="givecore-driver-card" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; border: 1px solid var(--border-color, #e2e8f0); border-radius: 0.5rem; {{ !$item['available'] ? 'opacity: 0.5;' : '' }}">
            <div class="givecore-driver-icon" style="font-size: 1.5rem; flex-shrink: 0;">
                <x-icon path="{{ $item['icon'] }}" />
            </div>
            <div class="givecore-driver-info" style="flex: 1; min-width: 0;">
                <h4 style="margin: 0 0 0.25rem 0; font-size: 0.95rem;">{{ $item['name'] }}</h4>
                <p style="margin: 0; font-size: 0.825rem; opacity: 0.7;">{{ $item['description'] }}</p>
                @if(!$item['available'] && ($item['reason'] ?? null))
                    <small style="color: var(--danger-color, #e53e3e);">{{ $item['reason'] }}</small>
                @endif
            </div>
            <div class="givecore-driver-status" style="flex-shrink: 0;">
                @if($item['available'])
                    <span class="badge success">{{ __('rolesync.driver_available') }}</span>
                @else
                    <span class="badge">{{ __('rolesync.driver_unavailable') }}</span>
                @endif
            </div>
        </div>
    @endforeach
</div>
