@extends('layouts.admin')

@section('contenido')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">🔓 Apertura de Caja</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Inicia el turno de caja declarando el monto inicial en efectivo.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="p-4 bg-red-100 dark:bg-red-500/10 border border-red-400 text-red-700 dark:text-red-400 rounded-lg">
            <ul>
                @foreach ($errors->all() as $error)<li>• {{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg p-6 shadow-default">
        <form action="{{ route('finanzas.apertura.store') }}" method="POST" class="space-y-4">
            @csrf
            
            <div>
                <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Seleccionar Caja *</label>
                <select name="caj_id" class="w-full rounded bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none" required>
                    <option value="">Seleccione caja a aperturar...</option>
                    @foreach($cajas as $caja)
                        <option value="{{ $caja->caj_id }}">Caja: {{ $caja->caj_nombre }} ({{ $caja->sucursal->suc_nombre ?? '' }})</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Monto Inicial Gs. *</label>
                    <input type="number" step="0.01" name="ses_monto_inicial_gs" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none" required value="0">
                </div>
                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Monto Inicial USD ($)</label>
                    <input type="number" step="0.01" name="ses_monto_inicial_usd" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none" value="0">
                </div>
                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Monto Inicial BRL (R$)</label>
                    <input type="number" step="0.01" name="ses_monto_inicial_brl" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none" value="0">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2.5 rounded-lg transition shadow-md">
                    🚀 Abrir Caja y Comenzar Turno
                </button>
            </div>
        </form>
    </div>
</div>
@endsection