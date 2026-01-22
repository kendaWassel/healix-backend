<?php

namespace App\Http\Controllers\Api;

use App\Models\Upload;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends Controller
{
    
    public function uploadFile(UploadRequest $request)
    {
        return $this->handleUpload(
            $request,
            $request->file('file')
        );
    }

    public function uploadImage(UploadRequest $request)
    {
        return $this->handleUpload(
            $request,
            $request->file('image')
        );
    }

    private function handleUpload(UploadRequest $request, $file)
    {
        $category = $request->validated('category');

        $path = $file->store($category, 'public');

        $upload = Upload::create([
            'user_id'   => $request->user()?->id,
            'category'  => $category,
            'file'      => basename($path),
            'file_path' => $path,
            'mime'      => $file->getClientMimeType(),
        ]);

        // Build download URL using the current request host (supports ngrok/public tunnels)
        $relative = route('download.file', $upload->id, false);
        $downloadUrl = $request->getSchemeAndHttpHost() . $relative;

        if ($request->hasFile('file')) {
            return response()->json([
                'file_id' => $upload->id,
                'url'     => $downloadUrl,
                'status'  => 'uploaded'
            ]);
        } else {
            return response()->json([
                'image_id' => $upload->id,
                'url'      => $downloadUrl,
                'status'   => 'uploaded'
            ]);
        }

    }
       public function downloadFile($id): Response
    {
        $upload = Upload::findOrFail($id);

        // Resolve the actual filesystem path for the stored file on the public disk
        $path = Storage::disk('public')->path($upload->file_path);

        if (!file_exists($path)) {
            abort(404);
        }

        $headers = [
            'Content-Type' => $upload->mime,
        ];

        return response()->download($path, $upload->file, $headers);
    }
    

}
