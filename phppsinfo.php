<?php

class PhpPsInfo
{
    protected $login;
    protected $password;

    const DEFAULT_PASSWORD = 'prestashop';
    const DEFAULT_LOGIN = 'prestashop';

    const TYPE_OK = true;
    const TYPE_ERROR = false;
    const TYPE_WARNING = null;

    const TYPE_SUCCESS_CLASS = 'table-success';
    const TYPE_ERROR_CLASS = 'table-danger';
    const TYPE_INFO_CLASS = 'table-info';
    const TYPE_WARNING_CLASS = 'table-warning';

    protected $requirements = [
        'versions' => [
            'php' => '7.1',
            'mysql' => '5.5',
            'prestashop' => '8.0',
        ],
        'extensions' => [
            'bcmath' => false,
            'curl' => true,
            'dom' => true,
            'fileinfo' => true,
            'gd' => true,
            'iconv' => true,
            'imagick' => false,
            'intl' => true,
            'json' => true,
            'mbstring' => true,
            'memcache' => false,
            'memcached' => false,
            'openssl' => true,
            'pdo_mysql' => true,
            'simplexml' => true,
            'zip' => true,
        ],
        'config' => [
            'allow_url_fopen' => true,
            'expose_php' => false,
            'file_uploads' => true,
            'max_input_vars' => 1000,
            'memory_limit' => '64M',
            'post_max_size' => '16M',
            'register_argc_argv' => false,
            'set_time_limit' => true,
            'short_open_tag' => false,
            'upload_max_filesize' => '4M',
        ],
        'directories' => [
            'cache_dir' => 'var/cache',
            'log_dir' => 'var/logs',
            'img_dir' => 'img',
            'mails_dir' => 'mails',
            'module_dir' => 'modules',
            'translations_dir' => 'translations',
            'customizable_products_dir' => 'upload',
            'virtual_products_dir' => 'download',
            'override_dir' => 'override',
            'config_sf2_dir' => 'app/config',
            'translations_sf2' => 'app/Resources/translations',
        ],
        'apache_modules' => [
            'mod_alias' => false,
            'mod_env' => false,
            'mod_headers' => false,
            'mod_rewrite' => false,
        ],
    ];

    protected $recommended = [
        'versions' => [
            'php' => '8.1',
            'mysql' => '8.0',
            'prestashop' => '9.0',
        ],
        'extensions' => [
            'bcmath' => true,
            'curl' => true,
            'dom' => true,
            'fileinfo' => true,
            'gd' => true,
            'iconv' => true,
            'imagick' => true,
            'intl' => true,
            'json' => true,
            'mbstring' => true,
            'memcache' => false,
            'memcached' => true,
            'openssl' => true,
            'pdo_mysql' => true,
            'simplexml' => true,
            'zip' => true,
        ],
        'config' => [
            'allow_url_fopen' => true,
            'expose_php' => false,
            'file_uploads' => true,
            'max_input_vars' => 5000,
            'memory_limit' => '256M',
            'post_max_size' => '128M',
            'register_argc_argv' => false,
            'set_time_limit' => true,
            'short_open_tag' => false,
            'upload_max_filesize' => '128M',
        ],
        'apache_modules' => [
            'mod_alias' => false,
            'mod_env' => true,
            'mod_headers' => true,
            'mod_rewrite' => true,
        ],
    ];

    /**
     * Set up login and password with parameter or
     * you can set server env vars:
     *  - PS_INFO_LOGIN
     *  - PS_INFO_PASSWORD
     *
     * @param string $login    Login
     * @param string $password Password
     */
    public function __construct($login = self::DEFAULT_LOGIN, $password = self::DEFAULT_PASSWORD)
    {
        $this->login = $_SERVER['PS_INFO_LOGIN'] ?? $login;
        $this->password = $_SERVER['PS_INFO_PASSWORD'] ?? $password;

        // Detect installed PrestaShop and set PHP required/recommended accordingly
        if (method_exists($this, 'getPrestaShopVersion')) {
            $psv = $this->getPrestaShopVersion();
            if (is_string($psv) && preg_match('~^\d+\.\d+(\.\d+)?$~', $psv)) {
                $php = $this->getPhpSupportForPs($psv);
                if ($php) {
                    $this->requirements['versions']['php'] = $php['min'];
                    $this->recommended['versions']['php'] = $php['rec'];
                }
            }
        }

    }

