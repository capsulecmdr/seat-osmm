@extends('web::layouts.app')

@section('page_title', 'Home')

@section('content')
    <div class="container-fluid">
        <div class="row">
            @forelse ($homeElements as $element)
                {!! $element['html'] !!}
            @empty
                <div class="col-12">
                    <div class="alert alert-info">
                        No home elements have been registered yet.
                    </div>
                </div>
            @endforelse
        </div>
    </div>
@endsection
