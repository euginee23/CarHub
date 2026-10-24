<?php

namespace App\Livewire\Forms;

use App\Enums\DocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

class CompanyApplicationForm extends Form
{
    public string $company_name = '';

    public string $business_address = '';

    public ?TemporaryUploadedFile $business_permit = null;

    public string $representative_id_type = 'national_id';

    public ?TemporaryUploadedFile $representative_id = null;

    public string $notes = '';

    /**
     * Get the validation rules for a rental company application.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rules(): array
    {
        $documentRules = ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];

        return [
            'company_name' => ['required', 'string', 'max:120'],
            'business_address' => ['required', 'string', 'max:255'],
            'business_permit' => $documentRules,
            'representative_id_type' => ['required', Rule::in(array_map(fn (DocumentType $type) => $type->value, DocumentType::governmentIds()))],
            'representative_id' => $documentRules,
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'company_name' => __('company name'),
            'business_address' => __('business address'),
            'business_permit' => __('business permit'),
            'representative_id_type' => __('ID type'),
            'representative_id' => __('representative\'s ID'),
        ];
    }
}
