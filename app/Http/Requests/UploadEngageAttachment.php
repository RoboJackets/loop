<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadEngageAttachment extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @psalm-pure
     */
    public function authorize(): true
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string>>
     *
     * @psalm-pure
     */
    public function rules(): array
    {
        return [
            'attachment' => [
                'required',
                'file',
            ],
            'documentId' => [
                'required',
                'numeric',
                'integer',
            ],
        ];
    }
}
