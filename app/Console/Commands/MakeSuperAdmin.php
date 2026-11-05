<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kitobiy:make-super-admin {email : The email of an existing user} {--revoke : Take super admin access away instead}')]
#[Description('Give an existing user super admin access to /admin (or take it away with --revoke)')]
class MakeSuperAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No user with the email {$email}. They need to sign up first.");

            return self::FAILURE;
        }

        $isSuperAdmin = ! $this->option('revoke');
        $user->forceFill(['is_super_admin' => $isSuperAdmin])->save();

        $this->components->info($isSuperAdmin
            ? "{$user->email} is now a super admin."
            : "{$user->email} is no longer a super admin.");

        return self::SUCCESS;
    }
}
