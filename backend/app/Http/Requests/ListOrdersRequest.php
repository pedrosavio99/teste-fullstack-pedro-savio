<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'affiliate_id' => ['sometimes', 'integer', 'min:1'],
            'status'       => ['sometimes', 'in:pending,approved,cancelled,refunded'],
            'date_from'    => ['sometimes', 'date'],
            'date_to'      => ['sometimes', 'date', 'after_or_equal:date_from'],
            'min_value'    => ['sometimes', 'numeric', 'min:0'],
            'max_value'    => ['sometimes', 'numeric', 'gte:min_value'],
            'sort_by'      => ['sometimes', 'in:id,affiliate_id,status,total_value,ordered_at,created_at'],
            'sort_dir'     => ['sometimes', 'in:asc,desc'],
            'per_page'     => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Devolve só os filtros presentes, já validados.
     */
    public function filters(): array
    {
        return $this->validated();
    }
}