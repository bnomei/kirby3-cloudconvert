# Kirby 3 Cloudconvert

![GitHub release](https://img.shields.io/github/release/bnomei/kirby3-cloudconvert.svg?maxAge=1800) ![License](https://img.shields.io/github/license/mashape/apistatus.svg) ![Kirby Version](https://img.shields.io/badge/Kirby-3%2B-black.svg)

Plugin to convert anything to anything using [cloudconvert](https://cloudconvert.com/).

## Commerical Usage

This plugin is free but if you use it in a commercial project please consider to 
- [make a donation 🍻](https://www.paypal.me/bnomei/5) or
- [buy me ☕](https://buymeacoff.ee/bnomei) or
- [buy a Kirby license using this affiliate link](https://a.paddle.com/v2/click/1129/35731?link=1170)

## Performance

**TD;DR**
Calling File-Method is performant since it only converts the file if it was modified or new.

### File-Object
Using `Kirby\Cms\File` object for `$options['file']` is recommended. In that case the modified timestamp will be checked against a cached value and a conversion triggered only if a file was modified or output does not exist. This is the default behaviour for the FileMethod provided by this plugin.

> DANGER: There is no check (yet) if a file is currently processed by cloudconvert. This might be improved at a later point.

### Path
When a path is used then file will be created only if ouput does not exist. You need to do modification checks and removing of old files yourself before starting the conversion.

## How to convert files on demand (synchronously)?

### Example 1: docx to pdf

```php
if($fileWord = $page->file('test.docx')) {
    $filePDF = $fileWord->cloudconvert(
        [
            'inputformat' => 'docx',
            'outputformat' => 'pdf',
        ], 
        str_replace('.docx', '.pdf', $fileWord->root()),
        false // wait for conversion to be done
    );
    echo $fileWord->url().PHP_EOL;
    if($filePDF) {
        echo $filePDF->url();
    }
}
```

## Hooks: How to convert files on upload/replace (asynchronously by default)?
### Example 2: gif to webm and mp4

In Kirbys config file add this... then use panel to upload a gif to an image/file section.

```php
function customConvertHook($file) {
    if($file->extension() == 'gif') {

        $file->cloudconvert(
            [
                'inputformat' => 'gif',
                'outputformat' => 'webm',
                'save' => true, // keep file at cloud to avoid another download from cloudconvert-server
            ]
        );

        $file->cloudconvert(
            [
                'inputformat' => 'gif',
                'outputformat' => 'mp4',
            ]
        );
    }
}

return [
    // ... other config settings
    'hooks' => [
        'file.create:after' => function($file) {
            customConvertHook($file);
        },
        'file.replace:after' => function($newFile, $oldFile) {
            customConvertHook($newFile);
        },
    ]
];
```

## Other Usecases

### Example 3: convert ai to svg and optimize
This example shows how to use this plugin with my [thumb imageoptim plugin](https://github.com/bnomei/kirby3-thumb-imageoptim).

```php
$fileSvg = $file->cloudconvert(
    [
        'inputformat' => 'ai',
        'outputformat' => 'svg',
    ], 
    null, // auto-rename with extension
    false // on-demand aka synchonous aka wait
);
if($fileSvg) {
    // NOTE: resize() is called to trigger stuff like optimziers (see thumb-imageoptim plugin)
    $fileSvgOptimized = $fileSvg->resize();
    echo svg($fileSvgOptimized->root()); // use kirbys svg helper to inline the svg
}
```

### Example 4: How to convert image files for srcsets? jpg to webp.
This example shows how to use this plugin with my [srcset plugin](https://github.com/bnomei/kirby3-srcset).

**config file**
```php
return [
    // ... other settings
    'bnomei.srcset.types' => ['jpg', 'webp'],
    'bnomei.srcset.resize' => function (\Kirby\Cms\File $file, int $width, string $type) {
        // NOTE: resize() is called to trigger stuff like optimziers (see thumb-imageoptim plugin)

        // use jpg to create webp
        if($file->extension() == 'jpg' && $type == 'webp') {
            $fileWebp = $file->cloudconvert(
                [
                    'inputformat' => 'jpg',
                    'outputformat' => 'webp',
                ], 
                null, // auto-rename with extension
                false // on-demand aka synchonous aka wait
            );
            if($fileWebp) {
                return $fileWebp->resize($width);
            }
        }
        // otherwise default to returning image
        return $file->resize($width);
    }
];
```

## Global cloudconvert helper function

This plugin provides a helper function to call the cloudconvert api.

```php
$obj = cloudconvert($options); // will change extension but keep path
$obj = cloudconvert($options, $outputPath); // provide different path
$obj = cloudconvert($options, $outputPath, $async); // a/sync
```

**Retrun Values**

- `Kirby\Cms\File` if `$options['file']` is one as well and `$async == false`
- otherwise it returns an instance of `CloudConvert\Process`
- or `null` on error

## Settings

All settings have to be prefixed with `bnomei.cloudconvert.`.

**apikey**
- default: `null` – your cloudconvert apikey

**convert**
- default: asynchronous or synchronous conversion depending on params.

**async**
- default: `true`

**options**
- default: By default this plugin requires the file to be public otherwise use `upload` here. On localhost `upload` is used as a default.

```php
[
    'input' => 'download', // but automatically uses 'upload' on localhost
]
```

> TIP: consider setting up [presets](https://cloudconvert.com/presets) to manage your settings from within the cloudconvert dashboard instead of the Kirby config file.

**log.enabled**
- default: `false`

**log**
- default: callback to `kirbyLog()`


## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test it yourself before using it in a production environment. If you find any issues, please [create a new issue](https://github.com/bnomei/kirby3-cloudconvert/issues/new).

## License

[MIT](https://opensource.org/licenses/MIT)

It is discouraged to use this plugin in any project that promotes racism, sexism, homophobia, animal abuse, violence or any other form of hate speech.