    /**
     * Check authentication if not in cli and have a login
     */
    public function checkAuth()
    {
        if (PHP_SAPI === 'cli') { return; }
        if (empty($this->login) || empty($this->password)) {
            header('HTTP/1.1 403 Forbidden'); exit(403);
        }
        $u = $_SERVER['PHP_AUTH_USER'] ?? '';
        $p = $_SERVER['PHP_AUTH_PW'] ?? '';
        if (!hash_equals($this->login, $u) || !hash_equals($this->password, $p)) {
            header('WWW-Authenticate: Basic realm="Presta Info"');
            header('HTTP/1.0 401 Unauthorized'); echo '401 Unauthorized'; exit(401);
        }
    }

    /**
     * Get current PrestaShop version
     * Order: via DB, via settings.inc.php, via defines.inc.php else "Unknown"
     */
    public function getPrestaShopVersion()
    {
        //return '1.6.1.23';

        // 1) Via DB (PS 1.6/1.7/8)
        $db =  $host = $name = $user = $pass = $prefix = null;

        // PS >= 1.7 / 8: app/config/parameters.php
        $parametersFile = getcwd() . '/app/config/parameters.php';
        if (file_exists($parametersFile)) {
            $params = include $parametersFile;
            if (is_array($params) && isset($params['parameters'])) {
                $p = $params['parameters'];
                $host = $p['database_host'] ?? null;
                $name = $p['database_name'] ?? null;
                $user = $p['database_user'] ?? null;
                $pass = $p['database_password'] ?? null;
                $prefix = $p['database_prefix'] ?? 'ps_';
            }
        }

        // PS 1.6: config/settings.inc.php
        if (!$host && file_exists(getcwd() . '/config/settings.inc.php')) {
            include getcwd() . '/config/settings.inc.php';
            if (defined('_DB_SERVER_')) {
                $host = _DB_SERVER_;
                $name = _DB_NAME_;
                $user = _DB_USER_;
                $pass = _DB_PASSWD_;
                $prefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';
            }
        }

        if ($host && $name && $user) {
            $db = @mysqli_connect($host, $user, $pass, $name);
            if ($db) {
                $sql = sprintf(
                    "SELECT value FROM `%sconfiguration` WHERE name IN ('PS_INSTALL_VERSION','PS_VERSION_DB') ORDER BY FIELD(name,'PS_INSTALL_VERSION','PS_VERSION_DB') LIMIT 1",
                    $prefix
                );
                if ($res = @mysqli_query($db, $sql)) {
                    if ($row = mysqli_fetch_row($res)) {
                        @mysqli_close($db);
                        return $row[0];
                    }
                }
                @mysqli_close($db);
            }
        }

        // 2) Fallback: read _PS_VERSION_ constant in defines.inc.php
        $candidates = [
            getcwd() . '/config/defines.inc.php',
            getcwd() . '/app/config/defines.inc.php',
        ];
        foreach ($candidates as $file) {
            if (file_exists($file)) {
                $content = @file_get_contents($file);
                if ($content && preg_match("/define\\s*\\(\\s*['_\"]_PS_VERSION_['_\"]\\s*,\\s*['_\"]([^'_\\\"]+)['_\"]\\s*\\)/", $content, $m)) {
                    return $m[1];
                }
            }
        }

        return 'Unknown';
    }

