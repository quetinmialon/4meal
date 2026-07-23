<?php

use App\Models\Cookbook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function membershipActionToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('allows every non-owner role to leave and protects the owner from leaving', function (string $role, int $expectedStatus): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $member = $role === 'owner' ? $owner : User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Membres', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    if ($member !== $owner) {
        $cookbook->members()->attach($member, ['role' => $role]);
    }

    $this->withToken(membershipActionToken($member))
        ->deleteJson('/api/cookbooks/'.$cookbook->public_id.'/members/me')
        ->assertStatus($expectedStatus);

    $membership = ['cookbook_id' => $cookbook->id, 'user_id' => $member->id];
    if ($expectedStatus === 204) {
        $this->assertDatabaseMissing('cookbook_members', $membership);
    } else {
        $this->assertDatabaseHas('cookbook_members', [...$membership, 'role' => 'owner']);
    }
})->with([
    'owner' => ['owner', 409],
    'editor' => ['editor', 204],
    'reader' => ['reader', 204],
    'commenter' => ['commenter', 204],
]);

it('allows only the owner to remove a non-owner member', function (string $actorRole, int $expectedStatus): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $actor = $actorRole === 'owner' ? $owner : User::factory()->create(['password' => 'password123']);
    $target = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Membres', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    if ($actor !== $owner) {
        $cookbook->members()->attach($actor, ['role' => $actorRole]);
    }
    $cookbook->members()->attach($target, ['role' => 'reader']);

    $this->withToken(membershipActionToken($actor))
        ->deleteJson('/api/cookbooks/'.$cookbook->public_id.'/members/'.$target->id)
        ->assertStatus($expectedStatus);

    $membership = ['cookbook_id' => $cookbook->id, 'user_id' => $target->id];
    if ($expectedStatus === 204) {
        $this->assertDatabaseMissing('cookbook_members', $membership);
    } else {
        $this->assertDatabaseHas('cookbook_members', [...$membership, 'role' => 'reader']);
    }
})->with([
    'owner' => ['owner', 204],
    'editor' => ['editor', 403],
    'reader' => ['reader', 403],
    'commenter' => ['commenter', 403],
]);

it('does not allow the owner to be removed', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Membres', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    $this->withToken(membershipActionToken($owner))
        ->deleteJson('/api/cookbooks/'.$cookbook->public_id.'/members/'.$owner->id)
        ->assertStatus(409);
});

it('returns not found when trying to remove a non-member', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $external = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Membres', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    $this->withToken(membershipActionToken($owner))
        ->deleteJson('/api/cookbooks/'.$cookbook->public_id.'/members/'.$external->id)
        ->assertNotFound();
});
