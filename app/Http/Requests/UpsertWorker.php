<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertWorker extends FormRequest
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
            'title' => [
                'required',
                'array',
            ],
            'title.instances' => [
                'required',
                'array',
                'size:1',
            ],
            'title.instances.*' => [
                'required',
                'array',
            ],
            'title.instances.*.instanceId' => [
                'required',
                'string',
                'regex:/^247\$\d+$/',
            ],
            'title.instances.*.text' => [
                'required',
                'string',
            ],
            'body' => [
                'required',
                'array',
            ],
            'body.compositeViewHeader' => [
                'required',
                'array',
            ],
            'body.compositeViewHeader.contactInfo' => [
                'required',
                'array',
            ],
            'body.compositeViewHeader.contactInfo.firstName' => [
                'required',
                'string',
            ],
            'body.compositeViewHeader.contactInfo.lastName' => [
                'required',
                'string',
            ],
            'body.compositeViewHeader.contactInfo.primaryEmail' => [
                'required',
                'string',
                'email:rfc,strict,dns,spoof,filter',
            ],
        ];
    }
}
