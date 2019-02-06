<?php

namespace Bnomei;

use \CloudConvert\Api as Api;
use \CloudConvert\Process as Process;

class CloudConvert
{
    private static $cache = null;
    private static function cache(): \Kirby\Cache\Cache
    {
        if (!static::$cache) {
            static::$cache = kirby()->cache('bnomei.cloudconvert');
        }
        return static::$cache;
    }

    private static function api()
    {
        $apikey = option('bnomei.cloudconvert.apikey');
        if ($apikey) {
            if (is_callable($apikey)) {
                $apikey = trim($apikey());
            }
            try {
                $instance = new Api($apikey);
                return $instance;
            } catch (Exception $ex) {
                self::log($ex->getMessage(), 'error');
            }
        } else {
            self::log('api(): Failed creating api instance', 'warning');
        }
        return null;
    }

    public static function convert(array $options, string $outputPath = null, $async = null)
    {
        $convert = option('bnomei.cloudconvert.convert');

        $ao = option('bnomei.cloudconvert.async');
        $async = ($async === null) ? boolval($ao) : $async;
        if (self::isLocalhost()) {
            $async = false;
        }

        $api = self::api();
        if (!$api) {
            return null;
        }

        if (\Kirby\Toolkit\A::get($options, 'input') == 'download' && self::isLocalhost()) {
            $options['input'] = 'upload';
        }

        $file = \Kirby\Toolkit\A::get($options, 'file');
        $input = \Kirby\Toolkit\A::get($options, 'input');

        if (is_a($file, 'Kirby\Cms\File')) {
            $page = $file->parent();
            $options['kirby.page'] = $page->id();

            if ($outputPath == null) {
                $outputPath = str_replace(
                    '.'.$options['inputformat'],
                    '.'.$options['outputformat'],
                    $file->root()
                );
            }

            $modID = md5($file->id() . $outputPath);
            $modified = intval(static::cache()->get($modID));

            if (!$modified || $modified < $file->modified()) {
                static::cache()->set($modID, $file->modified());

            // TODO: not just file exists. check if a process for this file is issues.
            // that also means done process caches must be removed. but think about deadlock.
            } elseif (\Kirby\Toolkit\F::exists($outputPath)) {
                return $page->file(basename($outputPath));
            }

            if ($input == 'upload') {
                $options['file'] = fopen($file->root(), 'r');
            } elseif ($input == 'download') {
                $options['file'] = trim($file->url());
            }
        } else {
            // Attention: No modified check for non kirby\cms\file objects
            if (!$outputPath) {
                return null;
            }
            return \Kirby\Toolkit\F::exists($outputPath);
        }

        if (is_callable($convert)) {
            $a = \Kirby\Toolkit\F::filename($outputPath);
            $b = md5($outputPath) . '.' . \Kirby\Toolkit\F::extension($outputPath);
            $options['kirby.outputPath'] = $outputPath;
            $options['kirby.tmp'] = str_replace($a, $b, $outputPath);
    
            $process = $convert($api, $options, $outputPath, $async);

            if (is_a($file, 'Kirby\Cms\File') && is_a($process, 'CloudConvert\Process')) {
                $id = (string) trim($process->id);
                static::cache()->set($id, $options);
            }

            return $process;
        }

        self::log('convert(): Custom convert options is not callable', 'error');
        return null;
    }

    public static function defaultConvert($api, array $options, string $outputPath)
    {
        $tmp = \Kirby\Toolkit\A::get($options, 'kirby.tmp', $outputPath);
        $api->convert($options)
            ->wait()
            ->download($tmp);

        return self::createFile($options, $outputPath);
    }

    public static function defaultConvertAsync($api, array $options, string $outputPath)
    {
        $options['callback'] = kirby()->site()->url() . '/plugin-cloudconvert';

        $process = $api->createProcess([
            'inputformat' => \Kirby\Toolkit\A::get($options, 'inputformat'),
            'outputformat' => \Kirby\Toolkit\A::get($options, 'outputformat'),
            'mode' => 'convert',
        ]);
        $process->start($options);

        if (\Kirby\Toolkit\A::get($options, 'input') == 'upload') {
            $process->upload(\Kirby\Toolkit\A::get($options, 'file'));
        }

        return $process;
    }

    private static function createFile(array $options, string $outputPath)
    {
        if ($uid = \Kirby\Toolkit\A::get($options, 'kirby.page')) {
            if ($page = page($uid)) {
                $tmp = \Kirby\Toolkit\A::get($options, 'kirby.tmp', $outputPath);
                try {
                    kirby()->impersonate('kirby');
                    $file = $page->createFile([
                        'filename' => basename($outputPath),
                        'source' => $tmp,
                    ]);
                    kirby()->impersonate();
                } catch (Exception $ex) {
                } finally {
                    \Kirby\Toolkit\F::remove($tmp);
                }
                return $file;
            }
        }
        return null;
    }

    public static function callback(string $id, string $url)
    {
        if ($options = static::cache()->get($id)) {
            $status = json_decode(\Kirby\Http\Remote::get('http:' . $url)->content(), true);
            if ($output = \Kirby\Toolkit\A::get($status, 'output')) {
                if ($download = \Kirby\Toolkit\A::get($output, 'url')) {
                    $download = 'http:' . $download;
                    $data = \Kirby\Http\Remote::get($download)->content();
                    $outputPath = \Kirby\Toolkit\A::get($options, 'kirby.outputPath');
                    $tmp = \Kirby\Toolkit\A::get($options, 'kirby.tmp', $outputPath);
                    if ($data && $outputPath) {
                        \Kirby\Toolkit\F::write($tmp, $data);
                        return self::createFile($options, $outputPath) != null;
                    }
                }
            }
        }
        return false;
    }

    private static function isLocalhost()
    {
        return in_array($_SERVER['REMOTE_ADDR'], array( '127.0.0.1', '::1' ));
    }

    private static function log(string $msg = '', string $level = 'info', array $context = []):bool
    {
        $log = option('bnomei.cloudconvert.log');
        if ($log && is_callable($log)) {
            if (!option('debug') && $level == 'debug') {
                // skip but...
                return true;
            } else {
                return $log($msg, $level, $context);
            }
        }
        return false;
    }
}
