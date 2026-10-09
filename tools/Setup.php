<?php
/***************************************************************************************************************************/
/**
    © Copyright 2023-2026, [Little Green Viper Software Development LLC](https://littlegreenviper.com)

    LICENSE:

    MIT License

    Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation
    files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy,
    modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
    Software is furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
    OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
    IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
    CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

    [Little Green Viper Software Development LLC](https://littlegreenviper.com)
*/
/***************************************************************************************************************************/
/**
    \file
    \brief These are shared Composer, download, and extraction helpers for the demo and installer.

    Downloads require HTTPS and stream to disk. Composer is checked against its official SHA-256 checksum,
    and runs with scripts and plugins disabled. ZIP extraction writes only the expected GeoJSON file.
*/

declare(strict_types=1);

/***************************************************************************************************************************/
/** \brief This prepares a Composer application and its boundary data. */
class LGV_TZ_Lookup_Setup {
    const PACKAGE = 'littlegreenviper/lgv_tz_lookup'; ///< The package installed by both command-line tools.

    /***********************************************************************************************************************/
    /** \brief Download an HTTPS resource to a local file. \throws RuntimeException if preparation fails. */
    public static function download(string $url,           ///< The HTTPS resource to download.
                        string $destination     ///< The local filename to create in the demo's working directory.
                        ): void {
        $output = fopen($destination, 'wb');
        if (false === $output) {
            throw new RuntimeException('Could not write '.$destination);
        }
        $request = curl_init($url);
        try {
            curl_setopt_array($request, [
                CURLOPT_FILE => $output,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_TIMEOUT => 1800,
                CURLOPT_FAILONERROR => true,
                CURLOPT_USERAGENT => 'LGV-TZ-Lookup-Setup',
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            ]);
            if (!curl_exec($request)) {
                throw new RuntimeException('Download failed: '.curl_error($request).' ('.$url.')');
            }
        } finally {
            fclose($output);
            unset($request);
        }
    }

    /***********************************************************************************************************************/
    /** \brief Extract exactly one GeoJSON entry without using archive paths. \throws RuntimeException if preparation fails. */
    public static function extract(string $archive,        ///< The downloaded boundary ZIP file.
                        string $destination     ///< The local GeoJSON filename to create.
                        ): void {
        $zip = new ZipArchive();
        if (true !== $zip->open($archive)) {
            throw new RuntimeException('Could not open the boundary archive.');
        }
        try {
            $entries = [];
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = $zip->getNameIndex($i);
                if (preg_match('/\.(?:json|geojson)$/i', $name)) {
                    $entries[] = $name;
                }
            }
            if (count($entries) !== 1) {
                throw new RuntimeException('Expected one GeoJSON file in the boundary archive.');
            }
            // Stream into our own filename instead of extracting arbitrary archive paths or reading the file into memory.
            $input = $zip->getStream($entries[0]);
            $output = fopen($destination, 'wb');
            if (false === $input || false === $output) {
                if (is_resource($input)) { fclose($input); }
                if (is_resource($output)) { fclose($output); }
                throw new RuntimeException('Could not extract the boundary file.');
            }
            try {
                $bytes = stream_copy_to_stream($input, $output);
                $entry = $zip->statName($entries[0]);
                if (false === $bytes || $bytes === 0 || $bytes !== $entry['size']) {
                    throw new RuntimeException('The extracted boundary file is empty or incomplete.');
                }
            } finally {
                fclose($input);
                fclose($output);
            }
        } finally {
            $zip->close();
        }
    }

    /***********************************************************************************************************************/
    /** \brief Install the library and optional loader, then require its Composer autoloader. \throws RuntimeException if preparation fails. */
    public static function install(string $directory, string $driver, string $packagePath = '', string $version = '^1.4'): void {
        echo "Installing a Composer application...\n";
        $composer = $directory.'/composer.phar';
        self::download('https://getcomposer.org/download/latest-stable/composer.phar', $composer);
        self::download('https://getcomposer.org/download/latest-stable/composer.phar.sha256sum', $directory.'/composer.sha256');
        $checksum = preg_split('/\s+/', trim(file_get_contents($directory.'/composer.sha256')))[0];
        if (!hash_equals($checksum, hash_file('sha256', $composer))) {
            throw new RuntimeException('Composer download checksum did not match.');
        }
        $repository = ['type' => 'vcs', 'url' => 'https://github.com/LittleGreenViper/LGV_TZ_Lookup.git'];
        if ($packagePath !== '') {
            $packagePath = realpath($packagePath);
            if (false === $packagePath || !is_file($packagePath.'/composer.json')) {
                throw new RuntimeException('The package path must point to a checkout containing composer.json.');
            }
            $version = 'dev-local';
            $repository = ['type' => 'path', 'url' => $packagePath,
                'options' => ['symlink' => false, 'versions' => [self::PACKAGE => $version]]];
        }
        file_put_contents($directory.'/composer.json', json_encode([
            'name' => 'littlegreenviper/tz-lookup-application',
            'repositories' => [$repository],
            'require' => [self::PACKAGE => $version, 'ext-pdo_'.$driver => '*', 'salsify/json-streaming-parser' => '8.3.*'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
        putenv('COMPOSER_HOME='.$directory.'/composer-home');
        putenv('COMPOSER_CACHE_DIR='.$directory.'/composer-cache');
        putenv('COMPOSER_ROOT_VERSION=1.0.0');
        $process = proc_open([
            PHP_BINARY, $composer, 'install', '--working-dir='.$directory, '--no-dev', '--no-interaction',
            '--no-scripts', '--no-plugins', '--prefer-dist', '--no-progress', '--no-ansi', '--quiet',
        ], [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);
        if (false === $process) {
            throw new RuntimeException('Could not start Composer.');
        }
        try {
            $status = proc_close($process);
            $process = null;
            if ($status !== 0) {
                throw new RuntimeException('Composer installation failed (exit '.$status.').');
            }
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
        require $directory.'/vendor/autoload.php';
        echo 'Installed '.self::PACKAGE.' '.Composer\InstalledVersions::getPrettyVersion(self::PACKAGE).".\n";
    }
}
