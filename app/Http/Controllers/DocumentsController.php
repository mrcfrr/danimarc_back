<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DocumentsController extends Controller
{
    private $networkPath;

    public function __construct() // Inizializza il percorso di rete (networkPath) con il percorso UNC(universal naming convenction)
    {
        // Percorso della condivisione di rete
        $this->networkPath = '\\\\192.168.1.157\\public\\';
    }
    public function index() // Chiama il metodo 'getFolderStructure' per ottenere la struttura della cartella a partire dalla radice e restituisce la struttura in json
    {
        $folderStructure = $this->getFolderStructure('');
        return response()->json($folderStructure);
    }



    public function getFolderStructure($directory)  // Esplora la directory specificata e costruisce un array contenente la struttura delle cartelle e file registrando i percorsi nel log
    {
        $structure = [];
        // Percorso completo concatenando 'networkPath' e 'directory'
        $fullPath = $this->networkPath . str_replace('/', '\\', $directory);

        // Log della directory
        Log::info('Tentativo di accesso alla rete: ' . $fullPath);


        // Se la directory esiste, esplora i file e le cartelle
        if(is_dir($fullPath)){
            $files = scandir($fullPath);

            foreach($files as $file){
                // Se il file è una cartella, aggiungi la cartella alla struttura
                if($file === '.' || $file === '..'){
                    continue;
                }

                // Costruisce il percorso completo
                $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;
                Log::info('Full path before encoding: ' . $fullPath);

                // Se il file è una cartella, chiama la funzione ricorsivamente
                if(is_dir($filePath)){
                    $structure[] = [
                        'type' => 'directory',
                        'name' => $file,
                        'path' => str_replace('+', '%20', urlencode(str_replace('\\', '/', $directory . '/' . $file))),
                        'contents' => $this->getFolderStructure($directory . '/' . $file),
                    ];
                    // Controlla se è un file
                } elseif(is_file($filePath)) {
                    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                    // Se il file ha una delle estensioni specificate, lo aggiunge alla struttura
                    if(in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'pdf'])){
                        $structure[] = [
                            'type' => 'file',
                            'name' => $file,
                            'path' => str_replace('+', '%20', urlencode(str_replace('\\', '/', $directory . '/' . $file))),
                        ];
                    }
                }
            }
            // Se il perscorso non è una directory valida, registra un errore nel log e ritorna una risposta json
        } else {
            Log::error('Directory non trovata: ' . $fullPath);
            return response()->json(['error' => 'Directory non trovata'], 404);
        }

        // Ritorna la struttura della cartella
        return $structure;
    }

    public function getDocument($path)
    {
        $decodedPath = urldecode($path);
        $networkPath = $this->networkPath . str_replace('/', '\\', $decodedPath);

        Log::info('Percorso richiesto: ' . $decodedPath);
        Log::info('Percorso completo al file: ' . $networkPath);

        if (file_exists($networkPath)) {
            Log::info('File trovato: ' . $networkPath);
            return response()->file($networkPath);
        } else {
            Log::error('File non trovato: ' . $networkPath);
            return response()->json(['error' => 'File non trovato'], 404);
        }
    }

    

    public function download($filename) // Scarica un file specifico, controlla se il file è leggibile e restituisce il contenuto del file come risposta
    {
        // Costruisce il percorso completo del file
        $filePath = $this->networkPath . str_replace('/', '\\', urldecode($filename));

        // Controlla se il file esiste o se è leggibile
        if(is_readable($filePath)){
            return response()->download($filePath);
        } else {
            return response()->json(['error' => 'File non trovato o non leggibile'], 403);
        }
    }
}
