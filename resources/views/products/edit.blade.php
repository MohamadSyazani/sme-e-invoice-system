@extends('layouts.app')

@section('title', 'Edit Product')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
            Edit {{ $product->name }}
            @unless ($product->isActive())
                <span class="badge text-bg-secondary align-middle fs-6">ARCHIVED</span>
            @endunless
        </h1>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm">Back to products</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            @include('products._form', ['action' => route('products.update', $product), 'product' => $product])
        </div>
    </div>
@endsection
