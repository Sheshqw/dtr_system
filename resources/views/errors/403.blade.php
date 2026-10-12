@extends('errors::layout')

@section('code', '403')
@section('title', 'Access denied')
@section('accent', '#B45309')
@section('message', 'Your account doesn\'t have permission to open this page. If you think you should have access, contact your HR Officer or system administrator.')
@section('actions')
    <a class="btn btn-primary" href="/">Go to Dashboard</a>
@endsection
