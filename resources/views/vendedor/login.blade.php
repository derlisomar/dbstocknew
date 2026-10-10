@extends('vendedor.layout')
@section('titulo', 'Acceso')
@section('contenido')
<div class="pv-card" style="padding:28px">
    <img src="{{ asset('img/landing/logo-claro.png') }}" alt="Dobi Soluciones Informáticas" style="height:52px;display:block;margin-bottom:18px">
    <h1>Panel del vendedor</h1>
    <p class="pv-sub" style="margin-bottom:18px">Acceso privado para configurar el sistema de cada negocio.</p>
    <form method="POST" action="{{ route('vendedor.entrar') }}">
        @csrf
        <label class="l" for="clave">Clave</label>
        <input id="clave" type="password" name="clave" class="in" autofocus required autocomplete="current-password">
        <div style="margin-top:16px"><button class="btn" style="width:100%;padding:11px">Entrar</button></div>
    </form>
</div>
@endsection
