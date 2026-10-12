<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Services\Admin\PanelAccess;
use Illuminate\Foundation\Http\FormRequest;

final class AdminActAsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && app(PanelAccess::class)->isOwner($user);
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['user_id' => ['required', 'integer', 'exists:users,id'], 'confirmation' => ['required', 'accepted']];
    }
}
