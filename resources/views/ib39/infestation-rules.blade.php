@extends('layouts.skydash-v')
@section('title', 'Map Legend')
@section('heading', 'Map Legend & Classification')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="mb-4">
                <h3 class="font-weight-bold mb-0">Infestation Rules</h3>
                <p class="text-muted mb-0">
                    A barangay's former-rebel count is matched top-to-bottom (highest threshold first)
                    to set its status and map colour. Saving re-classifies every barangay immediately.
                </p>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('ib39.rules.update') }}" id="rulesForm">
                @csrf @method('PUT')
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="rulesTable">
                        <thead>
                            <tr>
                                <th style="width:9rem">FRs ≥</th>
                                <th>Status</th>
                                <th style="width:12rem">Colour</th>
                                <th>Legend label</th>
                                <th style="width:6rem"></th>
                            </tr>
                        </thead>
                        <tbody id="rulesBody">
                            @foreach ($rules as $i => $rule)
                                <tr>
                                    <td><input type="number" min="0" name="rules[{{ $i }}][min_frs]" value="{{ $rule->min_frs }}" class="form-control" required></td>
                                    <td><input type="text" name="rules[{{ $i }}][status]" value="{{ $rule->status }}" class="form-control" required></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="rule-swatch" style="background: {{ $rule->color }}"></span>
                                            <input type="text" name="rules[{{ $i }}][color]" value="{{ $rule->color }}" class="form-control js-color" required>
                                        </div>
                                    </td>
                                    <td><input type="text" name="rules[{{ $i }}][label]" value="{{ $rule->label }}" class="form-control"></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger js-remove-rule"><i class="mdi mdi-delete"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between mt-3">
                    <button type="button" class="btn btn-light" id="addRuleBtn"><i class="mdi mdi-plus"></i> Add rule</button>
                    <button type="submit" class="btn btn-primary"><i class="mdi mdi-content-save"></i> Save &amp; re-classify</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Row template for "Add rule" --}}
    <template id="ruleRowTemplate">
        <tr>
            <td><input type="number" min="0" name="rules[__I__][min_frs]" value="0" class="form-control" required></td>
            <td><input type="text" name="rules[__I__][status]" value="" class="form-control" placeholder="Status name" required></td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <span class="rule-swatch" style="background: rgba(59,130,246,0.5)"></span>
                    <input type="text" name="rules[__I__][color]" value="rgba(59,130,246,0.5)" class="form-control js-color" required>
                </div>
            </td>
            <td><input type="text" name="rules[__I__][label]" value="" class="form-control" placeholder="Legend caption"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger js-remove-rule"><i class="mdi mdi-delete"></i></button></td>
        </tr>
    </template>
@endsection

@push('styles')
<style>
    .rule-swatch {
        display:inline-block; width:26px; height:26px; border-radius:6px;
        border:1px solid #cbd5e1; flex:0 0 auto;
    }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        const body = document.getElementById('rulesBody');
        const tpl = document.getElementById('ruleRowTemplate');
        let counter = {{ count($rules) }};

        // live swatch preview as the colour text changes
        document.addEventListener('input', (e) => {
            if (!e.target.classList.contains('js-color')) return;
            const sw = e.target.closest('td')?.querySelector('.rule-swatch');
            if (sw) sw.style.background = e.target.value;
        });

        document.getElementById('addRuleBtn')?.addEventListener('click', () => {
            body.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__I__', counter++));
        });

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-remove-rule');
            if (!btn) return;
            if (body.querySelectorAll('tr').length <= 1) return; // keep at least one
            btn.closest('tr').remove();
        });
    })();
</script>
@endpush
