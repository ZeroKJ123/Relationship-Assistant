@extends('layouts.app')
@section('title', __('Наша пара'))
@section('content')
@include('dashboard.content')
@endsection
@section('navigation')<x-bottom-navigation :couple="$dashboard->couple" />@endsection
