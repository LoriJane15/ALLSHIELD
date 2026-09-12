@props(['summaries', 'links'])

<section class="profile-card mb-4" aria-labelledby="documents-records-heading">
    <div class="profile-card-header"><h2 id="documents-records-heading"><i class="mdi mdi-folder-multiple-outline"></i>Documents/Records</h2></div>
    <div class="profile-card-body"><div class="workflow-tile-list">
        @php
            $labels = array_key_exists('eclip_enrollment_form', $summaries) ? [
                'cdr' => 'CDR',
                'japic' => 'JAPIC Certification',
                'eclip_enrollment_form' => 'E-CLIP Enrollment Form',
                'initial_interview_form' => 'Initial Interview Form',
                'profiling_interview_form' => 'Profiling Interview Form',
                'endorsement_letter' => 'Endorsement Letter',
                'fea' => 'FEA Processing Documents',
                'assistance' => 'Assistance Records',
            ] : [
                'cdr' => 'CDR',
                'fea' => 'FEA Processing Documents',
                'assistance' => 'Assistance Records',
                'japic' => 'JAPIC Certification',
            ];
        @endphp
        @foreach($labels as $key => $label)
            <a class="workflow-tile text-decoration-none" href="{{ $links[$key] }}">
                <strong>{{ $label }}</strong>
                <span>{{ $summaries[$key]['status'] }}</span>
                <span>{{ $summaries[$key]['availability'] }}</span>
            </a>
        @endforeach
    </div></div>
</section>
