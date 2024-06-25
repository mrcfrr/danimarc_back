<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentsController extends Controller
{
    public function index()
    {
        $allFiles = Storage::disk('shared')->allFiles();

        // Filtra solo i file JPG e PDF
        $filteredFiles = array_filter($allFiles, function($file) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            return in_array(strtolower($extension), ['jpg', 'jpeg', 'pdf']);
        });

        return response()->json(array_values($filteredFiles));
    }

    public function download($filename)
    {
        $path = Storage::disk('shared')->path($filename);
        return response()->download($path);
    }
}
