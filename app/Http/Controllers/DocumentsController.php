<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentsController extends Controller
{
    public function index()
    {
        $folderStructure = $this->getFolderStructure('');
        return response()->json($folderStructure);
    }

    public function getFolderStructure($directory)
    {
        $files = Storage::disk('shared')->files($directory);
        $directories = Storage::disk('shared')->directories($directory);

        $structure = [];

        foreach($files as $file){
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            if(in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'pdf'])){
                $structure[] = [
                    'type' => 'file',
                    'name' => basename($file),
                    'path' => $file
                ];
            }
        }

        foreach($directories as $dir){
            $structure[] = [
                'type' => 'directory',
                'name' => basename($dir),
                'path' => $dir,
                'contents' => $this->getFolderStructure($dir)
            ];
        }

        return $structure;
    }

    public function download($filename)
    {
        $path = Storage::disk('shared')->path($filename);

        if(!file_exists($path)){
            return response()->json(['error' => 'File non trovato'], 404);
        }

        if(!is_readable($path)){
            return response()->json(['error' => 'File non leggibile'], 403);
        }

        return response()->download($path);
    }

}
