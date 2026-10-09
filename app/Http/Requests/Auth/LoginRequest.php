<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login_input' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $loginInput = trim((string) $this->input('login_input'));

        // Si parece un correo se busca en usu_email; si no, en usu_usuario.
        $campo = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'usu_email' : 'usu_usuario';

        // 'usu_activo' => true: un usuario desactivado no puede entrar aunque la clave sea correcta.
        $credenciales = [
            $campo => $loginInput,
            'password' => $this->input('password'),
            'usu_activo' => true,
        ];

        if (! Auth::attempt($credenciales, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            // Queda registrado en storage/logs/laravel.log (sin guardar la contraseña).
            Log::warning('Intento de login fallido', [
                'login' => $loginInput,
                'ip' => $this->ip(),
            ]);

            // El mismo mensaje para clave incorrecta y usuario desactivado:
            // no se revela cuál de los dos casos es.
            throw ValidationException::withMessages([
                'login_input' => 'Estas credenciales no coinciden con nuestros registros.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $segundos = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login_input' => 'Demasiados intentos. Probá de nuevo en '.ceil($segundos / 60).' minuto(s).',
        ]);
    }

    /**
     * Límite de 5 intentos por combinación usuario + IP.
     * (Antes usaba el campo "email", que este formulario no envía.)
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('login_input')).'|'.$this->ip());
    }
}