    /**
     * Get versions data
     *
     * @return array
     */
    public function getVersions()
    {
        $data = [
            'Web server' => [$this->getWebServer()],
            'PHP Type' => [
                strpos(PHP_SAPI, 'cgi') !== false ?
                    'CGI with Apache Worker or another webserver' :
                    (strpos(PHP_SAPI, 'litespeed') !== false ? 'Litespeed (Better performance)' : 'Apache Module (low performance)')
            ],
        ];

        $currentPs = $this->getPrestaShopVersion();
        $psNorm = $this->normalizePsVersion($currentPs);

        // Static PS row color using a normalized version
        $psStatus =
            ($psNorm && isset($this->recommended['versions']['prestashop']) &&
                $this->normalizePsVersion($this->recommended['versions']['prestashop']) &&
                version_compare($psNorm, $this->normalizePsVersion($this->recommended['versions']['prestashop']), '>='))
                ? self::TYPE_OK
                : (($psNorm && isset($this->requirements['versions']['prestashop']) &&
                $this->normalizePsVersion($this->requirements['versions']['prestashop']) &&
                version_compare($psNorm, $this->normalizePsVersion($this->requirements['versions']['prestashop']), '>='))
                ? self::TYPE_WARNING : self::TYPE_ERROR);

        $data['PrestaShop Version'] = [
            $this->requirements['versions']['prestashop'] ?? 'N/A',
            $this->recommended['versions']['prestashop'] ?? 'N/A',
            $currentPs,
            $psStatus
        ];

        // Dynamic PHP bounds for the detected PS (now works for "1.6.1.23")
        $phpBounds = $psNorm ? ($this->getPhpSupportForPs($psNorm) ?? null) : null;
        $phpMin = $phpBounds['min'] ?? 'N/A'; // Required PHP for this PS version
        $phpRec = $phpBounds['rec'] ?? 'N/A'; // Recommended PHP for this PS version
        $phpCur = preg_match('~^\d+\.\d+~', PHP_VERSION, $m) ? $m[0] : 'N/A'; // Current PHP major.minor

        if ($phpCur && $phpRec && version_compare($phpCur, $phpRec, '>')) {
            $phpDynStatus = self::TYPE_ERROR; // Current PHP is higher than recommended
        } elseif ($phpCur && $phpMin && version_compare($phpCur, $phpMin, '<')) {
            $phpDynStatus = self::TYPE_ERROR; // Current PHP is lower than required
        } elseif ($phpCur && $phpRec && version_compare($phpCur, $phpRec, '==')) {
            $phpDynStatus = self::TYPE_OK; // Current PHP is the recommended version
        } elseif ($phpCur && $phpMin && version_compare($phpCur, $phpMin, '>=') && version_compare($phpCur, $phpRec, '<')) {
            $phpDynStatus = self::TYPE_WARNING; // Current PHP is not the last recommended version
        } else {
            $phpDynStatus = self::TYPE_ERROR; // Unknown state
        }

        $data['PHP Version for current PrestaShop Version'] = [
            $phpMin,
            $phpRec,
            PHP_VERSION,
            $phpDynStatus
        ];

        if (!extension_loaded('mysqli') || !is_callable('mysqli_connect')) {
            $data['MySQLi Extension'] = [
                true,
                true,
                'Not installed',
                self::TYPE_ERROR,
            ];
        } else {
            $data['MySQLi Extension'] = [
                $this->requirements['versions']['mysql'],
                $this->recommended['versions']['mysql'],
                mysqli_get_client_info(),
                self::TYPE_OK,
            ];
        }

        $ok = (gethostbyname('www.prestashop.com') !== 'www.prestashop.com');
        $data['Internet connectivity (Prestashop)'] = [false, true, $ok, $ok];

        return $data;
    }

    /**
     * Normalize the PS version to "X.Y.Z" (drops patch number: "1.6.1.23" -> "1.6.1").
     * Returns null if not a dotted numeric version.
     */
    protected function normalizePsVersion(?string $v): ?string
    {
        if (!is_string($v) || !preg_match('~^\d+(?:\.\d+)+$~', $v)) {
            return null;
        }
        $parts = explode('.', $v);
        $parts = array_slice($parts, 0, 3);        // keep at most 3 segments
        while (count($parts) < 3) { $parts[] = '0'; } // pad to 3
        return implode('.', $parts);
    }

