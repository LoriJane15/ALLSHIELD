@php
    $value = static fn (string $key, mixed $default = ''): mixed => old('draft.'.$key, data_get($payload, $key, $default));
    $addresseeLines = old('draft.addressee_lines', data_get($payload, 'addressee_lines', ['', '', '']));
    $addresseeLines = array_values(array_pad(array_slice(is_array($addresseeLines) ? $addresseeLines : [], 0, 3), 3, ''));
    $beneficiaries = old('draft.beneficiaries', data_get($payload, 'beneficiaries', []));
    $beneficiaries = array_values(array_pad(is_array($beneficiaries) ? $beneficiaries : [], 3, []));
    $assistanceSummary = old('draft.assistance_summary', data_get($payload, 'assistance_summary', []));
    $assistanceSummary = array_values(array_pad(is_array($assistanceSummary) ? $assistanceSummary : [], 1, []));
    $beneficiaryFields = [
        'name' => ['Name of FR/s and/or FVE/s', 255],
        'address' => ['Address', 500],
        'fr_group_type' => ['Group type — FR', 100],
        'fve_group_type' => ['Group type — FVE', 100],
        'assistance_type' => ['Type of Assistance', 255],
        'amount' => ['Amount', 100],
        'pending_cases' => ['With Pending Cases (If yes, please specify the venue, docket no., case title)', 1000],
    ];
    $assistanceFields = [
        'immediate_assistance' => 'Immediate Assistance',
        'livelihood_assistance' => 'Livelihood Assistance',
        'firearms_remuneration' => 'Firearms Remuneration',
        'reintegration_assistance' => 'Reintegration Assistance',
        'fr' => 'FR',
        'fve' => 'FVE',
    ];
@endphp

