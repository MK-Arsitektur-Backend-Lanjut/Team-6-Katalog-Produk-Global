<?php

namespace App\Repositories\Eloquent\Auth;

use App\Models\User;
use App\Repositories\Contracts\Auth\UserRepositoryInterface;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }
}
