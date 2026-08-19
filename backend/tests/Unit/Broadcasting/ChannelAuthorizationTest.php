<?php

use App\Broadcasting\CookbookChannel;
use App\Broadcasting\UserChannel;
use App\Models\Cookbook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function broadcastingUser(string $email): User
{
    return User::query()->create([
        'name' => 'Broadcasting user',
        'email' => $email,
        'email_verified_at' => now(),
        'password_hash' => Hash::make('password'),
    ]);
}

it('only authorizes a user channel for the authenticated user', function (): void {
    $user = broadcastingUser('broadcast-user@example.test');
    $otherUser = broadcastingUser('broadcast-other@example.test');
    $channel = new UserChannel;

    expect($channel->join($user, (string) $user->getKey()))->toBeTrue()
        ->and($channel->join($user, (string) $otherUser->getKey()))->toBeFalse();
});

it('only authorizes cookbook channels for cookbook members', function (): void {
    $member = broadcastingUser('broadcast-member@example.test');
    $nonMember = broadcastingUser('broadcast-non-member@example.test');
    $cookbook = Cookbook::query()->create([
        'owner_id' => $member->getKey(),
        'name' => 'Broadcasting cookbook',
    ]);
    $cookbook->members()->attach($member, [
        'role' => 'reader',
        'joined_at' => now(),
    ]);
    $channel = new CookbookChannel;

    expect($channel->join($member, (string) $cookbook->public_id))->toBeTrue()
        ->and($channel->join($nonMember, (string) $cookbook->public_id))->toBeFalse()
        ->and($channel->join($member, 'missing-cookbook'))->toBeFalse();
});
