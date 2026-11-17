@extends('layouts.app')

@section('title', 'Add Product')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Add hardware product</h1>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm">Back to products</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            @include('products._form', ['action' => route('products.store'), 'product' => null])
        </div>
    </div>
@endsection
