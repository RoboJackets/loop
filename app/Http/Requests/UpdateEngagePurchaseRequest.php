<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEngagePurchaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): true
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
            'requestType' => [
                'required',
                'string',
                'in:Purchase',
            ],
            'id' => [
                'required',
                'numeric',
                'integer',
            ],
            'requestNumber' => [
                'required',
                'numeric',
                'integer',
            ],
            'subject' => [
                'required',
                'string',
            ],
            'description' => [
                'present',
                'nullable',
                'string',
            ],
            'deletedOn' => [
                'present',
                'nullable',
                'string',
                'date',
            ],
            'status' => [
                'required',
                'string',
                'in:Approved,Unapproved,Canceled,Denied,Completed',
            ],
            'submitted' => [
                'required',
                'array',
            ],
            'submitted.email' => [
                'required',
                'string',
                'email:rfc,strict,dns,spoof,filter',
            ],
            'submitted.date' => [
                'required',
                'string',
                'date',
            ],
            'submitted.amount' => [
                'required',
                'numeric',
            ],
            'approved' => [
                'present',
                'nullable',
                'array',
            ],
            'approved.email' => [
                'string',
                'email:rfc,strict,dns,spoof,filter',
            ],
            'approved.date' => [
                'string',
                'date',
            ],
            'approved.amount' => [
                'numeric',
            ],
            'financeStage' => [
                'required',
                'array',
            ],
            'financeStage.name' => [
                'required',
                'string',
            ],
            'payee' => [
                'required',
                'array',
            ],
            'payee.firstName' => [
                'present',
                'nullable',
                'string',
                'nullable',
            ],
            'payee.lastName' => [
                'present',
                'nullable',
                'string',
                'nullable',
            ],
            'payee.street' => [
                'present',
                'nullable',
                'string',
                'nullable',
            ],
            'payee.street2' => [
                'present',
                'nullable',
                'string',
                'nullable',
            ],
            'payee.city' => [
                'present',
                'nullable',
                'string',
                'nullable',
            ],
            'payee.state' => [
                'present',
                'nullable',
                'string',
                'nullable',
            ],
            'payee.zipCode' => [
                'present',
                'nullable',
                'string',
                'nullable',
            ],
        ];
    }
}
