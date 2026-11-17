@extends('layouts.app')

@section('title', 'Edit Staff')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Edit staff account</h1>
        <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary btn-sm">Back to staff</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            @include('staff._form', ['action' => route('staff.update', $staff), 'staff' => $staff])
        </div>
    </div>
@endsection
