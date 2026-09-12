@props(['phases'])

@once
    @push('styles')
        <style>
            .surfaced-fr-progress{overflow:hidden}.surfaced-fr-progress-list{display:flex;align-items:flex-start;list-style:none;margin:0;padding:0}.surfaced-fr-progress-step{position:relative;display:flex;flex:1 1 0;min-width:0;align-items:flex-start;gap:.65rem}.surfaced-fr-progress-step:not(:last-child)::after{content:"";position:absolute;z-index:0;top:18px;left:42px;right:10px;height:3px;background:#dbe3ec}.surfaced-fr-progress-step.is-completed:not(:last-child)::after{background:#28a745}.surfaced-fr-progress-marker{position:relative;z-index:1;display:flex;flex:0 0 36px;align-items:center;justify-content:center;width:36px;height:36px;border:2px solid #cbd5e0;border-radius:50%;background:#fff;color:#64748b;font-size:.78rem;font-weight:800}.surfaced-fr-progress-copy{min-width:0;padding-top:.05rem}.surfaced-fr-progress-label,.surfaced-fr-progress-status{display:block}.surfaced-fr-progress-label{color:#1e293b;font-size:.86rem;font-weight:700;line-height:1.25}.surfaced-fr-progress-status{margin-top:.18rem;color:#64748b;font-size:.78rem;line-height:1.25}.surfaced-fr-progress-step.is-completed .surfaced-fr-progress-marker{border-color:#28a745;background:#28a745;color:#fff}.surfaced-fr-progress-step.is-completed .surfaced-fr-progress-status{color:#218838;font-weight:700}.surfaced-fr-progress-step.is-current .surfaced-fr-progress-marker{border-color:#401595;color:#401595;box-shadow:0 0 0 4px rgba(64,21,149,.12)}.surfaced-fr-progress-step.is-current .surfaced-fr-progress-label{color:#401595}.surfaced-fr-progress-step.is-current .surfaced-fr-progress-status{font-weight:700}.surfaced-fr-progress-step.is-not-applicable .surfaced-fr-progress-marker{border-color:#0f6f7a;background:#e7f7f9;color:#0f6f7a}.surfaced-fr-progress-step.is-cancelled .surfaced-fr-progress-marker{border-color:#dc3545;background:#fff1f2;color:#b42318}.surfaced-fr-progress-step.is-unavailable .surfaced-fr-progress-marker{border-color:#d97706;background:#fff7e6;color:#9a6700}.surfaced-fr-progress-step.is-locked:not(.is-current){opacity:.62}.surfaced-fr-progress-description{margin:.85rem 0 0;color:#64748b;font-size:.8rem}
            @media(max-width:991px){.surfaced-fr-progress-list{overflow-x:auto;padding-bottom:.5rem}.surfaced-fr-progress-step{flex:0 0 180px}}
            @media(max-width:575px){.surfaced-fr-progress-list{overflow:visible;flex-direction:column;gap:1rem}.surfaced-fr-progress-step{flex:0 0 auto;width:100%}.surfaced-fr-progress-step:not(:last-child)::after{top:36px;bottom:-16px;left:17px;right:auto;width:3px;height:auto}.surfaced-fr-progress-copy{padding-top:.15rem}}
        </style>
    @endpush
@endonce

<section class="profile-card mb-4 surfaced-fr-progress" aria-labelledby="surfaced-fr-progress-heading">
    <div class="profile-card-header">
        <h2 id="surfaced-fr-progress-heading"><i class="mdi mdi-timeline-check-outline" aria-hidden="true"></i>Surfaced FR Progress</h2>
    </div>
    <div class="profile-card-body">
        <ol class="surfaced-fr-progress-list" aria-label="Surfaced former rebel workflow progress">
            @foreach($phases as $phase)
                <li
                    class="surfaced-fr-progress-step is-{{ $phase['state'] }}{{ $phase['is_current'] ? ' is-current' : '' }}"
                    @if($phase['is_current']) aria-current="step" @endif
                    aria-label="{{ $phase['label'] }}: {{ $phase['status'] }}"
                >
                    <span class="surfaced-fr-progress-marker" aria-hidden="true">
                        @if($phase['state'] === 'completed')
                            <i class="mdi mdi-check"></i>
                        @elseif($phase['state'] === 'not-applicable')
                            N/A
                        @elseif($phase['state'] === 'cancelled')
                            <i class="mdi mdi-close"></i>
                        @elseif($phase['state'] === 'locked')
                            <i class="mdi mdi-lock-outline"></i>
                        @elseif($phase['state'] === 'unavailable')
                            <i class="mdi mdi-alert-outline"></i>
                        @else
                            {{ $loop->iteration }}
                        @endif
                    </span>
                    <span class="surfaced-fr-progress-copy">
                        <span class="surfaced-fr-progress-label">{{ $phase['label'] }}</span>
                        <span class="surfaced-fr-progress-status">{{ $phase['status'] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
        @if($currentPhase = collect($phases)->firstWhere('is_current', true))
            <p class="surfaced-fr-progress-description" role="status">
                <strong>{{ $currentPhase['label'] }}:</strong> {{ $currentPhase['description'] }}
            </p>
        @endif
    </div>
</section>
