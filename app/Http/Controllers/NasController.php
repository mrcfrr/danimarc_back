<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class NasController extends Controller
{
    private $nasPath = 'C:\\nas_simulation\\public';

    /* ************************************************************************************************************************** */

    public function index(Request $request)
    {
        /* $this->cleanupQrCodes(); */
        $folderStructure = $this->getFolderStructure('');

        return response()->json($folderStructure);
    }

    /* ************************************************************************************************************************** */

    public function getFolderStructure($directory)
    {
        $structure = [];
        $fullPath = $this->nasPath . ($directory ? '\\' . $directory : '');

        try {
            Log::info('Accessing directory: ' . $fullPath);
            if (!file_exists($fullPath)) {
                Log::error('Directory does not exist: ' . $fullPath);
                throw new \Exception('Directory does not exist: ' . $fullPath);
            }

            $files = scandir($fullPath);

            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === '' || $file[0] === '.') {
                    continue;
                }

                $filePath = $fullPath . '\\' . $file;
                $encodedPath = rawurlencode($directory . '\\' . $file);

                if (is_dir($filePath)) {
                    $contents = $this->getFolderStructure($directory . '\\' . $file);
                    $structure[] = [
                        'type' => 'directory',
                        'name' => $file,
                        'path' => $encodedPath,
                        'contents' => $contents
                    ];
                } else {
                    $structure[] = [
                        'type' => 'file',
                        'name' => $file,
                        'path' => $encodedPath,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Errore durante l\'accesso alla directory: ' . $e->getMessage());
            return ['error' => 'Errore durante l\'accesso alla directory', 'exception' => $e->getMessage()];
        }

        return $structure;
    }

    /* ************************************************************************************************************************** */


    public function download($filename)
    {
        Log::info('Download method called');
        Log::info('Original filename: ' . $filename);

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

    /* ************************************************************************************************************************** */

    public function fetchData(Request $request)
    {
        try {
            // Ottieni il percorso dal request
            $path = $request->input('path', '');

            /* // Controlla la struttura delle cartelle e rimuovi i QR code obsoleti
            $this->cleanupQrCodes(); */

            // Ottieni la struttura delle cartelle
            $data = $this->getFolderStructure($path);

            return response()->json($data);

        } catch (\Exception $e) {
            Log::error('Errore durante il recupero dei dati: ' . $e->getMessage());
            return response()->json(['error' => 'Errore durante il recupero dei dati'], 500);
        }
    }

    /* ************************************************************************************************************************** */

    /* public function generateQrCodeOnRequest(Request $request)
    {
        $folderPath = $request->input('path');
        $cleanPath = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|', ' '], '_', $folderPath);

        // Imposta il percorso per il file QR code
        $qrCodePath = 'qrcodes/' . $cleanPath . '.png';
        $fullPath = storage_path('app/public/' . $qrCodePath);

        // Verifica se la directory esiste, altrimenti creala
        if (!file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        // Genera il QR code solo se non esiste già
        if (!file_exists($fullPath)) {
            QrCode::format('png')->size(200)->generate($folderPath, $fullPath);
        }

        // Restituisci il percorso del QR code come stringa, non come array
        return '/storage/' . $qrCodePath;
    }


    public function generateQRCode(Request $request)
    {
        $path = $request->input('path');
        $qrCodeUrl = $this->generateQrCodeOnRequest(new Request(['path' => $path]));

        // Restituisci il percorso del QR code direttamente
        return response()->json(['qrCodeUrl' => $qrCodeUrl]);
    } */

    /* private function cleanupQrCodes()
    {
        $qrCodesPath = storage_path('app/public/qrcodes');
        $nasPath = 'C:\\nas_simulation\\public';

        // Ottieni tutti i file QR code
        $qrCodes = File::allFiles($qrCodesPath);

        foreach ($qrCodes as $qrCode) {
            // Decodifica il nome della cartella dal nome del file QR code
            $encodedFolderPath = pathinfo($qrCode->getFilename(), PATHINFO_FILENAME);
            $decodedFolderPath = str_replace('_', DIRECTORY_SEPARATOR, $encodedFolderPath);

            // Controlla se la cartella esiste ancora
            if (!File::exists($nasPath . DIRECTORY_SEPARATOR . $decodedFolderPath)) {
                // Se la cartella non esiste più, elimina il QR code
                File::delete($qrCode->getPathname());
                Log::info("QR code eliminato: {$qrCode->getFilename()}");
            }
        }
    } */

    /* ************************************************************************************************************************** */

    /* private function collectValidQrCodes($directory, &$validQrCodes)
    {
        $fullPath = $this->nasPath . ($directory ? '\\' . $directory : '');
        if (!file_exists($fullPath)) {
            return;
        }

        $files = scandir($fullPath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '' || $file[0] === '.') {
                continue;
            }

            $filePath = $fullPath . '\\' . $file;
            $relativePath = ($directory ? $directory . '\\' : '') . $file;

            if (is_dir($filePath)) {
                $hasFiles = count(glob("$filePath/*")) > 0;
                if ($hasFiles) {
                    $validQrCodes[] = $file . '.png';
                }
                $this->collectValidQrCodes($relativePath, $validQrCodes);
            }
        }
    }

    private function containsFiles($contents)
    {
        if (!is_array($contents)) {
            return false;
        }

        foreach ($contents as $content) {
            if (isset($content['type']) && $content['type'] === 'file') {
                return true;
            }
            if (isset($content['type']) && $content['type'] === 'directory' && $this->containsFiles($content['contents'])) {
                return true;
            }
        }
        return false;
    }

    private function generateQrCodeUrl($encodedPath)
    {
        return url('/nas/fetch-data?path=' . $encodedPath);
    }

    private function getDataForPath($path)
    {
        return [
            'name' => 'Example Directory',
            'contents' => [
                ['name' => 'File 1.txt', 'type' => 'file'],
                ['name' => 'File 2.txt', 'type' => 'file'],
                ['name' => 'Subdirectory', 'type' => 'directory'],
            ],
        ];
    } */
}
