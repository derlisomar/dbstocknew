<?php

namespace App\Http\Controllers;

use App\Services\NotificacionService;
use Illuminate\Http\JsonResponse;

class NotificacionController extends Controller
{
    public function index(): JsonResponse
    {
        $items = NotificacionService::para(auth()->user());

        return response()->json(['total' => count($items), 'items' => $items]);
    }
}
