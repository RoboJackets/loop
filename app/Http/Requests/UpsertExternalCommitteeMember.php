<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ExternalCommitteeMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a request to upsert an External Committee Member using the Workday API response.
 *
 * @phan-suppress PhanUnreferencedClass
 */
class UpsertExternalCommitteeMember extends FormRequest
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
                'regex:/^15341\$\d+$/',
            ],
            'title.instances.*.text' => [
                'required',
                'string',
                'regex:'.ExternalCommitteeMember::WORKDAY_NAME_REGEX,
            ],
            'body' => [
                'required',
                'array',
            ],
            'body.children' => [
                'required',
                'array',
                'size:1',
            ],
            'body.children.*' => [
                'required',
                'array',
            ],
            'body.children.*.value' => [
                'required',
                'string',
                'regex:/^ECM-\d{6}$/',
            ],
        ];
    }
}
