<?php

namespace App\Http\Controllers\Api;

use App\Models\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UploadController extends Controller
{
    
    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // max 10MB
            'category' => 'string|in:certificate,report,document,prescription,profile'
        ]);

        $file = $request->file('file');
        $category = $request->input('category');

        $filename = time() . '.' . $file->getClientOriginalExtension();
        $filepath = $file->storeAs("public/{$category}", $filename);

        $upload = Upload::create([
            'user_id' => $request->user()?->id,
            'category' => $category,
            'file' => $filename,
            'file_path' => "{$category}/{$filename}",
            'mime' => $file->getClientMimeType(),
        ]);

        return response()->json([
            'file_id' => $upload->id,
            'url' => asset('/' . $upload->file_path),
            'status' => 'uploaded'
        ]);
    }

   
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB
            'category' => 'string|in:profile,prescription,report'
        ]);

        $image = $request->file('image');
        $category = $request->input('category');

        $filename = time() . '.' . $image->getClientOriginalExtension();
        $path = $image->storeAs("public/{$category}", $filename);

        $upload = Upload::create([
            'user_id' => $request->user()?->id,
            'category' => $category,
            'file' => $filename,
            'file_path' => "{$category}/{$filename}",
            'mime' => $image->getClientMimeType(),
        ]);

        return response()->json([
            'image_id' => $upload->id,
            'url' => asset('storage/' . $upload->file_path),
            'status' => 'uploaded'
        ]);
    }
}
