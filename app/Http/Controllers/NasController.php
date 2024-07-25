<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class NasController extends Controller
{
    private $nasPath = '\\\\192.168.1.223\\public';

    public function index(Request $request)
    {
        $folderStructure = $this->getFolderStructure('');
        
        return response()->json($folderStructure);
    }

    public function getFolderStructure($directory)
    {
        $structure = [];
        $fullPath = $this->nasPath . ($directory ? '\\' . $directory : '');

        try {
            Log::info('Accessing directory: ' . $fullPath);
            if (!file_exists($fullPath)) {
                throw new \Exception('Directory does not exist: ' . $fullPath);
            }

            $files = scandir($fullPath);
            $hasFiles = false;
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === '' || $file[0] === '.') {
                    continue;
                }

                $filePath = $fullPath . '\\' . $file;
                $encodedPath = rawurlencode($directory . '\\' . $file);

                if (is_dir($filePath)) {
                    $contents = $this->getFolderStructure($directory . '\\' . $file);
                    $hasFilesInSubDir = $this->containsFiles($contents);
                    $structure[] = [
                        'type' => 'directory',
                        'name' => $file,
                        'path' => $encodedPath,
                        'contents' => $contents,
                        'qr_code' => $hasFilesInSubDir ? $this->generateQrCode($encodedPath) : null,
                    ];
                } else {
                    $hasFiles = true;
                    $structure[] = [
                        'type' => 'file',
                        'name' => $file,
                        'path' => $encodedPath,
                    ];
                }
            }

            if ($hasFiles) {
                $qrCodeUrl = $this->generateQrCode(rawurlencode($directory));
                $structure[] = ['qr_code' => $qrCodeUrl];
            }

            Log::info('Folder structure: ' . json_encode($structure)); // Log the folder structure
            return $structure;

        } catch (\Exception $e) {
            Log::error('Errore durante l\'accesso alla directory: ' . $e->getMessage());
            return response()->json(['error' => 'Errore durante l\'accesso alla directory'], 500);
        }
    }

    private function containsFiles($contents){
        foreach($contents as $item){
            if($item['type'] === 'file'){
                return true;
            }
        }
        return false;
    }

    public function download($filename)
    {
        Log::info('Download method called');
        Log::info('Original filename: ' . $filename);

        // Sostituisci %5C con / nel percorso
        $decodedFilename = urldecode(str_replace('%5C', '/', $filename));
        Log::info('Decoded filename: ' . $decodedFilename);

        $filePath = $this->nasPath . '/' . $decodedFilename;
        Log::info('File path: ' . $filePath);

        try {
            if (file_exists($filePath)) {
                Log::info('File trovato e leggibile: ' . $decodedFilename);
                return response()->file($filePath, [
                    'Access-Control-Allow-Origin' => '*',
                ]);
            } else {
                Log::error('File non trovato o non leggibile: ' . $decodedFilename);
                return response()->json(['error' => 'File non trovato o non leggibile'], 403);
            }
        } catch (\Exception $e) {
            Log::error('Errore durante il download del file: ' . $e->getMessage());
            return response()->json(['error' => 'Errore durante il download del file'], 500);
        }
    }

    public function generateQrCode($path)
    {
        set_time_limit(0);

        $qrCodePath = storage_path('app/public/qrcodes/' . md5($path) . '.png');

        if (!file_exists($qrCodePath)) {
            QrCode::format('png')
                ->size(300)
                ->generate(url('/nas?path=' . $path), $qrCodePath);
        }

        $qrCodeUrl = asset('storage/qrcodes/' . md5($path) . '.png');

        Log::info('Url qr code: ' . $qrCodeUrl);

        return $qrCodeUrl;
    }

    public function checkPermissions()
    {
        $directories = [
            'C:\\nas_simulation\\public\\',
            'C:\\nas_simulation\\public\\1° Piano (manovie)',
            'C:\\nas_simulation\\public\\1° Piano (manovie)\\01029 - Pressa RFS COMEC',
        ];

        $results = [];

        foreach ($directories as $directory) {
            $results[$directory] = [
                'readable' => is_readable($directory),
                'writable' => is_writable($directory),
                'permissions' => decoct(fileperms($directory) & 0777),
            ];
        }

        return response()->json($results);
    }
}
