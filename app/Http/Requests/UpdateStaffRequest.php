<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateStaffRequest extends FormRequest
{
    /** Access is already restricted to OWNER by the route's role middleware. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $staff = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($staff->id)],
            'role' => ['required', Rule::in(User::ROLES)],
            'status' => ['required', Rule::in(User::STATUSES)],
            // Leave blank to keep the current password.
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    /**
     * An Owner may not demote or deactivate their own account
     * (this also guarantees the shop always keeps at least one active Owner).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $staff = $this->route('user');

                if ($staff->is($this->user())
                    && ($this->input('role') !== $staff->role || $this->input('status') !== $staff->status)) {
                    $validator->errors()->add('role', 'You cannot change your own role or status.');
                }
            },
        ];
    }
}
