@extends('layouts.skydash-v')
@section('title', 'Former Rebels')
@section('heading', 'Former Rebels Monitoring')

@php
    $badge = fn ($s) => match ($s) {
        'Active' => 'badge badge-success',
        'Inactive' => 'badge badge-danger',
        'On hold' => 'badge badge-warning',
        'Reintegrated' => 'badge badge-primary',
        default => 'badge badge-secondary',
    };
@endphp

@section('content')
<div class="row">
    <div class="col-md-12 grid-margin">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="font-weight-bold">Former Rebels Monitoring</h3>
                <p class="text-muted mb-0">Add and manage Former Rebels</p>
            </div>
            <a href="{{ route('mblrc.fr.create') }}" class="btn btn-primary">+ Register Former Rebel</a>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr class="bg-primary text-white">
                                <th>Profile ID</th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Batch</th>
                                <th>Status</th>
                                <th>Profile</th>
                                <th>Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($frs as $fr)
                                <tr>
                                    <td>{{ $fr->classified_id }}</td>
                                    <td>
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width:40px;height:40px;overflow:hidden;">
                                            <img src="{{ asset('assets/img/fr-profile.jpg') }}" alt="FR Profile" style="width:100%;height:100%;object-fit:cover;">
                                        </div>
                                    </td>
                                    <td>{{ trim($fr->lastname.' '.$fr->firstname.' '.$fr->middlename.' '.$fr->suffix) }}</td>
                                    <td>{{ $fr->barangay?->name }}{{ $fr->municipality ? ', '.$fr->municipality->name : '' }}</td>
                                    <td>Batch {{ $fr->batch_section ?: '—' }} - {{ $fr->batch_year ?: '—' }}</td>
                                    <td><span class="{{ $badge($fr->status) }}">{{ $fr->status }}</span></td>
                                    <td>
                                        <a href="{{ route('mblrc.fr.show', $fr) }}" class="btn btn-outline-primary btn-sm">Profile</a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center" style="gap:15px;">
                                            <a href="{{ route('mblrc.fr.edit', $fr) }}" style="color:#007bff;text-decoration:none;" title="Edit">
                                                <i class="mdi mdi-pencil" style="font-size:18px;"></i>
                                            </a>
                                            <form method="POST" action="{{ route('mblrc.fr.destroy', $fr) }}"
                                                  data-confirm="Are you sure you want to delete this Former Rebel? This action cannot be undone." data-confirm-title="Confirm delete" data-confirm-action="Delete"
                                                  style="display:inline;">
                                                @csrf @method('DELETE')
                                                <button type="submit" style="background:none;border:none;color:#dc3545;padding:0;cursor:pointer;" title="Delete">
                                                    <i class="mdi mdi-delete" style="font-size:18px;"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center">No records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $frs->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
