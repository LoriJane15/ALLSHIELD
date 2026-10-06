@php
    $draftReadOnly = $draftReadOnly ?? false;
    $certificate = $payload['certificate'];
    $narrative = $certificate['narrative_values'];
    $prepared = old('certificate.prepared_by', $certificate['prepared_by'] ?: [['full_name' => '', 'rank' => '']]);
    $attested = old('certificate.attested_by', $certificate['attested_by'] ?: [['full_name' => '', 'rank' => '']]);
    $draftSaveFailed = !$draftReadOnly && array_key_exists('control_number', session()->getOldInput());
    $sourcePhotoVersionId = data_get($payload, 'source_snapshot.subject_photo_version_id');
    $workspacePhotoUrl = $processing->currentPhotoVersion
        ? route('japic.certifications.photos.show', [$processing, $processing->currentPhotoVersion])
        : ($sourcePhotoVersionId ? route('cdr.photos.preview', $sourcePhotoVersionId) : null);
@endphp
    <section class="process-draft-toolbar" aria-label="JAPIC certification drafting controls">
        <div class="process-autosave-status saved" @unless($draftReadOnly) id="japicAutoSaveStatus" aria-live="polite" @endunless>
            <i class="mdi {{ $draftReadOnly ? 'mdi-lock-outline' : 'mdi-cloud-check' }}" @unless($draftReadOnly) id="japicAutoSaveIcon" @endunless></i>
            <span @unless($draftReadOnly) id="japicAutoSaveText" @endunless>{{ $draftReadOnly ? 'Completed / Read-only' : 'All changes saved' }}</span>
        </div>
        <a class="btn btn-outline-primary btn-sm @unless($canPreviewDraft ?? $processing->draft !== null) disabled @endunless"
           id="japicPreviewDraft"
           href="{{ route('japic.certifications.preview', $processing) }}"
           target="_blank" rel="noopener"
           @unless($canPreviewDraft ?? $processing->draft !== null) aria-disabled="true" tabindex="-1" @endunless>
            <i class="mdi mdi-eye-outline mr-1"></i> Preview Draft
        </a>
    </section>

    <div class="japic-draft-area" data-japic-draft-area>
    {{-- Certification photograph is part of the drafting area, while keeping its existing secure upload endpoint. --}}
    <section class="mblrc-form-card" data-japic-photo-section>
        <div class="mblrc-form-card-header">
            <div class="d-flex align-items-center gap-3">
                <div class="mblrc-form-header-icon" style="background: #eef2ff; color: #312e81;">
                    <i class="mdi mdi-camera-account"></i>
                </div>
                <div>
                    <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Certification Photograph</h4>
                    <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Encrypted photograph used by the official JAPIC certification</p>
                </div>
            </div>
        </div>
        <div class="mblrc-form-card-body">
            @if($workspacePhotoUrl)
                <div class="p-3 mb-3 rounded-3 text-center" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <img src="{{ $workspacePhotoUrl }}" alt="{{ $processing->currentPhotoVersion ? 'Current JAPIC certification photograph' : 'Frozen CDR certification photograph' }}" style="display:block; width:100%; max-width:240px; max-height:300px; margin:0 auto; object-fit:contain; border:1px solid #cbd5e1; border-radius:10px; background:#fff;">
                    <strong class="d-block mt-2" style="color: #0f172a;">{{ $processing->currentPhotoVersion ? 'Current certification photograph' : 'Frozen CDR photograph' }}</strong>
                    @if($processing->currentPhotoVersion)
                        <div class="text-muted small">{{ $processing->currentPhotoVersion->width }} &times; {{ $processing->currentPhotoVersion->height }} pixels</div>
                    @endif
                </div>
            @else
                <div class="p-3 mb-3 rounded-3 text-muted" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                    <i class="mdi mdi-information-outline mr-1"></i> No certification photograph is available.
                </div>
            @endif

            @unless($draftReadOnly)
            <form id="japicPhotoUploadForm" method="POST" enctype="multipart/form-data" action="{{ route('japic.certifications.photos.store', $processing) }}" class="p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                @csrf
                <input type="hidden" name="revision" value="{{ $processing->draft?->revision ?? 0 }}">
                <input type="hidden" name="lock_version" value="{{ $processing->lock_version }}">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="certification-photo" class="mblrc-label">Choose Photo (JPEG or PNG)</label>
                        <input id="certification-photo" class="form-control mblrc-input" type="file" name="photo" accept="image/jpeg,image/png,.jpg,.jpeg,.png" required>
                        <small class="form-text text-muted mt-1">Maximum 5 MiB, 100&times;100 minimum, 8000&times;8000 maximum, and 16 million pixels.</small>
                        <div id="japicPhotoUploadStatus" class="small text-primary mt-2" role="status" aria-live="polite" hidden>Uploading...</div>
                    </div>
                </div>
            </form>
            @endunless
        </div>
    </section>

    {{-- Main Draft Form --}}
    <form @unless($draftReadOnly) method="POST" action="{{ route('japic.certifications.draft.update', $processing) }}" @endunless id="japicDraftForm">
        @unless($draftReadOnly)
        @csrf
        @method('PUT')
        <input type="hidden" name="revision" id="japicDraftRevision" value="{{ $processing->draft?->revision ?? 0 }}">
        <input type="hidden" name="lock_version" id="japicDraftLockVersion" value="{{ $processing->lock_version }}">
        @endunless
        @if($draftReadOnly)<fieldset disabled aria-label="Completed JAPIC certification content">@endif

        {{-- 2. Official Control Information --}}
        <div class="mblrc-form-card">
            <div class="mblrc-form-card-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="mblrc-form-header-icon" style="background: #fffbeb; color: #d97706;">
                        <i class="mdi mdi-numeric"></i>
                    </div>
                    <div>
                        <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Official Control Information</h4>
                        <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Control tracking number and date of issuance</p>
                    </div>
                </div>
            </div>
            <div class="mblrc-form-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="control-number" class="mblrc-label">Control Number</label>
                        <input id="control-number" class="form-control mblrc-input @error('control_number') is-invalid @enderror" name="control_number" maxlength="100" required value="{{ old('control_number', $certificate['control_number'] ?? $processing->control_number) }}" aria-describedby="control-number-error">
                        <div id="control-number-error" class="invalid-feedback" @unless($errors->has('control_number')) hidden @endunless>{{ $errors->first('control_number') }}</div>
                    </div>
                    <div class="col-md-6">
                        <label for="date-issued" class="mblrc-label">Date Issued</label>
                        <input id="date-issued" class="form-control mblrc-input" type="date" name="certificate[date_issued]" value="{{ old('certificate.date_issued', $certificate['date_issued'] ?? '') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Structured Narrative --}}
        <div class="mblrc-form-card">
            <div class="mblrc-form-card-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="mblrc-form-header-icon" style="background: #eff6ff; color: #2563eb;">
                        <i class="mdi mdi-file-document-outline"></i>
                    </div>
                    <div>
                        <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Structured Certification Narrative</h4>
                        <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Standardized intelligence certification statement</p>
                    </div>
                </div>
            </div>
            <div class="mblrc-form-card-body">
                <div class="row g-3">
                    @foreach([
                        'fr_name' => ['FR name', 255],
                        'residence' => ['Residence', 1000],
                        'former_organization_or_category' => ['Former organization or category', 500],
                        'areas_of_operation' => ['Areas of operation', 2000],
                        'affiliated_organization' => ['Affiliated organization', 500],
                        'surrendered_to' => ['Office or organization surrendered to', 500],
                        'surrendered_on' => ['Date surrendered', null],
                        'surrendered_at' => ['Place surrendered', 1000],
                    ] as $field => [$label, $maxLength])
                        <div class="col-md-6">
                            <label class="mblrc-label" for="narrative-{{ $field }}">{{ $label }}</label>
                            <input id="narrative-{{ $field }}" class="form-control mblrc-input" type="{{ $field === 'surrendered_on' ? 'date' : 'text' }}" name="certificate[narrative_values][{{ $field }}]" @if($maxLength) maxlength="{{ $maxLength }}" @endif required value="{{ old('certificate.narrative_values.'.$field, $narrative[$field] ?? '') }}">
                        </div>
                    @endforeach
                </div>
                <p class="text-muted small mt-3 mb-0">{{ $wording['affiliation_subject'] }} started {{ $wording['possessive'] }} affiliation @if(filled($affiliationPeriod)) during {{ $affiliationPeriod }} @endif.</p>
                <div class="p-3 rounded-3 mt-2" style="background: #f1f5f9; font-style: italic; color: #475569; font-size: 0.9rem;">
                    {{ $wording['purpose'] }}
                </div>
            </div>
        </div>

        {{-- 4. Signatories --}}
        @foreach(['prepared_by' => ['Prepared By', $prepared, 'mdi-account-edit', '#eef2ff', '#312e81'], 'attested_by' => ['Attested By', $attested, 'mdi-account-check', '#fffbeb', '#d97706']] as $section => [$heading, $rows, $icon, $bg, $color])
            <div class="mblrc-form-card">
                <div class="mblrc-form-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mblrc-form-header-icon" style="background: {{ $bg }}; color: {{ $color }};">
                            <i class="mdi {{ $icon }}"></i>
                        </div>
                        <div>
                            <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">{{ $heading }}</h4>
                            <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Designated military and police signatories</p>
                        </div>
                    </div>
                    @unless($draftReadOnly)<button type="button" class="btn btn-sm add-personnel" data-section="{{ $section }}" style="background: #eef2ff; color: #312e81; font-weight: 750; border-radius: 8px; border: 1px solid #c7d2fe;">
                        <i class="mdi mdi-plus mr-1"></i> Add Person
                    </button>@endunless
                </div>
                <div class="mblrc-form-card-body">
                    <div id="{{ $section }}-rows" data-personnel-section="{{ $section }}">
                        @foreach($rows as $index => $row)
                            <div class="personnel-row" data-personnel-row>
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-7">
                                        <label class="mblrc-label">Full Name</label>
                                        <input class="form-control mblrc-input" name="certificate[{{ $section }}][{{ $index }}][full_name]" maxlength="255" required value="{{ $row['full_name'] ?? '' }}" placeholder="Enter full name...">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="mblrc-label">Rank / Designation</label>
                                        <input class="form-control mblrc-input" name="certificate[{{ $section }}][{{ $index }}][rank]" maxlength="100" required value="{{ $row['rank'] ?? '' }}" placeholder="Enter rank...">
                                    </div>
                                    @unless($draftReadOnly)<div class="col-md-1 d-flex align-items-end justify-content-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-personnel" aria-label="Remove person" style="border-radius: 8px; padding: 0.45rem 0.65rem;" title="Remove this person">
                                            <i class="mdi mdi-delete"></i>
                                        </button>
                                    </div>@endunless
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        @if($processing->delayed)
            <div class="mblrc-form-card">
                <div class="mblrc-form-card-body">
                    <label for="delay-reason" class="mblrc-label text-danger font-weight-bold">
                        <i class="mdi mdi-alert mr-1"></i> Justification / Delay Reason
                    </label>
                    <textarea id="delay-reason" class="form-control mblrc-input" name="delay_reason" maxlength="2000" required placeholder="State the reason for delay...">{{ old('delay_reason') }}</textarea>
                </div>
            </div>
        @endif

        @if($draftReadOnly)</fieldset>@endif
        {{-- Action Bar --}}
        @unless($draftReadOnly)
        <div class="mblrc-action-bar mb-4">
            <a href="{{ route('japic.certifications.workspace', $processing) }}" class="btn btn-light" style="border-radius: 10px; font-weight: 700; border: 1px solid #cbd5e1; padding: 0.65rem 1.25rem;">
                Cancel
            </a>
            <button class="btn" type="submit" style="background: #312e81; color: #ffffff; font-weight: 750; border-radius: 10px; padding: 0.65rem 1.65rem; box-shadow: 0 4px 12px rgba(49, 46, 129, 0.25);">
                <i class="mdi mdi-content-save mr-1"></i> Save Draft
            </button>
        </div>
        @endunless
    </form>
    </div>
