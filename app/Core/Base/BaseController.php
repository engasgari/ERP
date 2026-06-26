<?php

namespace App\Core\Base;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

abstract class BaseController extends Controller
{
    protected function success(
        string $message = 'Operation completed successfully.',
        ?string $route = null,
        array $parameters = []
    ): RedirectResponse {
        return $route
            ? redirect()->route($route, $parameters)->with('success', $message)
            : back()->with('success', $message);
    }

    protected function error(
        string $message = 'Operation failed.'
    ): RedirectResponse {
        return back()->with('error', $message);
    }

    protected function successJson(array $data = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    protected function errorJson(string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}