@extends('errors::layout')

@section('code', '404')
@section('title', 'Page not found')
@section('message', 'The page you\'re looking for doesn\'t exist or may have been moved. Check the address, or head back to the dashboard.')
@section('actions')
    <a class="btn btn-primary" href="/">Go to Dashboard</a>
@endsection
