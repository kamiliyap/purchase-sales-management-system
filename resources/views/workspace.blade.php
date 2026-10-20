@extends('layouts.app')
@section('title', 'Workspace')
@section('content')
<section class="surface-card p-4">
    <h1 class="h3">Workspace {{ auth()->user()->role }}</h1>
    <p>Pilih modul yang tersedia melalui sidebar.</p>
</section>
@endsection
