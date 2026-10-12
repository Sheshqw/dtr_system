@extends('errors::layout')

@section('code', '500')
@section('title', 'Something went wrong')
@section('accent', '#B91C1C')
@section('message', 'The system ran into an unexpected problem. It has been recorded for the administrators. Please try again in a moment.')
@section('actions')
    <a class="btn btn-primary" href="{{ url()->current() }}">Try again</a>
    <a class="btn btn-ghost" href="/">Go to Dashboard</a>
@endsection
