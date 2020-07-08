<?php

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('bnomei/cloudconvert', [
    'options' => [
        'cache' => true,
        'apikey' => null,
        'convert' => function ($api, array $options, string $outputPath, bool $async) {
            if ($async) {
                return \Bnomei\CloudConvert::defaultConvertAsync($api, $options, $outputPath);
            } else {
                return \Bnomei\CloudConvert::defaultConvert($api, $options, $outputPath);
            }
        },
        'async' => true,
        'options' => [
            'input' => 'download', // must be public else use 'upload'
            // 'save' => true,
        ],
        'log.enabled' => false,
        'log.fn' => function (string $msg, string $level = 'info', array $context = []):bool {
            if (option('bnomei.cloudconvert.log.enabled') && function_exists('kirbyLog')) {
                kirbyLog('bnomei.cloudconvert.log')->log($msg, $level, $context);
                return true;
            }
            return false;
        },
    ],
    'fileMethods' => [
        'cloudconvert' => function (array $options, string $outputPath = null, $async = null) {
            $options = array_merge(option('bnomei.cloudconvert.options'), $options);
            if (!\Kirby\Toolkit\A::get($options, 'file')) {
                $options['file'] = $this; // public url for download
            }
            return \Bnomei\CloudConvert::convert($options, $outputPath, $async);
        }
    ],
    'routes' => [
        [
            'pattern' => 'plugin-cloudconvert',
            'action' => function () {
                $id = strip_tags(kirby()->request()->get('id'));
                $url = strip_tags(urldecode(kirby()->request()->get('url')));
                if ($id && $url) {
                    if (\Bnomei\CloudConvert::callback($id, $url)) {
                        return Kirby\Http\Response::json([], 200);
                    }
                }
                return Kirby\Http\Response::json([], 500);
            },
        ]
    ]
]);

if (!class_exists('Bnomei\CloudConvert')) {
    require_once __DIR__ . '/classes/CloudConvert.php';
}

if (!function_exists('cloudconvert')) {
    function cloudconvert(array $options, string $outputPath = null)
    {
        return \Bnomei\CloudConvert::convert($options, $outputPath);
    }
}
