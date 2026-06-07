<?php

namespace App\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Maneja los ficheros de documentos sobre el disco configurado en
 * `docsigner.disk` (local o s3/DO Spaces).
 *
 * El pipeline de conversion (LibreOffice) y firma (pyHanko) necesita rutas
 * de fichero LOCALES; el driver s3 no las ofrece. Por eso, cuando el disco
 * es remoto, materializamos los objetos en un directorio temporal, operamos
 * en local y subimos el resultado de vuelta. Patron tomado de OlePyme.
 */
trait HandlesDocumentFiles
{
    /** Disco de almacenamiento de documentos. */
    protected function docDisk(): string
    {
        return config('docsigner.disk', 'local');
    }

    /** Crea un directorio temporal local unico para trabajar. */
    protected function tempWorkDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'firmadoc_'.Str::random(16);

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("No se pudo crear el directorio temporal: {$dir}");
        }

        return $dir;
    }

    /** Descarga un objeto del disco a una ruta local (por streams). */
    protected function pullToLocal(string $path, string $localAbs): void
    {
        $disk = Storage::disk($this->docDisk());

        if (! $disk->exists($path)) {
            throw new RuntimeException("No existe en el almacenamiento: {$path}");
        }

        $in = $disk->readStream($path);
        $out = fopen($localAbs, 'w');

        if ($in === false || $out === false) {
            throw new RuntimeException("No se pudo leer/escribir el fichero: {$path}");
        }

        stream_copy_to_stream($in, $out);
        fclose($out);

        if (is_resource($in)) {
            fclose($in);
        }
    }

    /** Sube un fichero local al disco (por streams). */
    protected function pushFromLocal(string $localAbs, string $path): void
    {
        $in = fopen($localAbs, 'r');

        if ($in === false) {
            throw new RuntimeException("No se pudo abrir el fichero local: {$localAbs}");
        }

        Storage::disk($this->docDisk())->writeStream($path, $in);

        if (is_resource($in)) {
            fclose($in);
        }
    }

    /** Borra recursivamente un directorio temporal local. */
    protected function cleanupTemp(?string $dir): void
    {
        if ($dir && is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }
}
