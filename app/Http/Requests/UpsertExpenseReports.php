<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertExpenseReports extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'rows' => [
                'required',
                'array',
            ],
            'rows.*' => [
                'required',
                'array',
            ],
            'rows.*.cellsMap' => [
                'required',
                'array',
            ],
            'rows.*.cellsMap.*' => [
                'required',
                'array',
            ],
            'rows.*.cellsMap.*.widget' => [
                'required',
                'string',
            ],
            'rows.*.cellsMap.*.ecid' => [
                'required',
                'string',
            ],
            'rows.*.cellsMap.*.iid' => [
                'required',
                'string',
            ],
            'rows.*.cellsMap.*.enabled' => [
                'required',
                'boolean',
            ],
            'rows.*.cellsMap.*.label' => [
                'required',
                'string',
            ],
            'rows.*.cellsMap.*.propertyName' => [
                'required',
                'string',
            ],
        ];
    }
}
