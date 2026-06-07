<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Normaliza cualquier archivo de entrada (PDF, DOCX, imagen) a un unico PDF.
 *
 *  - PDF: se usa tal cual (solo se copia).
 *  - DOCX / DOC / ODT / imagenes: se convierten con LibreOffice headless.
 *
 * Todo el flujo de firma posterior trabaja exclusivamente sobre el PDF
 * resultante, asi que el resto de la app no necesita saber el formato origen.
 */
class PdfConversionService
{
    /**
     * Convierte $sourceAbsPath a un PDF dentro de $outputDir.
     * Devuelve la ruta absoluta del PDF generado.
     *
     * @param  string  $sourceAbsPath  Ruta absoluta del archivo origen.
     * @param  string  $format         Extension en minusculas (pdf, docx, jpg...).
     * @param  string  $outputDir      Directorio absoluto donde dejar el PDF.
     */
    public function normalizeToPdf(string $sourceAbsPath, string $format, string $outputDir): string
    {
        if (! is_file($sourceAbsPath)) {
            throw new RuntimeException("El archivo origen no existe: {$sourceAbsPath}");
        }

        if (! is_dir($outputDir) && ! mkdir($outputDir, 0775, true) && ! is_dir($outputDir)) {
            throw new RuntimeException("No se pudo crear el directorio de salida: {$outputDir}");
        }

        $target = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . 'normalized.pdf';

        // Caso simple: ya es PDF -> copiar.
        if ($format === 'pdf') {
            if (! copy($sourceAbsPath, $target)) {
                throw new RuntimeException('No se pudo copiar el PDF de origen.');
            }

            return $target;
        }

        // El resto (docx, imagenes...) pasa por LibreOffice.
        return $this->convertWithLibreOffice($sourceAbsPath, $outputDir, $target);
    }

    /**
     * Ejecuta `soffice --headless --convert-to pdf`.
     */
    protected function convertWithLibreOffice(string $sourceAbsPath, string $outputDir, string $target): string
    {
        $binary = $this->resolveSofficeBinary();

        // Perfil de usuario aislado: evita el bloqueo "LibreOffice ya esta en
        // ejecucion" y permite conversiones concurrentes.
        $profileDir = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . '.lo_profile';
        $profileUri = 'file:///' . str_replace('\\', '/', ltrim($profileDir, '/'));

        $process = new Process([
            $binary,
            '--headless',
            '--norestore',
            '--nologo',
            '-env:UserInstallation=' . $profileUri,
            '--convert-to', 'pdf',
            '--outdir', $outputDir,
            $sourceAbsPath,
        ]);
        $process->setTimeout((float) config('docsigner.convert_timeout', 120));

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new RuntimeException(
                'LibreOffice fallo al convertir: ' . trim($process->getErrorOutput() ?: $process->getOutput()),
                previous: $e,
            );
        }

        // LibreOffice nombra la salida como <basename>.pdf en el outdir.
        $producedName = pathinfo($sourceAbsPath, PATHINFO_FILENAME) . '.pdf';
        $produced = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $producedName;

        if (! is_file($produced)) {
            throw new RuntimeException('LibreOffice no genero el PDF esperado.');
        }

        // Renombrar a nombre estable "normalized.pdf".
        if ($produced !== $target) {
            @unlink($target);
            if (! rename($produced, $target)) {
                throw new RuntimeException('No se pudo renombrar el PDF generado.');
            }
        }

        return $target;
    }

    /**
     * Resuelve y valida el binario de LibreOffice.
     */
    protected function resolveSofficeBinary(): string
    {
        $configured = trim((string) config('docsigner.soffice_path', ''));

        if ($configured !== '') {
            if (! is_file($configured)) {
                throw new RuntimeException(
                    "SOFFICE_PATH apunta a un archivo inexistente: {$configured}"
                );
            }

            return $configured;
        }

        // Sin ruta configurada: confiamos en que "soffice" este en el PATH.
        // En Ubuntu basta con instalarlo; en Windows conviene fijar SOFFICE_PATH.
        return 'soffice';
    }
}