<form id="pswdoEndorsementDraftForm"
      class="pswdo-endorsement-editor"
      data-editable="{{ $canEdit ? 'true' : 'false' }}"
      @if($canEdit) method="POST" action="{{ $updateUrl }}" @endif>
    @if($canEdit)
        @csrf
        @method('PUT')
    @endif
    <input id="pswdoEndorsementRevision" type="hidden" name="revision" value="{{ $draft?->revision ?? 0 }}">

    @unless($canEdit)<fieldset disabled>@endunless
        <section class="pswdo-eclip-group" aria-labelledby="endorsement-letter-information">
            <h3 id="endorsement-letter-information">Letter Information</h3>
            <div class="pswdo-eclip-grid">
                <div>
                    <label class="pswdo-eclip-label" for="endorsement-fiscal-year">FY</label>
                    <input class="form-control" id="endorsement-fiscal-year" name="draft[fiscal_year]" maxlength="30" value="{{ $value('fiscal_year') }}">
                </div>
                <div>
                    <label class="pswdo-eclip-label" for="endorsement-date">Date</label>
                    <input class="form-control" id="endorsement-date" type="date" name="draft[letter_date]" value="{{ $value('letter_date') }}">
                </div>
            </div>
        </section>

        <section class="pswdo-eclip-group" aria-labelledby="endorsement-addressee">
            <h3 id="endorsement-addressee">Addressee</h3>
            <div class="pswdo-eclip-grid">
                @foreach($addresseeLines as $index => $line)
                    <div class="pswdo-eclip-field-full">
                        <label class="pswdo-eclip-label" for="endorsement-addressee-{{ $index }}">Addressee Line {{ $index + 1 }}</label>
                        <input class="form-control" id="endorsement-addressee-{{ $index }}" name="draft[addressee_lines][{{ $index }}]" maxlength="255" value="{{ $line }}">
                        <small class="form-text text-muted">Appears on line {{ $index + 1 }} above the salutation.</small>
                    </div>
                @endforeach
                <div class="pswdo-eclip-field-full">
                    <label class="pswdo-eclip-label" for="endorsement-salutation">Dear</label>
                    <input class="form-control" id="endorsement-salutation" name="draft[salutation]" maxlength="255" value="{{ $value('salutation') }}">
                </div>
            </div>
        </section>

        <section class="pswdo-eclip-group" aria-labelledby="endorsement-beneficiaries">
            <h3 id="endorsement-beneficiaries">Endorsed Beneficiaries</h3>
            <div class="pswdo-endorsement-table-wrap" data-endorsement-dynamic-table="beneficiaries" data-min-rows="3">
                <table class="pswdo-endorsement-table">
                    <thead>
                    <tr>
                        <th>No.</th>
                        <th>Name of FR/s and/or FVE/s</th>
                        <th>Address</th>
                        <th>Group type — FR</th>
                        <th>Group type — FVE</th>
                        <th>Type of Assistance</th>
                        <th>Amount</th>
                        <th>With Pending Cases <em>(If yes, please specify the venue, docket no., case title)</em></th>
                        <th><span class="sr-only">Row action</span></th>
                    </tr>
                    </thead>
                    <tbody data-endorsement-rows>
                    @foreach($beneficiaries as $index => $beneficiary)
                        <tr data-endorsement-row>
                            <td class="pswdo-endorsement-row-number" data-endorsement-row-number>{{ $index + 1 }}</td>
                            @foreach($beneficiaryFields as $field => [$label, $maxlength])
                                <td data-label="{{ $label }}">
                                    <label class="pswdo-endorsement-cell-label" for="endorsement-{{ str_replace('_', '-', $field) }}-{{ $index }}">{{ $label }}</label>
                                    @if($field === 'pending_cases')
                                        <textarea class="form-control" id="endorsement-{{ str_replace('_', '-', $field) }}-{{ $index }}" name="draft[beneficiaries][{{ $index }}][{{ $field }}]" rows="2" maxlength="{{ $maxlength }}">{{ data_get($beneficiary, $field, '') }}</textarea>
                                    @else
                                        <input class="form-control" id="endorsement-{{ str_replace('_', '-', $field) }}-{{ $index }}" name="draft[beneficiaries][{{ $index }}][{{ $field }}]" maxlength="{{ $maxlength }}" value="{{ data_get($beneficiary, $field, '') }}">
                                    @endif
                                </td>
                            @endforeach
                            <td class="pswdo-endorsement-row-action">
                                <button class="btn btn-outline-danger btn-sm" type="button" data-endorsement-remove-row aria-label="Remove beneficiary row {{ $index + 1 }}">
                                    <i class="mdi mdi-delete-outline" aria-hidden="true"></i> Remove
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($canEdit)
                <div class="mt-3">
                    <button class="btn btn-outline-primary btn-sm" type="button" data-endorsement-add-row="beneficiaries">
                        <i class="mdi mdi-plus" aria-hidden="true"></i> Add Beneficiary
                    </button>
                </div>
            @endif
            <div class="pswdo-eclip-grid mt-3">
                <div>
                    <label class="pswdo-eclip-label" for="endorsement-grand-total">GRAND TOTAL:</label>
                    <input class="form-control" id="endorsement-grand-total" name="draft[grand_total]" maxlength="100" value="{{ $value('grand_total') }}">
                </div>
            </div>
        </section>

        <section class="pswdo-eclip-group" aria-labelledby="endorsement-assistance-summary">
            <h3 id="endorsement-assistance-summary">Assistance Summary</h3>
            <div class="pswdo-endorsement-table-wrap" data-endorsement-dynamic-table="assistance_summary" data-min-rows="1">
                <table class="pswdo-endorsement-table pswdo-endorsement-assistance-table">
                    <thead>
                    <tr>
                        @foreach($assistanceFields as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                        <th><span class="sr-only">Row action</span></th>
                    </tr>
                    </thead>
                    <tbody data-endorsement-rows>
                    @foreach($assistanceSummary as $index => $assistance)
                        <tr data-endorsement-row>
                            @foreach($assistanceFields as $field => $label)
                                <td data-label="{{ $label }}">
                                    <label class="pswdo-endorsement-cell-label" for="endorsement-assistance-{{ str_replace('_', '-', $field) }}-{{ $index }}">{{ $label }}</label>
                                    <input class="form-control"
                                           id="endorsement-assistance-{{ str_replace('_', '-', $field) }}-{{ $index }}"
                                           type="number"
                                           min="0"
                                           max="999999"
                                           name="draft[assistance_summary][{{ $index }}][{{ $field }}]"
                                           value="{{ data_get($assistance, $field, '') }}">
                                </td>
                            @endforeach
                            <td class="pswdo-endorsement-row-action">
                                <button class="btn btn-outline-danger btn-sm" type="button" data-endorsement-remove-row aria-label="Remove assistance row {{ $index + 1 }}">
                                    <i class="mdi mdi-delete-outline" aria-hidden="true"></i> Remove
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($canEdit)
                <div class="mt-3">
                    <button class="btn btn-outline-primary btn-sm" type="button" data-endorsement-add-row="assistance_summary">
                        <i class="mdi mdi-plus" aria-hidden="true"></i> Add Assistance
                    </button>
                </div>
            @endif
        </section>
    @unless($canEdit)</fieldset>@else
        <div class="text-right">
            <button class="btn btn-primary" type="submit"><i class="mdi mdi-content-save mr-1"></i> Save Draft</button>
        </div>
    @endunless
</form>
