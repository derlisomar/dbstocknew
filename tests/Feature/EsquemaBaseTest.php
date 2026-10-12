<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * El archivo database/schema/dbstock_estructura.sql sirve para instalar desde cero.
 * Si se agrega una migración nueva, hay que regenerarlo (o este test avisa).
 */
class EsquemaBaseTest extends TestCase
{
    public function test_el_esquema_base_marca_todas_las_migraciones_menos_la_de_planes(): void
    {
        $sql = file_get_contents(base_path('database/schema/dbstock_estructura.sql'));
        $this->assertStringContainsString('CREATE TABLE public.ventas', $sql);

        foreach (glob(database_path('migrations/*.php')) as $archivo) {
            $nombre = basename($archivo, '.php');
            $esPlanes = $nombre === '2026_10_18_000001_parte11_planes_definitivos';
            $this->assertSame(! $esPlanes, str_contains($sql, "'{$nombre}'"), "Migración {$nombre}: revisá database/schema/dbstock_estructura.sql");
        }
    }

    public function test_el_esquema_no_trae_datos_de_nadie(): void
    {
        $sql = file_get_contents(base_path('database/schema/dbstock_estructura.sql'));
        $this->assertSame(0, preg_match('/^COPY public\./m', $sql));
        $this->assertSame(1, preg_match_all('/^INSERT INTO /m', $sql));   // solo la lista de migraciones
    }
}
