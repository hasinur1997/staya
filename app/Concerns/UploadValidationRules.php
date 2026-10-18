<?php

namespace App\Concerns;

use App\Models\MessSetting;
use Illuminate\Contracts\Validation\ValidationRule;

trait UploadValidationRules
{
    /**
     * Rules for an uploaded file: one of the given types, checked by
     * content, and no larger than the mess's upload limit.
     *
     * @param  array<int, string>  $extensions
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function uploadRules(array $extensions): array
    {
        return [
            'file',
            'mimes:'.implode(',', $extensions),
            'max:'.MessSetting::current()->max_upload_kb,
        ];
    }
}
