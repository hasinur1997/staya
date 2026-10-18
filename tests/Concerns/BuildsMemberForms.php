<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * Valid member form input, for sign-up and the staff forms.
 */
trait BuildsMemberForms
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function memberForm(array $overrides = []): array
    {
        return [
            'first_name' => 'Rahim',
            'last_name' => 'Uddin',
            'phone' => '01712-345678',
            'nid_number' => '1234 5678 90',
            'father_name' => 'Karim Uddin',
            'mother_name' => 'Amina Begum',
            'permanent_address' => 'House 12, Road 3, Dhanmondi, Dhaka',
            'nid_file' => UploadedFile::fake()->create('nid.pdf', 200, 'application/pdf'),
            ...$overrides,
        ];
    }
}
