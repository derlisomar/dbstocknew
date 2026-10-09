<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class VendedorClave extends Command
{
    protected $signature = 'vendedor:clave';

    protected $description = 'Genera la clave del panel del vendedor para pegar en el archivo .env';

    public function handle(): int
    {
        $clave = (string) $this->secret('Escribí la clave que querés usar (mínimo 10 caracteres)');
        if (mb_strlen($clave) < 10) {
            $this->error('La clave es muy corta. Usá al menos 10 caracteres.');

            return self::FAILURE;
        }
        if ($clave !== (string) $this->secret('Repetila')) {
            $this->error('Las dos claves no coinciden.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Pegá esta línea en tu archivo .env (con las comillas simples) y después corré: php artisan config:clear');
        $this->newLine();
        $this->line("VENDEDOR_CLAVE_HASH='".Hash::make($clave)."'");
        $this->newLine();
        $this->line('La dirección del panel es: /'.config('vendedor.ruta').' (podés cambiarla con VENDEDOR_RUTA en el .env).');

        return self::SUCCESS;
    }
}
