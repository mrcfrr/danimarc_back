<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DocumentsController extends Controller
{
    private $networkPath;

    public function __construct() // Inizializza il percorso di rete (networkPath) con il percorso UNC(universal naming convenction)
    {
        // Percorso della condivisione di rete
        $this->networkPath = '\\\\SRVNAS\\public\\';
    }



    public function index(Request $request) // Chiama il metodo 'getFolderStructure' per ottenere la struttura della cartella a partire dalla radice e restituisce la struttura in json
    {
        $folderStructure = $this->getFolderStructure('');
        return response()->json($folderStructure);
    }



    public function getFolderStructure($directory)  // Esplora la directory specificata e costruisce un array contenente la struttura delle cartelle e file registrando i percorsi nel log
    {

        $structure = [];
        $processedDirectory = strtolower(str_replace(['/', '%20'], ['\\', ' '], $directory));
        $networkPathLower = strtolower($this->networkPath);

        // Percorso completo concatenando 'networkPath' e 'directory'
        $fullPath = $networkPathLower . $processedDirectory;

        // Log della directory
        Log::info('Tentativo di accesso alla rete: ' . $fullPath);
        Log::info('Processed Directory: ' . $processedDirectory);
        Log::info('Network Path: ' . $networkPathLower);
        Log::info('Directory Parameter: ' . $directory);

        $fullPath = rawurldecode($fullPath);
        Log::info('Full path after decoding: ' . $fullPath );

        // Se la directory esiste, esplora i file e le cartelle
        if($fullPath && is_dir($fullPath)){
            $files = scandir($fullPath);
            $hasFiles = false;

            foreach($files as $file){
                // Se il file è una cartella, aggiungi la cartella alla struttura
                if($file === '.' || $file === '..'){
                    continue;
                }

                // Costruisce il percorso completo
                $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;
                Log::info('Full path before encoding: ' . $fullPath);

                $encodedPath = rawurlencode($directory . '/' . $file);
                Log::info('Encoded path: ' . $encodedPath);


                // Se il file è una cartella, chiama la funzione ricorsivamente
                if(is_dir($filePath)){
                    Log::info('Processing directory: ' . $filePath);

                    $folderContents = $this->getFolderStructure($directory . '/' . $file);
                    $structure[] = [
                        'type' => 'directory',
                        'name' => $file,
                        'path' => $encodedPath,
                        'contents' => $folderContents,
                    ];

                    if(!empty($folderContents)){
                        $structure[count($structure) - 1]['qr_code'] = $this->generateQrCode($encodedPath);
                    }

                    // Controlla se è un file
                } elseif(is_file($filePath)) {

                    $hasFiles = true;
                    $extension = pathinfo($filePath, PATHINFO_EXTENSION);

                    // Se il file ha una delle estensioni specificate, lo aggiunge alla struttura
                    if(in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'pdf'])){
                        Log::info('Adding file: ' . $filePath);
                        $structure[] = [
                            'type' => 'file',
                            'name' => $file,
                            'path' => $encodedPath,
                        ];
                    } else {
                        Log::info('File extension not supported: ' . $filePath);
                    }
                }
            }

            if($hasFiles && empty($directory)){
                $structure['qr_code'] = $this->generateQrCode($directory);
            }

            // Se il perscorso non è una directory valida, registra un errore nel log e ritorna una risposta json
        } else {
            Log::error('Directory non trovata: ' . $fullPath);
            return response()->json(['error' => 'Directory non trovata'], 404);
        }

        // Ritorna la struttura della cartella
        return $structure;
    }



    public function download($filename) // Scarica un file specifico, controlla se il file è leggibile e restituisce il contenuto del file come risposta
    {

        Log::info('Download method called');
        Log::info('Original filename: ' . $filename);

        $decodedFilename = urldecode($filename);
        Log::info('Decoded filename: ' . $decodedFilename);

        // Costruisce il percorso completo del file
        $filePath = $this->networkPath . str_replace(['/', '%20'], ['\\', ' '], $decodedFilename);

        Log::info('Percorso del file richiesto: ' . $filename);
        Log::info('Percorso del file decodificato: ' . $decodedFilename);
        Log::info('Percorso del file finale: ' . $filePath);

        // Controlla se il file esiste o se è leggibile
        if(is_readable($filePath)){
            Log::info('File trovato e leggibile: ' . $filePath);
            return response()->download($filePath);
        } else {
            Log::error('File non trovato o non leggibile: ' . $filePath);
            return response()->json(['error' => 'File non trovato o non leggibile'], 403);
        }
    }


    public function generateQrCode($path)
    {
        set_time_limit(0);

        $qrCodePath = storage_path('app/public/qrcodes/' . md5($path) . '.png');

        if(!file_exists($qrCodePath)){
            QrCode::format('png')
                ->size(300)
                ->generate(url('/documents?path=' . $path), $qrCodePath);
        }

        $qrCodeUrl = asset('storage/qrcodes/' . md5($path) . '.png');

        Log::info('Url qr code: ' . $qrCodeUrl);

        return $qrCodeUrl;
    }


    public function checkPermissions()
    {
        $directories = [
            '\\\\127.0.0.1\\sharing_test\\',
            '\\\\127.0.0.1\\sharing_test\\1° Piano (manovie)',
            '\\\\127.0.0.1\\sharing_test\\1° Piano (manovie)\\01029 - Pressa RFS COMEC',
            // Aggiungi altri percorsi come necessario
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
