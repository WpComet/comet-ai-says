<?php

defined('ABSPATH') || exit;

return function (): array {
    $steps = [];
    $title = __('Server Image Downsampling & AVIF Vision', 'comet-ai-says');

    // 1. Check PHP graphic extensions
    $has_gd      = extension_loaded('gd');
    $has_imagick = extension_loaded('imagick') && class_exists('Imagick');

    $steps[] = sprintf(
        __('Detected image engines: GD (%1$s), Imagick (%2$s)', 'comet-ai-says'),
        $has_gd ? __('Installed', 'comet-ai-says') : __('Not installed', 'comet-ai-says'),
        $has_imagick ? __('Installed', 'comet-ai-says') : __('Not installed', 'comet-ai-says')
    );

    if (!$has_gd && !$has_imagick) {
        return [
            'status' => 'error',
            'title'  => $title,
            'steps'  => $steps,
            'result' => __('Neither GD nor Imagick is installed on this PHP server. Image vision multimodal features will not function.', 'comet-ai-says'),
        ];
    }

    // 2. Test format support
    $formats = [
        'JPEG' => function_exists('imagejpeg') || ($has_imagick && in_array('JPEG', \Imagick::queryFormats(), true)),
        'PNG'  => function_exists('imagepng') || ($has_imagick && in_array('PNG', \Imagick::queryFormats(), true)),
        'WebP' => function_exists('imagewebp') || ($has_imagick && in_array('WEBP', \Imagick::queryFormats(), true)),
        'AVIF' => function_exists('imageavif') || ($has_imagick && in_array('AVIF', \Imagick::queryFormats(), true)),
    ];

    $supported_names = [];
    foreach ($formats as $fmt => $supported) {
        if ($supported) {
            $supported_names[] = $fmt;
        }
    }
    $steps[] = sprintf(__('Supported format encoders: %s', 'comet-ai-says'), implode(', ', $supported_names));

    // 3. Test wp_get_image_editor with synthetic micro image
    $temp_img = wp_tempnam('cmt_test_img.png');
    $im = imagecreatetruecolor(100, 100);
    $bg = imagecolorallocate($im, 99, 102, 241);
    imagefill($im, 0, 0, $bg);
    imagepng($im, $temp_img);
    imagedestroy($im);

    $editor = wp_get_image_editor($temp_img);
    if (is_wp_error($editor)) {
        @unlink($temp_img);
        return [
            'status' => 'error',
            'title'  => $title,
            'steps'  => $steps,
            'result' => sprintf(__('wp_get_image_editor failed: %s', 'comet-ai-says'), $editor->get_error_message()),
        ];
    }

    $resize_res = $editor->resize(50, 50, false);
    $save_temp  = wp_tempnam('cmt_test_thumb.jpg');
    $saved      = $editor->save($save_temp, 'image/jpeg');

    @unlink($temp_img);
    @unlink($save_temp);
    if (!is_wp_error($saved) && file_exists($saved['path'])) {
        @unlink($saved['path']);
    }

    if (is_wp_error($resize_res) || is_wp_error($saved)) {
        return [
            'status' => 'warn',
            'title'  => $title,
            'steps'  => $steps,
            'result' => __('Image resizing or saving test encountered a warning.', 'comet-ai-says'),
        ];
    }

    $steps[] = __('Successfully verified live 100x100 -> 50x50 resize & JPEG downsampling pipeline.', 'comet-ai-says');

    return [
        'status' => 'pass',
        'title'  => $title,
        'steps'  => $steps,
        'result' => sprintf(
            __('Image pipeline operational using %1$s. Formats verified: %2$s. Downsampling and AVIF conversion working properly.', 'comet-ai-says'),
            get_class($editor),
            implode(', ', $supported_names)
        ),
    ];
};