@unless($draftReadOnly)
<template id="personnel-template">
    <div class="personnel-row" data-personnel-row>
        <div class="row g-3 align-items-center">
            <div class="col-md-7">
                <label class="mblrc-label">Full Name</label>
                <input class="form-control mblrc-input" data-field="full_name" maxlength="255" required placeholder="Enter full name...">
            </div>
            <div class="col-md-4">
                <label class="mblrc-label">Rank / Designation</label>
                <input class="form-control mblrc-input" data-field="rank" maxlength="100" required placeholder="Enter rank...">
            </div>
            <div class="col-md-1 d-flex align-items-end justify-content-end">
                <button type="button" class="btn btn-outline-danger btn-sm remove-personnel" aria-label="Remove person" style="border-radius: 8px; padding: 0.45rem 0.65rem;" title="Remove this person">
                    <i class="mdi mdi-delete"></i>
                </button>
            </div>
        </div>
    </div>
</template>

@push('scripts')
<script>
(() => {
    const maximum = {{ $maxPersonnelRows }};
    const form = document.getElementById('japicDraftForm');
    const status = document.getElementById('japicAutoSaveStatus');
    const statusIcon = document.getElementById('japicAutoSaveIcon');
    const statusText = document.getElementById('japicAutoSaveText');
    const preview = document.getElementById('japicPreviewDraft');
    const controlNumber = document.getElementById('control-number');
    const controlNumberError = document.getElementById('control-number-error');
    const photoForm = document.getElementById('japicPhotoUploadForm');
    const photoInput = document.getElementById('certification-photo');
    const photoStatus = document.getElementById('japicPhotoUploadStatus');
    let timer = null;
    let saving = false;
    let submitting = false;
    let changeVersion = 0;
    let dirty = @json($draftSaveFailed);

    const setStatus = (state, text) => {
        if (!status) return;
        status.className = `process-autosave-status ${state}`;
        statusText.textContent = text;
        statusIcon.className = state === 'saving' ? 'mdi mdi-loading mdi-spin'
            : (state === 'unsaved' ? 'mdi mdi-clock-alert-outline' : 'mdi mdi-cloud-check');
    };

    if (dirty) setStatus('unsaved', 'Unsaved changes');

    const clearControlNumberError = () => {
        controlNumber?.classList.remove('is-invalid');
        if (controlNumberError) {
            controlNumberError.hidden = true;
            controlNumberError.textContent = '';
        }
    };

    const showControlNumberError = message => {
        if (!message) return;
        controlNumber?.classList.add('is-invalid');
        if (controlNumberError) {
            controlNumberError.hidden = false;
            controlNumberError.textContent = message;
        }
    };

    const queueSave = () => {
        clearTimeout(timer);
        timer = setTimeout(save, 1200);
    };

    const save = async () => {
        if (!form || saving || !dirty) return;
        if (!controlNumber.value.trim()) {
            setStatus('unsaved', 'Unsaved changes');
            return;
        }
        const savingVersion = changeVersion;
        saving = true;
        setStatus('saving', 'Saving...');
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                showControlNumberError(data.errors?.control_number?.[0]);
                throw new Error('Draft autosave failed.');
            }
            document.querySelectorAll('input[name="revision"]').forEach(input => { input.value = data.revision; });
            document.querySelectorAll('input[name="lock_version"]').forEach(input => { input.value = data.lock_version; });
            clearControlNumberError();
            dirty = changeVersion !== savingVersion;
            setStatus(dirty ? 'unsaved' : 'saved', dirty ? 'Unsaved changes' : 'All changes saved');
            preview?.classList.remove('disabled');
            preview?.removeAttribute('aria-disabled');
            preview?.removeAttribute('tabindex');
        } catch (error) {
            setStatus('unsaved', 'Unsaved changes');
        } finally {
            saving = false;
            if (dirty && changeVersion !== savingVersion) queueSave();
        }
    };

    const schedule = () => {
        changeVersion += 1;
        dirty = true;
        setStatus('unsaved', 'Unsaved changes');
        queueSave();
    };
    const reindex = section => section.querySelectorAll('[data-personnel-row]').forEach((row, index) => row.querySelectorAll('[data-field], input[name]').forEach(input => {
        const field = input.dataset.field || input.name.match(/\[([^\]]+)]$/)?.[1];
        input.name = `certificate[${section.dataset.personnelSection}][${index}][${field}]`;
    }));
    document.querySelectorAll('[data-personnel-section]').forEach(section => {
        section.addEventListener('click', event => {
            const removeBtn = event.target.closest('.remove-personnel');
            if (!removeBtn) return;
            if (section.querySelectorAll('[data-personnel-row]').length > 1) {
                removeBtn.closest('[data-personnel-row]').remove();
                reindex(section);
                schedule();
            }
        });
        reindex(section);
    });
    document.querySelectorAll('.add-personnel').forEach(button => button.addEventListener('click', () => {
        const section = document.querySelector(`[data-personnel-section="${button.dataset.section}"]`);
        if (section.querySelectorAll('[data-personnel-row]').length >= maximum) return;
        section.append(document.getElementById('personnel-template').content.cloneNode(true));
        reindex(section);
        schedule();
    }));
    form?.addEventListener('input', schedule);
    form?.addEventListener('change', schedule);
    controlNumber?.addEventListener('input', clearControlNumberError);
    form?.addEventListener('submit', () => {
        clearTimeout(timer);
        submitting = true;
    });
    photoInput?.addEventListener('change', () => {
        if (!photoInput.files?.length || !photoForm) return;
        if (photoStatus) photoStatus.hidden = false;
        if (typeof photoForm.requestSubmit === 'function') {
            photoForm.requestSubmit();
        } else {
            photoForm.submit();
        }
    });
    window.addEventListener('beforeunload', event => {
        if (!dirty || submitting) return;
        event.preventDefault();
        event.returnValue = '';
    });
})();
</script>
@endpush
@endunless
