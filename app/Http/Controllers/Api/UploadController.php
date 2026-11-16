<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UploadController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'disk' => ['sometimes', 'string', Rule::in(['local', 'public'])],
            'path' => ['sometimes', 'nullable', 'string', 'max:180', 'regex:/^[A-Za-z0-9 _\-\/]+$/'],
        ]);

        $disk = $request->input('disk', 'local');
        $base = $this->tenantPrefix($request->input('path'));
        $file = $request->file('file');

        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = "{$base}/".date('Y/m/d');

        $storedPath = $file->storeAs($path, $filename, $disk);
        $url = Storage::disk($disk)->url($storedPath);

        return response()->json([
            'data' => [
                'path' => $storedPath,
                'url' => $url,
                'disk' => $disk,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string', 'max:255'],
            'disk' => ['sometimes', 'string', Rule::in(['local', 'public'])],
        ]);

        $disk = $request->input('disk', 'local');
        $path = (string) $request->input('path');
        $prefix = $this->tenantPrefix();

        abort_unless(str_starts_with($path, $prefix.'/') && ! str_contains($path, '..'), 404);

        if (! Storage::disk($disk)->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        Storage::disk($disk)->delete($path);

        return response()->json(['message' => 'File deleted']);
    }

    private function tenantPrefix(?string $path = null): string
    {
        $prefix = 'tenants/'.$this->context->tenantId().'/uploads';
        $path = trim((string) $path, '/');

        if ($path === '' || $path === 'uploads') {
            return $prefix;
        }

        $path = preg_replace('#^uploads/?#', '', $path) ?? '';

        return trim($prefix.'/'.$path, '/');
    }
}
