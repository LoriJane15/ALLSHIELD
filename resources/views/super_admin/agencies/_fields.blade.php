<div class="mb-3">
    <label class="form-label">Acronym</label>
    <input name="acronym" value="{{ old('acronym') }}" required class="form-control" placeholder="DILG">
    @error('acronym') <p class="mt-1 text-danger small">{{ $message }}</p> @enderror
</div>
<div class="mb-3">
    <label class="form-label">Name</label>
    <input name="name" value="{{ old('name') }}" required class="form-control" placeholder="Department of the Interior and Local Government">
    @error('name') <p class="mt-1 text-danger small">{{ $message }}</p> @enderror
</div>
<div class="mb-3">
    <label class="form-label">Profile / description</label>
    <input name="profile" value="{{ old('profile') }}" class="form-control">
</div>
