<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertEngagePurchaseRequests extends FormRequest
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
            'skip' => [
                'required',
                'numeric',
                'integer',
            ],
            'take' => [
                'required',
                'numeric',
                'integer',
            ],
            'totalItems' => [
                'required',
                'numeric',
                'integer',
            ],
            'items' => [
                'required',
                'array',
            ],
            'items.*' => [
                'required',
                'array',
            ],
            'items.*.requestType' => [
                'required',
                'in:Purchase',
            ],
            'items.*.id' => [
                'required',
                'numeric',
                'integer',
            ],
            'items.*.requestNumber' => [
                'required',
                'numeric',
                'integer',
            ],
            'items.*.name' => [
                'required',
                'string',
            ],
            'items.*.status' => [
                'required',
                'string',
                'in:Approved,Unapproved,Canceled,Denied',
            ],
            'items.*.currentStepName' => [
                'required',
                'string',
            ],
            'items.*.organizationName' => [
                'required',
                'string',
                'in:RoboJackets',
            ],
            'items.*.submittedByName' => [
                'required',
                'string',
            ],
            'items.*.submittedAmount' => [
                'required',
                'numeric',
            ],
            'items.*.submittedOn' => [
                'required',
                'string',
                'date',
            ],
            'items.*.approvedAmount' => [
                'present',
                'nullable',
                'numeric',
            ],
            'items.*.deletedOn' => [
                'present',
                'nullable',
                'string',
                'date',
            ],
        ];
    }
}
