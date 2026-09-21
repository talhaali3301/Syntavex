<?php

namespace App\Http\Requests;

use App\Support\ReviewDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewDecisionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(ReviewDecision::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function decision(): ReviewDecision
    {
        return ReviewDecision::from($this->string('decision')->value());
    }

    public function note(): ?string
    {
        $note = trim((string) $this->input('note', ''));

        return $note === '' ? null : $note;
    }
}
