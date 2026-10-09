@extends('vendedor.layout')
@section('titulo', 'Negocio')
@section('contenido')
<h1>Datos del negocio</h1>
<p class="pv-sub">El nombre y el logo aparecen en el menú, el inicio de sesión, los presupuestos y los comprobantes.</p>
<form method="POST" action="{{ route('vendedor.negocio.guardar') }}" enctype="multipart/form-data" class="pv-card">
    @csrf
    <div class="pv-grid">
        <div><label class="l">Nombre del negocio *</label><input class="in" name="negocio_nombre" value="{{ old('negocio_nombre', $cfg['negocio_nombre'] ?? '') }}" maxlength="120" required></div>
        <div><label class="l">RUC</label><input class="in" name="negocio_ruc" value="{{ old('negocio_ruc', $cfg['negocio_ruc'] ?? '') }}" maxlength="30"></div>
        <div><label class="l">Teléfono</label><input class="in" name="negocio_telefono" value="{{ old('negocio_telefono', $cfg['negocio_telefono'] ?? '') }}" maxlength="40"></div>
        <div><label class="l">Correo</label><input class="in" type="email" name="negocio_email" value="{{ old('negocio_email', $cfg['negocio_email'] ?? '') }}" maxlength="150"></div>
    </div>
    <div style="margin-top:12px"><label class="l">Dirección</label><input class="in" name="negocio_direccion" value="{{ old('negocio_direccion', $cfg['negocio_direccion'] ?? '') }}" maxlength="200"></div>
    <div style="margin-top:12px"><label class="l">Leyenda al pie de los comprobantes</label><input class="in" name="negocio_pie_ticket" value="{{ old('negocio_pie_ticket', $cfg['negocio_pie_ticket'] ?? '') }}" maxlength="200" placeholder="Gracias por su compra"></div>
    <div style="margin-top:12px;display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        @if($logo)<img src="{{ $logo }}" alt="Logo" style="height:56px;border:1px solid var(--bd);border-radius:8px;padding:4px;background:#fff">@endif
        <div><label class="l">Logo (PNG, JPG o WEBP, hasta 1 MB)</label><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></div>
        @if($logo)<label class="mu"><input type="checkbox" name="quitar_logo" value="1"> Quitar el logo</label>@endif
    </div>
    <div style="margin-top:16px"><button class="btn ok">Guardar</button></div>
</form>
@endsection
