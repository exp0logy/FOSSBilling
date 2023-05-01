<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


class FOSSBilling_Requirements implements \Box\InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    private bool $_all_ok = true;
    private string $_app_path = PATH_ROOT;
    private array $_options = array();

    public function __construct()
    {
        $this->_options = array(
            'php'   =>  array(
                'extensions' => array(
                    'pdo_mysql',
                    'zlib',
                    'openssl',
                    'dom',
                    'xml',
                 ),
                'version'       =>  PHP_VERSION,
                'min_version'   =>  '8.0',
                'safe_mode'     =>  ini_get('safe_mode'),
            ),
            'writable_folders' => array(
                $this->_app_path . '/data/cache',
                $this->_app_path . '/data/log',
                $this->_app_path . '/data/uploads',
            ),
            'writable_files' => array(
                $this->_app_path . '/config.php',
            ),
        );
    }

    public function getOptions(): array
    {
        return $this->_options;
    }

    public function getInfo(): array
    {
        $data = array();
        $data['ip']             = $_SERVER['SERVER_ADDR'] ?? null;
        $data['PHP_OS']         = PHP_OS;
        $data['PHP_VERSION']    = PHP_VERSION;

        $data['FOSSBilling']    = array(
            'BB_LOCALE'     =>  $this->di['config']['i18n']['locale'],
            'version'       =>  FOSSBilling_Version::VERSION,
        );

        $data['ini']    = array(
            'allow_url_fopen'   =>  ini_get('allow_url_fopen'),
            'safe_mode'         =>  ini_get('safe_mode'),
            'memory_limit'      =>  ini_get('memory_limit'),
        );

        $data['permissions']    = array(
            PATH_UPLOADS     =>  substr(sprintf('%o', fileperms(PATH_UPLOADS)), -4),
            PATH_DATA        =>  substr(sprintf('%o', fileperms(PATH_DATA)), -4),
            PATH_CACHE       =>  substr(sprintf('%o', fileperms(PATH_CACHE)), -4),
            PATH_LOG         =>  substr(sprintf('%o', fileperms(PATH_LOG)), -4),
        );

        $data['extensions']    = array(
            'apc'           => extension_loaded('apc'),
            'pdo_mysql'     => extension_loaded('pdo_mysql'),
            'zlib'          => extension_loaded('zlib'),
            'mbstring'      => extension_loaded('mbstring'),
            'openssl'        => extension_loaded('openssl'),
        );

        //determine php username
        if(function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $data['posix_getpwuid'] = posix_getpwuid(posix_geteuid());
        }
        return $data;
    }

    public function isPhpVersionOk(): bool
    {
        $current = $this->_options['php']['version'];
        $required = $this->_options['php']['min_version'];
        return version_compare($current, $required, '>=');
    }

    public function isFOSSBillingVersionOk(): bool
    {
        return FOSSBilling_Version::VERSION !== '0.0.1';
    }

    /**
     * What extensions must be loaded for FOSSBilling to function correctly
     */
    public function extensions(): array
    {
        $exts = $this->_options['php']['extensions'];

        $result = array();
        foreach($exts as $ext) {
            if(extension_loaded($ext)) {
                $result[$ext] = true;
            } else {
                $result[$ext] = false;
                $this->_all_ok = false;
            }
        }

        return $result;
    }

    /**
     * Files that must be writable
     */
    public function files(): array
    {
        $files = $this->_options['writable_files'];
        $result = array();

        foreach($files as $file) {
            if ($this->checkPerms($file)) {
                $result[$file] = true;
            } else if (is_writable($file)) {
            	$result[$file] = true;
            } else {
                $result[$file] = false;
                $this->_all_ok = false;
            }
        }

        return $result;
    }

    /**
     * Folders that must be writable
     */
    public function folders(): array
    {
        $folders = $this->_options['writable_folders'];

        $result = array();
        foreach($folders as $folder) {
            if($this->checkPerms($folder)) {
                $result[$folder] = true;
            } else if (is_writable($folder)) {
            	$result[$folder] = true;
            } else {
                $result[$folder] = false;
                $this->_all_ok = false;
            }
        }

        return $result;
    }

    /**
     * Check if we can continue with installation
     * @return bool
     */
    public function canInstall(): bool
    {
        $this->extensions();
        $this->folders();
        $this->files();
        return $this->_all_ok;
    }

    /**
     * Check permissions
     * @param string $path
     * @param string $perm
     * @return bool
     */
    public function checkPerms(string $path, string $perm = '0777'): bool
    {
        clearstatcache();
        $configmod = substr(sprintf('%o', @fileperms($path)), -4);
        return ($configmod == $perm);
    }
}