<?php

use App\Models\Cookbook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function notificationToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('creates a database notification for every other cookbook member', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $member = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($member, ['role' => 'commenter']);

    $this->withToken(notificationToken($owner))
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/messages', ['content' => 'Nouveau message'])
        ->assertCreated();

    expect($owner->fresh()->notifications)->toHaveCount(0)
        ->and($member->fresh()->notifications)->toHaveCount(1)
        ->and($member->fresh()->unreadNotifications->first()->data)->toMatchArray([
            'type' => 'cookbook_message',
            'cookbook' => ['id' => $cookbook->public_id, 'name' => $cookbook->name],
            'sender' => ['id' => $owner->id, 'name' => $owner->name],
        ]);
});

it('lists only the authenticated user notifications and marks one as read', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $member = User::factory()->create(['password' => 'password123']);
    $external = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($member, ['role' => 'commenter']);

    $this->withToken(notificationToken($owner))
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/messages', ['content' => 'À lire'])
        ->assertCreated();

    $notification = $member->fresh()->unreadNotifications->first();

    $response = $this->withToken(notificationToken($member))
        ->getJson('/api/notifications?per_page=10')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $notification->id)
        ->assertJsonPath('data.0.type', 'cookbook_message')
        ->assertJsonPath('data.0.read_at', null)
        ->assertJsonPath('meta.unread_count', 1);

    expect($response->json('data.0.data.message.preview'))->toBe('À lire');

    $this->withToken(notificationToken($member))
        ->patchJson('/api/notifications/'.$notification->id.'/read')
        ->assertOk()
        ->assertJsonPath('data.read_at', fn ($value): bool => is_string($value));

    $this->withToken(notificationToken($member))
        ->patchJson('/api/notifications/'.$notification->id.'/read')
        ->assertOk();

    expect($member->fresh()->unreadNotifications)->toHaveCount(0)
        ->and($external->fresh()->notifications)->toHaveCount(0);
});

it('returns not found when marking another user notification as read', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $member = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($member, ['role' => 'commenter']);

    $this->withToken(notificationToken($owner))
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/messages', ['content' => 'Privé'])
        ->assertCreated();

    $notification = $member->fresh()->notifications->first();

    $this->withToken(notificationToken($owner))
        ->patchJson('/api/notifications/'.$notification->id.'/read')
        ->assertNotFound();
});
