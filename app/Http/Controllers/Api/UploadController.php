<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB max
            'disk' => ['sometimes', 'string', Rule::in(['local', 'public'])],
            'path' => ['sometimes', 'string', 'max:255'],
        ]);

        $disk = $request->input('disk', 'local');
        $path = $request->input('path', 'uploads');
        $file = $request->file('file');
        
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "{$path}/" . date('Y/m/d');
        
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
            'path' => ['required', 'string'],
            'disk' => ['sometimes', 'string', Rule::in(['local', 'public'])],
        ]);

        $disk = $request->input('disk', 'local');
        
        if (!Storage::disk($disk)->exists($request->path)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        
        Storage::disk($disk)->delete($request->path);
        
        return response()->json(['message' => 'File deleted']);
    }
}
