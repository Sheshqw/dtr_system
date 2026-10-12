@extends('errors::layout')

@section('code', '503')
@section('title', 'Under maintenance')
@section('message', 'The system is being updated and will be back shortly. Your saved attendance records are not affected. Please check again in a few minutes.')
@section('actions')
    <a class="btn btn-primary" href="{{ url()->current() }}">Check again</a>
@endsection
