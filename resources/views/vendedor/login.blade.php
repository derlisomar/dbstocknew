@extends('vendedor.layout')
@section('titulo', 'Acceso')
@section('contenido')
<div style="max-width:380px;margin:60px auto 0">
    <div class="pv-card">
        <h1>Panel del vendedor</h1>
        <p class="pv-sub">Acceso privado para configurar el sistema de cada negocio.</p>
        <form method="POST" action="{{ route('vendedor.entrar') }}">
            @csrf
            <label class="l">Clave</label>
            <input type="password" name="clave" class="in" autofocus required autocomplete="current-password">
            <div style="margin-top:14px"><button class="btn" style="width:100%">Entrar</button></div>
        </form>
    </div>
</div>
@endsection
