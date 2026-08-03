<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function ajaxRedirect(Request $request, string $url, ?string $message = null): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            if ($message) {
                session()->flash('success', $message);
            }

            return response()->json(array_filter([
                'message' => $message,
                'redirect' => $url,
            ], fn ($v) => $v !== null && $v !== ''));
        }

        return $message
            ? redirect()->to($url)->with('success', $message)
            : redirect()->to($url);
    }

    protected function ajaxBack(Request $request, string $message, array $errors = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            $normalized = [];
            foreach ($errors as $field => $value) {
                $normalized[$field] = is_array($value) ? $value : [$value];
            }

            return response()->json([
                'message' => $message,
                'errors' => $normalized,
            ], $normalized === [] ? 200 : 422);
        }

        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        return back()->with('success', $message);
    }
}
