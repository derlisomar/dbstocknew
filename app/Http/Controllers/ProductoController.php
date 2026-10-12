<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\Deposito;
use App\Models\ProductoCodigo;
use App\Services\StockService;
use App\Support\Unidades;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    /** Códigos de barra validados de la última carga (se guardan junto con el producto). */
    private array $codigosPendientes = [];

    private function guardarCodigos(Producto $producto): void
    {
        ProductoCodigo::where('pro_id', $producto->pro_id)->delete();
        foreach ($this->codigosPendientes as $codigo) {
            ProductoCodigo::create(['pro_id' => $producto->pro_id, 'cod_codigo' => $codigo]);
        }
    }

    public function index()
    {
        // 1. Calcular las estadísticas para las tarjetas superiores
        $totalProductos = \App\Models\Producto::count();
        $sinStock = \App\Models\Producto::where('pro_stockactual', '<=', 0)->count();
        $inactivos = \App\Models\Producto::where('pro_activo', false)->count();

        // 2. Obtener los productos de 30 en 30 usando paginate() en lugar de get()
        $productos = \App\Models\Producto::with(['categoria', 'proveedor', 'sucursal', 'deposito', 'codigos'])
                        ->orderBy('pro_id', 'desc')
                        ->paginate(30);

        $categorias = \App\Models\Categoria::all();
        $proveedores = \App\Models\Proveedor::all();
        $sucursales = \App\Models\Sucursal::all();
        $depositos = \App\Models\Deposito::all();
        $unidades = Unidades::nombres();

        return view('productos.index', compact(
            'unidades',
            'productos', 
            'categorias', 
            'proveedores', 
            'sucursales', 
            'depositos',
            'totalProductos',
            'sinStock',
            'inactivos'
        ));
    }

    /** Reglas de validación compartidas por crear y editar. */
    private function reglas(?int $id = null): array
    {
        return [
            'pro_codigo' => ['required', 'string', 'max:50', Rule::unique('productos', 'pro_codigo')->ignore($id, 'pro_id'), function ($attr, $valor, $fail) use ($id) {
                if (ProductoCodigo::where('cod_codigo', $valor)->when($id, fn ($q) => $q->where('pro_id', '!=', $id))->exists()) {
                    $fail('Ese código ya es un código de barras de otro producto.');
                }
            }],
            'pro_nombre' => ['required', 'string', 'max:255'],
            'pro_descripcion' => ['nullable', 'string', 'max:1000'],
            'cat_id' => ['required', 'integer', 'exists:categorias,cat_id'],
            'prov_id' => ['nullable', 'integer', 'exists:proveedores,prov_id'],
            'suc_id' => ['required', 'integer', 'exists:sucursales,suc_id'],
            'dep_id' => ['required', 'integer', 'exists:depositos,dep_id'],
            'pro_preciocosto' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'pro_precioventa' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'pro_preciomayorista' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'pro_stockminimo' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'pro_unidad' => ['nullable', Rule::in(Unidades::codigos())],
            'pro_plu' => ['nullable', 'regex:/^[0-9]{1,10}$/', function ($attr, $valor, $fail) use ($id) {
                $buscado = ltrim((string) $valor, '0');
                $otro = Producto::whereNotNull('pro_plu')->when($id, fn ($q) => $q->where('pro_id', '!=', $id))->pluck('pro_plu')
                    ->first(fn ($x) => ltrim((string) $x, '0') === $buscado);
                if ($otro !== null) {
                    $fail('Ese código de balanza ya lo tiene otro producto.');
                }
            }],
            'codigos' => ['nullable', 'array', 'max:20'],
            'codigos.*' => ['string', 'max:50', 'regex:/^[A-Za-z0-9._\-]+$/', function ($attr, $valor, $fail) use ($id) {
                $existe = ProductoCodigo::where('cod_codigo', $valor)->when($id, fn ($q) => $q->where('pro_id', '!=', $id))->exists()
                    || Producto::where('pro_codigo', $valor)->when($id, fn ($q) => $q->where('pro_id', '!=', $id))->exists();
                if ($existe) {
                    $fail("El código de barras {$valor} ya pertenece a otro producto.");
                }
            }],
            'pro_tipo_iva' => ['nullable', 'integer', Rule::in([0, 5, 10])],
            'pro_fechavencimiento' => ['nullable', 'date'],
            'pro_imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // Solo se usa al crear: es el stock con el que arranca el producto.
            'pro_stockactual' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    private function datosLimpios(Request $request, ?int $id = null): array
    {
        // Los códigos de barra llegan en un cuadro de texto: uno por línea (o separados por coma).
        $lista = collect(preg_split('/[\s,;]+/', (string) $request->input('codigos_texto', ''), -1, PREG_SPLIT_NO_EMPTY))->unique()->values()->all();
        $request->merge(['codigos' => $lista]);

        $datos = $request->validate($this->reglas($id), [
            'pro_plu.regex' => 'El código de balanza tiene que tener solo números (hasta 10).',
            'codigos.*.regex' => 'Los códigos de barra solo pueden llevar letras, números, punto, guion o guion bajo.',
        ]);

        $unidad = Unidades::normalizar($datos['pro_unidad'] ?? null);
        $datos['pro_unidad'] = $unidad;
        if (! Unidades::esFraccionable($unidad)) {
            $extras = ['pro_stockminimo' => (float) $datos['pro_stockminimo'], 'pro_stockactual' => (float) ($datos['pro_stockactual'] ?? 0)];
            foreach ($extras as $campo => $v) {
                if ($v != floor($v)) {
                    throw ValidationException::withMessages([$campo => 'Un producto que se vende por unidad solo admite cantidades enteras.']);
                }
            }
            if ($id && ($actual = (float) Producto::whereKey($id)->value('pro_stockactual')) != floor($actual)) {
                throw ValidationException::withMessages(['pro_unidad' => 'El stock actual tiene decimales ('.$actual.'): corregilo en Inventario antes de pasar el producto a unidad.']);
            }
        }
        $datos['pro_plu'] = ($datos['pro_plu'] ?? '') === '' ? null : $datos['pro_plu'];
        $this->codigosPendientes = $datos['codigos'] ?? [];
        unset($datos['pro_stockactual'], $datos['pro_imagen'], $datos['codigos']);

        $datos['pro_preciomayorista'] = $datos['pro_preciomayorista'] ?? 0;
        $datos['pro_tipo_iva'] = $datos['pro_tipo_iva'] ?? 10;
        $datos['pro_activo'] = $request->boolean('pro_activo');

        if ($request->hasFile('pro_imagen')) {
            $datos['pro_imagen'] = $request->file('pro_imagen')->store('productos', 'public');
        }

        return $datos;
    }

    private function avisoPrecio(array $datos): ?string
    {
        return (float) $datos['pro_precioventa'] < (float) $datos['pro_preciocosto']
            ? 'Atención: el precio de venta es menor que el costo, ese producto se vende con pérdida.'
            : null;
    }

    public function store(Request $request, StockService $stock)
    {
        $datos = $this->datosLimpios($request);
        $stockInicial = (float) $request->input('pro_stockactual', 0);

        DB::transaction(function () use ($datos, $stockInicial, $stock) {
            $datos['pro_stockactual'] = 0;
            // Se asigna directo porque pro_stockactual no es de asignación masiva.
            $producto = new Producto($datos);
            $producto->pro_stockactual = 0;
            $producto->save();
            $this->guardarCodigos($producto);

            if ($stockInicial > 0) {
                $stock->mover((int) $producto->pro_id, 'CARGA_INICIAL', $stockInicial, 'Stock al crear el producto');
            }
        });

        return redirect()->route('productos.index')
            ->with('success', 'Producto registrado correctamente.')
            ->with('warning', $this->avisoPrecio($datos));
    }

    public function update(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);
        $datos = $this->datosLimpios($request, (int) $producto->pro_id);

        // El stock NO se cambia desde acá: se corrige en Inventario, que deja historial.
        DB::transaction(function () use ($producto, $datos) {
            $producto->update($datos);
            $this->guardarCodigos($producto);
        });

        return redirect()->route('productos.index')
            ->with('success', 'Producto actualizado correctamente.')
            ->with('warning', $this->avisoPrecio($datos));
    }

    public function destroy($id)
    {
        $producto = Producto::findOrFail($id);

        $tieneVentas = DB::table('detalle_ventas')->where('pro_id', $producto->pro_id)->exists();
        $tieneMovimientos = DB::table('stock_movimientos')->where('pro_id', $producto->pro_id)
            ->where('smo_tipo', '<>', 'SALDO_INICIAL')->exists();

        // Un producto con historial no se borra: los tickets viejos lo necesitan. Se desactiva.
        if ($tieneVentas || $tieneMovimientos) {
            $producto->update(['pro_activo' => false]);

            return redirect()->route('productos.index')
                ->with('success', 'El producto tiene historial de ventas o stock, por eso no se borró: quedó desactivado.');
        }

        DB::transaction(function () use ($producto) {
            DB::table('stock_movimientos')->where('pro_id', $producto->pro_id)->delete();
            $producto->delete();
        });

        return redirect()->route('productos.index')->with('success', 'Producto eliminado correctamente.');
    }
}
