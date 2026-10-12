@extends('errors::layout')

@section('code', '429')
@section('title', 'Too many requests')
@section('accent', '#B45309')
@section('message', 'Too many attempts were made in a short time. Please wait a minute, then try again.')
@section('actions')
    <a class="btn btn-primary" href="{{ url()->current() }}">Try again</a>
    <a class="btn btn-ghost" href="/">Go to Dashboard</a>
@endsection
