<?php

namespace App\Http\Requests\DDI;

use Illuminate\Foundation\Http\FormRequest;

class AllergyCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Mode A - legacy single-drug lookup: "which drugs cross-react with X?"
            'drug' => 'required_without:medications,allergies|string|max:255',
            'max_results' => 'sometimes|integer|min:1|max:50',
            // Mode B - prescription batch check: "are any of these medications
            // contraindicated given these known allergens?"
            'medications' => 'required_with:allergies|array|min:1|max:100',
            'medications.*' => 'required_with:medications|string|max:255',
            'allergies' => 'required_with:medications|array|min:1|max:100',
            'allergies.*' => 'required_with:allergies|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'drug.required_without' => __('requests.ddi_allergy.drug_required_without'),
            'medications.required_with' => __('requests.ddi_allergy.medications_required_with'),
            'medications.array' => __('requests.ddi_allergy.medications_array'),
            'medications.min' => __('requests.ddi_allergy.medications_min'),
            'allergies.required_with' => __('requests.ddi_allergy.allergies_required_with'),
            'allergies.array' => __('requests.ddi_allergy.allergies_array'),
            'allergies.min' => __('requests.ddi_allergy.allergies_min'),
        ];
    }

    /**
     * Which mode this request is targeting. Used by the controller so the
     * branching stays readable (string tag, no structural guesses later).
     */
    public function mode(): string
    {
        return $this->filled('medications') && $this->filled('allergies')
            ? 'prescription'
            : 'single';
    }
}
