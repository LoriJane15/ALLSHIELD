@props(['phases'])

@once
    @push('styles')
        <style>
            .surfaced-fr-progress {
                overflow: hidden;
            }
            .surfaced-fr-progress-list {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                list-style: none;
                margin: 0;
                padding: 0.75rem 0;
                position: relative;
            }
            .surfaced-fr-progress-step {
                position: relative;
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
                flex: 1;
                min-width: 0;
                padding: 0 0.5rem;
                z-index: 1;
            }
            .surfaced-fr-progress-step:not(:last-child)::after {
                content: "";
                position: absolute;
                z-index: 1;
                top: 21px;
                left: 50%;
                width: 100%;
                height: 3px;
                background: #e2e8f0;
                border-radius: 9999px;
            }
            .surfaced-fr-progress-step.is-completed:not(:last-child)::after {
                background: #312e81;
            }
            .surfaced-fr-progress-marker {
                position: relative;
                z-index: 2;
                display: flex;
                flex: 0 0 42px;
                align-items: center;
                justify-content: center;
                width: 42px;
                height: 42px;
                border: 2px solid #cbd5e1;
                border-radius: 50%;
                background: #ffffff;
                color: #64748b;
                font-size: 0.875rem;
                font-weight: 800;
                transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
                margin-bottom: 0.5rem;
            }
            .surfaced-fr-progress-copy {
                position: relative;
                z-index: 2;
                min-width: 0;
            }
            .surfaced-fr-progress-label, .surfaced-fr-progress-status {
                display: block;
            }
            .surfaced-fr-progress-label {
                color: #0f172a;
                font-size: 0.875rem;
                font-weight: 800;
                line-height: 1.3;
            }
            .surfaced-fr-progress-status {
                margin-top: 0.25rem;
                color: #64748b;
                font-size: 0.75rem;
                font-weight: 600;
                line-height: 1.3;
            }
            .surfaced-fr-progress-step.is-completed .surfaced-fr-progress-marker {
                border-color: #312e81;
                background: #312e81;
                color: #ffffff;
                box-shadow: 0 0 0 4px rgba(49, 46, 129, 0.15);
            }
            .surfaced-fr-progress-step.is-completed .surfaced-fr-progress-label {
                color: #1e1b4b;
            }
            .surfaced-fr-progress-step.is-completed .surfaced-fr-progress-status {
                color: #312e81;
                font-weight: 750;
            }
            .surfaced-fr-progress-step.is-current .surfaced-fr-progress-marker {
                border-color: #2563eb;
                background: #ffffff;
                color: #2563eb;
                box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.18);
                transform: scale(1.05);
            }
            .surfaced-fr-progress-step.is-current .surfaced-fr-progress-label {
                color: #2563eb;
            }
            .surfaced-fr-progress-step.is-current .surfaced-fr-progress-status {
                font-weight: 750;
                color: #2563eb;
            }
            .surfaced-fr-progress-step.is-not-applicable .surfaced-fr-progress-marker {
                border-color: #64748b;
                background: #f8fafc;
                color: #64748b;
            }
            .surfaced-fr-progress-step.is-cancelled .surfaced-fr-progress-marker {
                border-color: #e11d48;
                background: #fff1f2;
                color: #e11d48;
            }
            .surfaced-fr-progress-step.is-unavailable .surfaced-fr-progress-marker {
                border-color: #d97706;
                background: #fffbeb;
                color: #d97706;
            }
            .surfaced-fr-progress-step.is-locked:not(.is-current) {
                opacity: 0.55;
            }
            .surfaced-fr-progress-description {
                margin: 1.25rem 0 0;
                padding: 0.9rem 1.25rem;
                background: #eef2ff;
                border: 1px solid #c7d2fe;
                border-left: 4px solid #312e81;
                border-radius: 12px;
                color: #1e1b4b;
                font-size: 0.84rem;
                display: flex;
                align-items: center;
                gap: 0.65rem;
            }
            .surfaced-fr-progress-description i {
                font-size: 1.25rem;
                color: #312e81;
                flex-shrink: 0;
            }
            @media(max-width:991px){
                .surfaced-fr-progress-list {
                    flex-direction: column;
                    gap: 1.25rem;
                    align-items: flex-start;
                }
                .surfaced-fr-progress-step {
                    flex-direction: row;
                    align-items: center;
                    text-align: left;
                    gap: 1rem;
                    width: 100%;
                    padding: 0;
                }
                .surfaced-fr-progress-step:not(:last-child)::after {
                    top: 42px;
                    bottom: -20px;
                    left: 20px;
                    width: 3px;
                    height: auto;
                    right: auto;
                }
                .surfaced-fr-progress-marker {
                    margin-bottom: 0;
                }
            }
        </style>
    @endpush
@endonce

<section class="profile-card mb-4 surfaced-fr-progress" aria-labelledby="surfaced-fr-progress-heading">
    <div class="profile-card-header">
        <h2 id="surfaced-fr-progress-heading">
            <i class="mdi mdi-checkbox-marked-circle-outline" style="color: #312e81;" aria-hidden="true"></i>
            <span>Surfaced FR Progress</span>
        </h2>
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
            <div class="surfaced-fr-progress-description" role="status">
                <i class="mdi mdi-information-outline" aria-hidden="true"></i>
                <div>
                    <strong>{{ $currentPhase['label'] }}:</strong> {{ $currentPhase['description'] }}
                </div>
            </div>
        @endif
    </div>
</section>

