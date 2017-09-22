<?php

namespace App\Http\Controllers\Admin\Email;

use app\Http\Controllers\Controller;

class Flow extends Controller
{
    // @param array $ids
    // This will create template for preview-and-send, template will have placeholders.
    // @return array()
    public function createPreviewWithPlaceholders(Request $r)
    {
        $ids = $r->input('ids');

        // validate events selection
        $validate = new \App\Http\Controllers\Admin\Email\ValidateGroup($ids);
        if ($validate->error)
            return [
                'type' => 'error',
                'message' => $validate->message,
            ];

        // get email template
        $package = \App\Package::find($validate->packageId);

        // replace section in template
        $templateString = new \App\Http\Controllers\Admin\Email\RemoveSection($package->template, $validate->isNoTip);

        return [
            'type'        => 'success',
            'template'    => $templateString->template,
            'packageName' => $package->name,
            'siteName'    => \App\Site::find($package->siteId)->name,
        ];
    }

    // this is use to have a full preview of template with all events included.
    // @param array $ids
    // @return array()
    public  function createFullPreview(Request $r) 
    {
        $ids = $r->input('ids');
        $template = $r->input('template');

        // validate events selection
        $validate = new \App\Http\Controllers\Admin\Email\ValidateGroup($ids);
        if ($validate->error)
            return [
                'type' => 'error',
                'message' => $validate->message,
            ];

        // get email template
        $events = \App\Distribution::whereIn('id', $ids)->get();

        // replace section in template
        $replaceTips = new \App\Http\Controllers\Admin\Email\ReplaceTipsInTemplate($template, $events, $validate->isNoTip);

        return [
            'type'        => 'success',
            'template'    => $replaceTips->template,
        ];
    }
}
