<?php

namespace App\Http\Traits;

trait PdfTrait
{

    public function pdfPortrait($hashname, $filename)
    {
        $wkhtml = config('app.wkthmltopdf', 'wkhtmltopdf');
        return shell_exec('"' . $wkhtml . '" --enable-local-file-access -O portrait -s A4 -B 1.5cm -L 1cm -R 1cm -T 1.5cm  ' . storage_path('app/public/tempo/') . $hashname . '.html ' . storage_path('app/public/downloads/') . $filename);
    }

    public function pdfPortraits($hashname, $filename)
    {
        $wkhtml = config('app.wkthmltopdf', 'wkhtmltopdf');
        return shell_exec('"' . $wkhtml . '" --enable-local-file-access -O portrait -s A4 -B 1.5cm -L 1cm -R 1cm -T 1.5cm --footer-center ---Hal.[page]/[topage]--- --footer-font-size 8 --footer-font-name "Segoe UI"  ' . storage_path('app/public/tempo/') . $hashname . '.html ' . storage_path('app/public/downloads/') . $filename);
    }

    public function pdfLandscape($hashname, $filename)
    {
        // Path untuk file input dan output
        $htmlPath = storage_path('app/public/tempo/' . $hashname . '.html');
        $pdfPath = storage_path('app/public/downloads/' . $filename);

        // Coba metode Windows-friendly
        if (PHP_OS === 'WINNT') {
            // Gunakan path dengan double quote untuk menangani spasi di Windows
            $wkhtml = config('app.wkthmltopdf', 'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe');

            // Buat command dengan format Windows-friendly
            $command = 'powershell -command "& \'' . $wkhtml . '\' --enable-local-file-access -O landscape -s A4 -B 1.5cm -L 1cm -R 1cm -T 1.5cm \'--footer-center\' \'---Hal.[page]/[topage]---\' \'--footer-font-size\' \'8\' \'--footer-font-name\' \'\"Segoe UI\"\' \'' . $htmlPath . '\' \'' . $pdfPath . '\'"';
        } else {
            // Linux method
            $wkhtml = config('app.wkthmltopdf', 'wkhtmltopdf');
            $command = '"' . $wkhtml . '" --enable-local-file-access -O landscape -s A4 -B 1.5cm -L 1cm -R 1cm -T 1.5cm --footer-center ---Hal.[page]/[topage]--- --footer-font-size 8 --footer-font-name "Segoe UI" "' . $htmlPath . '" "' . $pdfPath . '" 2>&1';
        }

        // Log command untuk debugging
        file_put_contents(storage_path('logs/wkhtmltopdf_command.log'), $command);

        // Execute command
        $output = shell_exec($command);

        // Log output untuk debugging
        file_put_contents(storage_path('logs/wkhtmltopdf_output.log'), $output ?? 'No output');

        // Manual file copy sebagai fallback (jika shell_exec gagal)
        if (!file_exists($pdfPath) && file_exists($htmlPath)) {
            // Jika PDF tidak dibuat, salin HTML saja sebagai fallback
            copy($htmlPath, $pdfPath);
        }

        return $output;
    }

    public function pdfCustomHeadFoot($hashname, $filename, $orientation, $size, $param)
    {
        $wkhtml = config('app.wkthmltopdf', 'wkhtmltopdf');
        return shell_exec('"' . $wkhtml . '" --enable-local-file-access -O ' . $orientation . ' -s ' . $size . ' ' . $param . ' --header-html ' . storage_path('app/public/tempo/header_') . $hashname . '.html --footer-html ' . storage_path('app/public/tempo/footer_') . $hashname . '.html ' . storage_path('app/public/tempo/body_') . $hashname . '.html ' . storage_path('app/public/downloads/') . $filename);
    }
}
