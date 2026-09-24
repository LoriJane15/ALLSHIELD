<?php

namespace App\Http\Requests\Ib39;

use App\Models\Ib39FeaProcessing;
use Illuminate\Foundation\Http\FormRequest;

class IndexFeaProcessingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Ib39FeaProcessing::class) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
