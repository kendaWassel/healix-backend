<?php

namespace App\Http\Controllers\Api;

use App\Models\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadRequest;

class UploadController extends Controller
{
    
    public function uploadFile(UploadRequest $request)
    {
        $validated = $request->validated();

        $file = $request->file('file');
        $category = $validated['category'];

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

   
    public function uploadImage(UploadRequest $request)
    {
        $validated = $request->validated();

        $image = $request->file('image');
        $category = $validated['category'];

        $filename = time() . '.' . $image->getClientOriginalExtension();
        $path = $image->storeAs("public/{$category}", $filename);

        $upload = Upload::create([
            'user_id' => $request->user()?->id,
            'category' => $category,
            'file' => $filename,
            'file_path' => $path,
            'mime' => $image->getClientMimeType(),
        ]);

        return response()->json([
            'image_id' => $upload->id,
            'url' => asset('storage/' . $category . $upload->file_path),
            'status' => 'uploaded'
        ]);
    }
}
