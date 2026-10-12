@extends('errors::layout')

@section('code', '419')
@section('title', 'Session expired')
@section('accent', '#B45309')
@section('message', 'Your session expired for security reasons. Please sign in again to continue.')
@section('actions')
    <a class="btn btn-primary" href="/login">Sign in again</a>
@endsection
