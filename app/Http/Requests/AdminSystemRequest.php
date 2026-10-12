<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Services\Admin\PanelAccess;
use Illuminate\Foundation\Http\FormRequest;

final class AdminSystemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && app(PanelAccess::class)->isOwner($user);
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['recipient_id' => ['required', 'integer', 'exists:users,id'], 'description' => ['required', 'string', 'max:2000'], 'operation_key' => ['required', 'uuid'], 'confirmation' => ['required', 'accepted']];
    }
}