    /**
     * Get php extensions data
     *
     * @return array
     */
    public function getPhpExtensions()
    {
        $data = [];
        $vars = [
            'BCMath Arbitrary Precision Mathematics' => 'bcmath',
            'Client URL Library (Curl)' => 'curl',
            'Image Processing and GD' => 'gd',
            'Image Processing (ImageMagick)' => 'imagick',
            'Human Language and Character Encoding Support (Iconv)' => 'iconv',
            'Internationalization Functions (Intl)' => 'intl',
            'Memcache' => 'memcache',
            'Memcached' => 'memcached',
            'Multibyte String (Mbstring)' => 'mbstring',
            'OpenSSL' => 'openssl',
            'File Information (Fileinfo)' => 'fileinfo',
            'JavaScript Object Notation (Json)' => 'json',
            'PDO and MySQL Functions' => 'pdo_mysql',
            'SimpleXML' => 'simplexml',
        ];
        foreach ($vars as $label => $var) {
            $value = extension_loaded($var);
            $data[$label] = [
                $this->requirements['extensions'][$var],
                $this->recommended['extensions'][$var],
                $value
            ];
        }

        $vars = [
            'PHP-DOM and PHP-XML' => ['dom', 'DomDocument'],
            'Zip' => ['zip', 'ZipArchive'],
        ];
        foreach ($vars as $label => $var) {
            $value = class_exists($var[1]);
            $data[$label] = [
                $this->requirements['extensions'][$var[0]],
                $this->recommended['extensions'][$var[0]],
                $value
            ];
        }

        return $data;
    }

    /**
     * Map PrestaShop version to PHP minimal and recommended versions.
     * Returns ['min' => 'x.y', 'rec' => 'x.y'] or null if unknown.
     * Built from the provided compatibility matrix.
     */
    protected function getPhpSupportForPs(string $psVersion): ?array
    {
        $ps = $this->normalizePsVersion($psVersion);
        if ($ps === null) { return null; }

        // Normalize "1.x" -> "1.x.0"
        if (preg_match('~^\d+\.\d+(\.\d+)?$~', $psVersion)) {
            if (substr_count($psVersion, '.') === 1) {
                $psVersion .= '.0';
            }
        } else {
            return null;
        }

        // Rules from your table
        $rules = [
            ['min' => '1.2.0', 'max' => '1.5.9', 'req' => '5.2', 'rec' => '5.6'],
            ['min' => '1.6.0', 'max' => '1.6.0', 'req' => '5.2', 'rec' => '7.0'],
            ['min' => '1.6.1', 'max' => '1.6.9', 'req' => '5.2', 'rec' => '7.1'],
            ['min' => '1.7.0', 'max' => '1.7.3', 'req' => '5.4', 'rec' => '7.1'],
            ['min' => '1.7.4', 'max' => '1.7.4', 'req' => '5.6', 'rec' => '7.1'],
            ['min' => '1.7.5', 'max' => '1.7.6', 'req' => '5.6', 'rec' => '7.2'],
            ['min' => '1.7.7', 'max' => '1.7.7', 'req' => '7.1', 'rec' => '7.3'],
            ['min' => '1.7.8', 'max' => '7.9.9', 'req' => '7.1', 'rec' => '7.4'],
            ['min' => '8.0.0', 'max' => '8.9.9', 'req' => '7.2', 'rec' => '8.1'],
            ['min' => '9.0.0', 'max' => '9.9.9', 'req' => '8.1', 'rec' => '8.3'],
            // To be continued...
        ];

        foreach ($rules as $r) {
            if (version_compare($psVersion, $r['min'], '>=') && version_compare($psVersion, $r['max'], '<=')) {
                return ['min' => $r['req'], 'rec' => $r['rec']];
            }
        }
        return null;
    }

    /**
     * Get php config data
     *
     * @return array
     */
    public function getPhpConfig()
    {
        $data = [];
        $vars = [
            'allow_url_fopen',
            'expose_php',
            'file_uploads',
            'register_argc_argv',
            'short_open_tag',
        ];
        foreach ($vars as $var) {
            $value = (bool) ini_get($var);
            $data[$var] = [
                $this->requirements['config'][$var],
                $this->recommended['config'][$var],
                $value
            ];
        }

        $vars = [
            'max_input_vars',
            'memory_limit',
            'post_max_size',
            'upload_max_filesize',
        ];
        foreach ($vars as $var) {
            $value = ini_get($var);
            if ($this->toBytes($value) >= $this->toBytes($this->recommended['config'][$var])) {
                $result = self::TYPE_OK;
            } elseif ($this->toBytes($value) >= $this->toBytes($this->requirements['config'][$var])) {
                $result = self::TYPE_WARNING;
            } else {
                $result = self::TYPE_ERROR;
            }

            $data[$var] = [
                $this->requirements['config'][$var],
                $this->recommended['config'][$var],
                $value,
                $result,
            ];
        }

        $vars = [
            'set_time_limit',
        ];
        foreach ($vars as $var) {
            $value = is_callable($var);
            $data[$var] = [
                $this->recommended['config'][$var],
                $this->requirements['config'][$var],
                $value
            ];
        }

        return $data;
    }

