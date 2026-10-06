@php($readOnly = $readOnly ?? false)
@foreach($fields as $field)
    @php($value = $draft[$field['key']] ?? null)
    @if($field['type'] === 'notice')
        @php($photoSlot = $field['key'] === 'photo_notice_3' ? \App\Enums\Ib39FeaUploadSlot::JustificationSurrendered : \App\Enums\Ib39FeaUploadSlot::JustificationComparison)
        <section class="border rounded p-3 mb-4"><strong>{{ $field['label'] }}</strong>@include('ib39.fea.partials.supporting-photo-upload', ['slot' => $photoSlot, 'render' => 'controls'])</section>
    @elseif($field['type'] === 'table')
        <section class="mb-4" data-draft-table="{{ $field['key'] }}" data-next-index="{{ count($value ?? []) }}">
            <label class="font-weight-bold">{{ $field['label'] }}</label>
            <div class="table-responsive"><table class="table table-bordered"><thead><tr>@foreach($field['columns'] as $label)<th>{{ $label }}</th>@endforeach @unless($readOnly)<th><span class="sr-only">Row action</span></th>@endunless</tr></thead><tbody>
            @foreach(($value ?? []) as $index => $row)<tr>@foreach($field['columns'] as $key => $label)<td><input class="form-control" name="draft[{{ $field['key'] }}][{{ $index }}][{{ $key }}]" value="{{ $row[$key] ?? '' }}" maxlength="500" @if($key === 'quantity') type="number" min="0" max="999999" @endif></td>@endforeach @unless($readOnly)<td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row>Remove</button></td>@endunless</tr>@endforeach
            </tbody></table></div>
            @unless($readOnly)
            <button type="button" class="btn btn-sm btn-outline-secondary" data-add-row>Add row</button>
            <template><tr>@foreach($field['columns'] as $key => $label)<td><input class="form-control" data-name="draft[{{ $field['key'] }}][__INDEX__][{{ $key }}]" maxlength="500" @if($key === 'quantity') type="number" min="0" max="999999" @endif></td>@endforeach<td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row>Remove</button></td></tr></template>
            @endunless
        </section>
    @elseif($field['type'] === 'choice')
        <div class="form-group"><label>{{ $field['label'] }}</label><div>@foreach($field['choices'] as $choice)<label class="mr-3"><input type="radio" name="draft[{{ $field['key'] }}]" value="{{ $choice }}" @checked($value === $choice)> {{ $choice }}</label>@endforeach</div></div>
    @else
        <div class="form-group"><label for="field-{{ $document->id }}-{{ $field['key'] }}">{{ $field['label'] }}</label>
            @if($field['type'] === 'textarea')<textarea id="field-{{ $document->id }}-{{ $field['key'] }}" name="draft[{{ $field['key'] }}]" class="form-control" maxlength="4000" rows="3">{{ $value }}</textarea>
            @else<input id="field-{{ $document->id }}-{{ $field['key'] }}" name="draft[{{ $field['key'] }}]" class="form-control" value="{{ $value }}" maxlength="500" type="{{ in_array($field['type'], ['date', 'time'], true) ? $field['type'] : ($field['type'] === 'decimal' ? 'number' : 'text') }}" @if($field['type'] === 'decimal') min="0" max="999999999.99" step="0.01" @endif>@endif
            @isset($field['title'])<small class="form-text text-muted">{{ $field['title'] }}</small>@endisset
        </div>
    @endif
@endforeach
