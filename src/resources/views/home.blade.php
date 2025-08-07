@extends('web::layouts.app')

@section('page-title', 'Welcome')
@section('page-icon', 'fas fa-home')
@section('content')

<div class="container-fluid">
    {{-- Row 1 - 6 widgets @ col-md-2 --}}
    <div class="row mb-3">
        @foreach($homeElements->where('row', 1) as $element)
            <div class="col-md-2 mb-3">
                {!! $element['html'] !!}
            </div>
        @endforeach
    </div>

    {{-- Row 2 - 4 widgets @ col-md-3 --}}
    <div class="row mb-3">
        @foreach($homeElements->where('row', 2) as $element)
            <div class="col-md-3 mb-3">
                {!! $element['html'] !!}
            </div>
        @endforeach
    </div>

    {{-- Row 3 - 2 widgets @ col-md-6 --}}
    <div class="row mb-3">
        @foreach($homeElements->where('row', 3) as $element)
            <div class="col-md-6 mb-3">
                {!! $element['html'] !!}
            </div>
        @endforeach
    </div>

    {{-- Row 4 - 1 widget @ col-md-12 --}}
    <div class="row mb-3">
        @foreach($homeElements->where('row', 4) as $element)
            <div class="col-md-12 mb-3">
                {!! $element['html'] !!}
            </div>
        @endforeach
    </div>
</div>

@endsection