    /**
     * Check if directories are writable
     *
     * @return array
     */
    public function getDirectories()
    {
        $data = [];
        foreach ($this->requirements['directories'] as $directory) {
            $directoryPath = getcwd() . DIRECTORY_SEPARATOR . trim($directory, '\\/');
            $data[$directory] = file_exists($directoryPath) ? [is_writable($directoryPath)] : [null];
        }

        return $data;
    }

    public function getServerModules()
    {
        $data = [];
        if ($this->getWebServer() !== 'Apache' || !function_exists('apache_get_modules')) {
            return $data;
        }

        $modules = apache_get_modules();
        $vars = array_keys($this->requirements['apache_modules']);
        foreach ($vars as $var) {
            $value = in_array($var, $modules);
            $data[$var] = [
                $this->requirements['apache_modules'][$var],
                $this->recommended['apache_modules'][$var],
                $value,
            ];
        }

        return $data;
    }

    /**
     * Convert PHP variable (G/M/K) to bytes
     * Source: http://php.net/manual/fr/function.ini-get.php
     *
     * @param mixed $value
     *
     * @return integer
     */
    public function toBytes($value)
    {
        if (is_numeric($value)) {
            return $value;
        }

        $value = trim($value);
        $val = (int) $value;
        switch (strtolower($value[strlen($value)-1])) {
            case 'g':
                $val *= 1024;
            // continue
            case 'm':
                $val *= 1024;
            // continue
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    /**
     * Transform value to string
     *
     * @param mixed $value Value
     *
     * @return string
     */
    public function toString($value)
    {
        if ($value === true) {
            return 'Yes';
        } elseif ($value === false) {
            return 'No';
        } elseif ($value === null) {
            return 'N/A';
        }

        return strval($value);
    }

    /**
     * Get html class
     *
     * @param array $data
     * @return string
     */
    public function toHtmlClass(array $data)
    {
        if (count($data) === 1 && !is_bool($data[0])) {
            return self::TYPE_INFO_CLASS;
        }


        if (count($data) === 1 && is_bool($data[0])) {
            $result = $data[0];
        } elseif (array_key_exists(3, $data)) {
            $result = $data[3];
        } else {
            if ($data[2] >= $data[1]) {
                $result = self::TYPE_OK;
            } elseif ($data[2] >= $data[0]) {
                $result = self::TYPE_WARNING;
            } else {
                $result = self::TYPE_ERROR;
            }
        }

        if ($result === false) {
            return self::TYPE_ERROR_CLASS;
        }

        if ($result === null) {
            return self::TYPE_WARNING_CLASS;
        }

        return self::TYPE_SUCCESS_CLASS;
    }

    /**
     * Detect Web server
     *
     * @return string
     */
    protected function getWebServer()
    {
        $s = $_SERVER['SERVER_SOFTWARE'] ?? '';
        if (stripos($s,'Apache')!==false) return 'Apache';
        if (stripos($s,'LiteSpeed')!==false) return 'Lite Speed';
        if (stripos($s,'Nginx')!==false) return 'Nginx';
        if (stripos($s,'lighttpd')!==false) return 'lighttpd';
        if (stripos($s,'IIS')!==false) return 'Microsoft IIS';
        return 'Not detected';
    }

    /**
     * Determines if a command exists on the current environment
     * Source: https://stackoverflow.com/questions/12424787/how-to-check-if-a-shell-command-exists-from-php
     *
     * @param string $command The command to check
     *
     * @return bool
     */
    protected function commandExists($command)
    {
        $which = (PHP_OS == 'WINNT') ? 'where' : 'which';

        $process = proc_open(
            $which . ' ' . $command,
            [
                ['pipe', 'r'], //STDIN
                ['pipe', 'w'], //STDOUT
                ['pipe', 'w'], //STDERR
            ],
            $pipes
        );

        if ($process !== false) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return $stdout != '';
        }

        return false;
    }
}

// Init render
$info = new PhpPsInfo();
$info->checkAuth();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <meta name="description" content=""/>
    <meta name="author" content=""/>
    <link rel="icon" href="../../../../favicon.ico"/>

