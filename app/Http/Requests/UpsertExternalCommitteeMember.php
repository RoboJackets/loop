<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace App\Http\Requests;

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
                'array:widget,ecid,iid,enabled,label,instances,selfUriTemplate,relatedTasksUriTemplate,text,propertyName,singular',
            ],
            'title.instances' => [
                'required',
                'array',
                'size:1',
            ],
            'title.instances.*' => [
                'required',
                'array:widget,instanceId,text,rt,pv,v',
            ],
            'title.instances.*.instanceId' => [
                'required',
                'string',
                'regex:/^15341\$\d+$/',
            ],
            'title.instances.*.text' => [
                'required',
                'string',
                'regex:/^[a-zA-Z\s]+\s+\(ECM\)(\s+-\s+Inactive)?$/',
            ],
            'body' => [
                'required',
                'array:widget,iid,enabled,children,propertyName',
            ],
            'body.children' => [
                'required',
                'array',
                'size:1',
            ],
            'body.children.*' => [
                'required',
                'array:widget,ecid,iid,enabled,label,value,propertyName',
            ],
            'body.children.*.value' => [
                'required',
                'string',
                'regex:/^ECM-\d{6}$/',
            ],
        ];
    }
}
