<div class="row g-3">
    <div class="col-sm-6">
        <label class="form-label">Full name</label>
        <input name="name" value="{{ old('name') }}" required class="form-control">
        @error('name') <p class="mt-1 text-danger small">{{ $message }}</p> @enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label">Username</label>
        <input name="username" value="{{ old('username') }}" required class="form-control">
        @error('username') <p class="mt-1 text-danger small">{{ $message }}</p> @enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label">Password {{ $isEdit ? '(blank = keep)' : '' }}</label>
        <input name="password" type="password" {{ $isEdit ? '' : 'required' }} class="form-control" autocomplete="new-password">
        @error('password') <p class="mt-1 text-danger small">{{ $message }}</p> @enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label">Role</label>
        <select name="role" required class="form-select">
            @foreach (config('shield.roles') as $key => $meta)
                <option value="{{ $key }}" @selected(old('role') === $key)>{{ $meta['label'] }}</option>
            @endforeach
        </select>
    </div>

    {{-- LGU only --}}
    <div data-role-field="lgu" class="col-sm-6 d-none">
        <label class="form-label">Municipality</label>
        <select name="municipality_id" class="form-select">
            <option value="">Select municipality</option>
            @foreach ($municipalities as $m)
                <option value="{{ $m->id }}">{{ $m->name }}</option>
            @endforeach
        </select>
        @error('municipality_id') <p class="mt-1 text-danger small">{{ $message }}</p> @enderror
    </div>

    {{-- Gov agency only --}}
    <div data-role-field="gov_agency" class="col-sm-6 d-none">
        <label class="form-label">Government Agency</label>
        <select name="gov_agency_id" class="form-select">
            <option value="">Select agency</option>
            @foreach ($agencies as $a)
                <option value="{{ $a->id }}">{{ $a->acronym }}</option>
            @endforeach
        </select>
        @error('gov_agency_id') <p class="mt-1 text-danger small">{{ $message }}</p> @enderror
    </div>

    <div class="col-12">
        <label class="form-label">Logo (optional)</label>
        <input name="logo" type="file" accept="image/*" class="form-control form-control-sm">
    </div>
</div>