    <title>PHP PrestaShop Info</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        h1 {font-size:2rem;}
    </style>
</head>

<body>
<nav class="navbar navbar-dark bg-dark flex-md-nowrap p-0 shadow">
    <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="#">PHP PrestaShop Info</a>
</nav>

<div class="container-fluid">
    <div class="row justify-content-md-center">
        <main role="main" class="col-8">
            <h1>General information & PHP/MySQL Version</h1>
            <div class="table-responsive">
                <table class="table table-striped table-sm text-center">
                    <thead>
                    <tr>
                        <th class="text-left">#</th>
                        <th>Required</th>
                        <th>Recommended</th>
                        <th>Current</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($info->getVersions() as $label => $data) : ?>
                        <?php if (count($data) === 1) : ?>
                            <tr>
                                <td class="text-left"><?php echo $label ?></td>
                                <td class="<?php echo $info->toHtmlClass($data); ?>" colspan="3"><?php echo $info->toString($data[0]) ?></td>
                            </tr>
                        <?php else : ?>
                            <tr>
                                <td class="text-left"><?php echo $label ?></td>
                                <td><?php echo $info->toString($data[0]) ?></td>
                                <td><?php echo $info->toString($data[1]) ?></td>
                                <td class="<?php echo $info->toHtmlClass($data); ?>"><?php echo $info->toString($data[2]) ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h1>PHP Configuration</h1>

            <div class="table-responsive">
                <table class="table table-striped table-sm text-center">
                    <thead>
                    <tr>
                        <th class="text-left">#</th>
                        <th>Required</th>
                        <th>Recommended</th>
                        <th>Current</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($info->getPhpConfig() as $label => $data) : ?>
                        <tr>
                            <td class="text-left"><?php echo $label ?></td>
                            <td><?php echo $info->toString($data[0]) ?></td>
                            <td><?php echo $info->toString($data[1]) ?></td>
                            <td class="<?php echo $info->toHtmlClass($data); ?>"><?php echo $info->toString($data[2]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h1>PHP Extensions</h1>

            <div class="table-responsive">
                <table class="table table-striped table-sm text-center">
                    <thead>
                    <tr>
                        <th class="text-left">#</th>
                        <th>Required</th>
                        <th>Recommended</th>
                        <th>Current</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($info->getPhpExtensions() as $label => $data) : ?>
                        <tr>
                            <td class="text-left"><?php echo $label ?></td>
                            <td><?php echo $info->toString($data[0]) ?></td>
                            <td><?php echo $info->toString($data[1]) ?></td>
                            <td class="<?php echo $info->toHtmlClass($data); ?>"><?php echo $info->toString($data[2]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h1>Directories</h1>

            <div class="table-responsive">
                <table class="table table-striped table-sm text-center">
                    <thead>
                    <tr>
                        <th class="text-left">#</th>
                        <th>Is Writable</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($info->getDirectories() as $label => $data) : ?>
                        <tr>
                            <td class="text-left"><?php echo $label ?></td>
                            <?php if (null == $data[0]) : ?>
                                <td class="<?php echo PhpPsInfo::TYPE_ERROR_CLASS; ?>">Directory not exists</td>
                            <?php else : ?>
                                <td class="<?php echo $info->toHtmlClass($data); ?>"><?php echo $info->toString($data[0]) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (count($info->getServerModules()) > 0): ?>
                <h1>Apache Modules</h1>

                <div class="table-responsive">
                    <table class="table table-striped table-sm text-center">
                        <thead>
                        <tr>
                            <th class="text-left">#</th>
                            <th>Required</th>
                            <th>Recommended</th>
                            <th>Current</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($info->getServerModules() as $label => $data) : ?>
                            <tr>
                                <td class="text-left"><?php echo $label ?></td>
                                <td><?php echo $info->toString($data[0]) ?></td>
                                <td><?php echo $info->toString($data[1]) ?></td>
                                <td class="<?php echo $info->toHtmlClass($data); ?>"><?php echo $info->toString($data[2]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<footer class="footer-copyright text-center py-3">
    © <?php echo date('Y') ?> Copyright: <a href="https://prestashop.com/">PrestaShop</a>
</footer>
</body>
</html>
