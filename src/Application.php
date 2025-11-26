<?php

namespace Devhammed\Photoshop;

use Devhammed\Photoshop\Attributes\Getter;
use Devhammed\Photoshop\Attributes\Setter;
use Devhammed\Photoshop\Enums\DialogModes;
use Devhammed\Photoshop\Exceptions\LockException;
use Devhammed\Photoshop\Exceptions\ApplicationException;

/**
 * @property ?Document $activeDocument The frontmost document.
 * @property DialogModes $displayDialogs The dialog mode for the document, which indicates whether or not Photoshop
 *     displays dialogs when the script runs.
 * @property-read string $build The build number of Adobe Photoshop application.
 * @property-read string $currentTool Name of the current tool.
 * @property-read string $locale The language locale of the application.
 * @property-read integer $freeMemory The amount of unused memory available to Photoshop.
 * @property-read string $name The application name.
 * @property-read string $windowsFileTypes A list of the image file extensions Photoshop can open.
 * @property-read string $scriptingVersion The version of the Scripting interface.
 * @property-read string $scriptingBuildDate The build date of the scripting interface.
 * @method void bringToFront() Makes Photoshop the active application.
 * @method void beep() Alerts the user.
 */
class Application extends Model
{
    protected string $version;

    protected mixed $lockFile;

    protected string $comId;

    protected array $attributes = [
        'build',
        'currentTool',
        'locale',
        'freeMemory',
        'name',
        'windowsFileTypes',
        'displayDialogs',
        'scriptingVersion',
        'scriptingBuildDate',
    ];

    protected array $fillable = [
        'displayDialogs',
    ];

    protected array $methods = [
        'bringToFront',
        'beep',
    ];

    public function __construct(string $version, int $blockFor = 60)
    {
        $this->version = $version;

        $this->comId = match ($this->version) {
            '2025' => '190',
            '2024' => '180',
            '2023' => '170',
            '2022' => '160',
            '2021' => '150',
            '2020' => '140',
            '2019' => '130',
            '2018' => '120',
            '2017' => '110',
            default => throw new ApplicationException('Invalid Photoshop version.'),
        };

        $lockFile = sys_get_temp_dir().DIRECTORY_SEPARATOR."photoshop_{$this->version}_lock";

        $this->lockFile = fopen($lockFile, 'w+');

        if ( ! $this->lockFile) {
            throw new LockException("Cannot open Photoshop lock file.");
        }

        $waited = 0;

        $acquired = false;

        while ($waited < $blockFor) {
            if (flock($this->lockFile, LOCK_EX | LOCK_NB)) {
                $acquired = true;
                break;
            }

            usleep(100_000);

            $waited += 0.1;
        }

        if ( ! $acquired) {
            throw new LockException("Cannot acquire Photoshop lock within {$blockFor} seconds.");
        }

        parent::__construct($this, 'app');
    }

    public function __destruct()
    {
        if ($this->lockFile) {
            flock($this->lockFile, LOCK_UN);
            fclose($this->lockFile);
        }
    }

    #[Getter('activeDocument')]
    #[Setter('activeDocument')]
    protected function activeDocument(?Document $document = null): Document
    {
        if ($document === null) {
            $name = $this->execute('app.activeDocument.name');

            return new Document($this, "app.documents.getByName('{$name}')");
        }

        $this->execute("(function() { app.activeDocument = {$document} })()");

        return $document;
    }

    public function execute(string $jsxExpression): mixed
    {
        try {
            $tmpFile = tempnam(sys_get_temp_dir(), 'psjs').'.jsx';

            $outputFile = tempnam(sys_get_temp_dir(), 'psout').'.json';

            $json2Path = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'json2.js';

            $wrappedJSX = <<<JSX
                //@target photoshop

                #include "{$json2Path}"

                (function() {
                    var output = {};

                    try {
                        var result = ($jsxExpression);

                        if (typeof result !== 'undefined') {
                            output.result = result;
                        } else {
                            output.result = null;
                        }
                    } catch(e) {
                        output.error = String(e);
                    }

                    var f = new File("{$outputFile}");
                    f.open("w");
                    f.write(JSON.stringify(output));
                    f.close();
                })();
            JSX;

            file_put_contents($tmpFile, $wrappedJSX);

            if (strtoupper(substr(PHP_OS, 0, 3)) === "WIN") {
                $cmd = [
                    'powershell',
                    '-NoProfile',
                    '-ExecutionPolicy', 'Bypass',
                    '-Command',
                    <<<POWERSHELL
                        try {
                            \$app = New-Object -ComObject Photoshop.Application.$this->comId
                            \$app.DoJavaScriptFile("$tmpFile")
                            exit 0
                        } catch {
                            Write-Error \$_.Exception.Message
                            exit 1
                        }
                    POWERSHELL,
                ];
            } elseif (strtoupper(substr(PHP_OS, 0, 3)) === "DAR") {
                $cmd = [
                    'osascript',
                    '-e', "tell application \"Adobe Photoshop {$this->version}\"",
                    '-e', 'activate',
                    '-e', "do javascript of file \"$tmpFile\"",
                    '-e', 'end tell',
                ];
            } else {
                throw new ApplicationException('Unsupported operating system.');
            }

            $sh = shell_exec(implode(' ', array_map('escapeshellarg', $cmd)));

            if ($sh === false) {
                throw new ApplicationException("Cannot execute Photoshop script.");
            }

            $json = file_exists($outputFile) ? file_get_contents($outputFile) : null;

            if (empty($json)) {
                throw new ApplicationException("Empty result from Photoshop.");
            }

            $data = json_decode($json, true);

            if ( ! is_array($data)) {
                $type = gettype($data);

                throw new ApplicationException("Invalid result from Photoshop, expected array, got {$type}.");
            }

            if (isset($data['error'])) {
                throw new ApplicationException($data['error']);
            }

            return $data['result'] ?? null;
        } finally {
            @unlink($tmpFile);
            @unlink($outputFile);
        }
    }
}
