@extends('layouts.admin')

@section('contenido')
<style>
.pf{max-width:880px;margin:0 auto;display:grid;gap:20px}
.pf h2{font-size:1.5rem;font-weight:800;margin:0}
.pf .sub{color:#6b7280;font-size:.9rem;margin-top:4px}
.pf-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:22px 24px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.pf-card h3{font-size:1.05rem;font-weight:700;margin:0 0 4px}
.pf-card .nota{color:#6b7280;font-size:.85rem;margin:0 0 18px}
.pf-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.pf-grid .full{grid-column:1/-1}
.pf label{display:block;font-size:.8rem;font-weight:600;margin-bottom:6px;color:#374151}
.pf input{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:10px 12px;font-size:.95rem;background:#fff;color:#111827;outline:none}
.pf input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.18)}
.pf input[readonly]{background:#f3f4f6;color:#6b7280;cursor:not-allowed}
.pf .err{color:#dc2626;font-size:.8rem;margin-top:5px}
.pf .ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;border-radius:10px;padding:10px 14px;font-size:.9rem;font-weight:600;margin-bottom:16px}
.pf-btn{background:#2563eb;color:#fff;border:0;border-radius:10px;padding:10px 20px;font-weight:700;font-size:.92rem;cursor:pointer}
.pf-btn:hover{background:#1d4ed8}
.pf-ficha{display:flex;align-items:center;gap:16px}
.pf-av{width:56px;height:56px;border-radius:50%;background:#2563eb;color:#fff;display:grid;place-items:center;font-size:1.4rem;font-weight:800;flex:none}
.pf-rol{display:inline-block;margin-top:4px;font-size:.75rem;font-weight:700;background:#dbeafe;color:#1e40af;border-radius:999px;padding:2px 10px}
html.dark .pf h2,html.dark .pf-card h3{color:#fff}
html.dark .pf .sub,html.dark .pf-card .nota{color:#9ca3af}
html.dark .pf-card{background:#1c2434;border-color:#1f2937}
html.dark .pf label{color:#d1d5db}
html.dark .pf input{background:#111827;border-color:#374151;color:#f3f4f6}
html.dark .pf input[readonly]{background:#0f172a;color:#9ca3af}
html.dark .pf .ok{background:rgba(6,78,59,.35);border-color:#065f46;color:#6ee7b7}
html.dark .pf-rol{background:rgba(30,64,175,.35);color:#93c5fd}
@media (max-width:640px){.pf-grid{grid-template-columns:1fr}}
</style>

<div class="pf">
    <div>
        <h2>Mi perfil</h2>
        <p class="sub">Tus datos y tu contraseña. Para cambiar tu usuario de acceso o tu rol, pedíselo a un administrador.</p>
    </div>

    <div class="pf-card">
        <div class="pf-ficha">
            <div class="pf-av">{{ mb_strtoupper(mb_substr($usuario->usu_nombre, 0, 1)) }}</div>
            <div>
                <strong>{{ $usuario->usu_nombre }} {{ $usuario->usu_apellido }}</strong><br>
                <span class="pf-rol">{{ $usuario->rol?->rol_nombre ?? 'Sin rol' }}</span>
            </div>
        </div>
    </div>

    <form class="pf-card" method="POST" action="{{ route('perfil.actualizar') }}" id="datos">
        @csrf @method('PUT')
        <h3>Datos personales</h3>
        <p class="nota">Así te ven los demás usuarios en el sistema.</p>
        @if(session('ok_perfil'))<div class="ok">{{ session('ok_perfil') }}</div>@endif
        <div class="pf-grid">
            <div>
                <label for="usu_nombre">Nombre</label>
                <input id="usu_nombre" name="usu_nombre" type="text" value="{{ old('usu_nombre', $usuario->usu_nombre) }}" maxlength="100" required>
                @error('usu_nombre')<div class="err">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="usu_apellido">Apellido</label>
                <input id="usu_apellido" name="usu_apellido" type="text" value="{{ old('usu_apellido', $usuario->usu_apellido) }}" maxlength="100">
                @error('usu_apellido')<div class="err">{{ $message }}</div>@enderror
            </div>
            <div class="full">
                <label for="usu_email">Correo</label>
                <input id="usu_email" name="usu_email" type="email" value="{{ old('usu_email', $usuario->usu_email) }}" maxlength="150" required>
                @error('usu_email')<div class="err">{{ $message }}</div>@enderror
            </div>
            <div>
                <label>Usuario de acceso</label>
                <input type="text" value="{{ $usuario->usu_usuario }}" readonly>
            </div>
            <div>
                <label>Cédula</label>
                <input type="text" value="{{ $usuario->usu_cedula }}" readonly>
            </div>
        </div>
        <div style="margin-top:20px"><button class="pf-btn" type="submit">Guardar datos</button></div>
    </form>

    <form class="pf-card" method="POST" action="{{ route('perfil.clave') }}" id="clave" autocomplete="off">
        @csrf @method('PUT')
        <h3>Contraseña</h3>
        <p class="nota">Usá al menos 8 caracteres. Si te la dieron por correo (por ejemplo en la demo), conviene cambiarla.</p>
        @if(session('ok_clave'))<div class="ok">{{ session('ok_clave') }}</div>@endif
        <div class="pf-grid">
            <div class="full">
                <label for="clave_actual">Contraseña actual</label>
                <input id="clave_actual" name="clave_actual" type="password" autocomplete="current-password" required>
                @error('clave_actual')<div class="err">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="clave_nueva">Contraseña nueva</label>
                <input id="clave_nueva" name="clave_nueva" type="password" autocomplete="new-password" minlength="8" required>
                @error('clave_nueva')<div class="err">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="clave_nueva_confirmation">Repetí la contraseña nueva</label>
                <input id="clave_nueva_confirmation" name="clave_nueva_confirmation" type="password" autocomplete="new-password" minlength="8" required>
            </div>
        </div>
        <div style="margin-top:20px"><button class="pf-btn" type="submit">Cambiar contraseña</button></div>
    </form>
</div>
@endsection
