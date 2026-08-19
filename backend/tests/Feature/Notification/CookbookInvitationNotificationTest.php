<?php

use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function invitationNotificationToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

function invitationNotificationCookbook(User $owner): Cookbook
{
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    return $cookbook;
}

it('persists an invitation notification for the recipient but not the inviter', function (): void {
    Mail::fake();
    $inviter = User::factory()->create(['password' => 'password123']);
    $recipient = User::factory()->create(['password' => 'password123']);
    $cookbook = invitationNotificationCookbook($inviter);

    $this->withToken(invitationNotificationToken($inviter))
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/invitations', [
            'email' => $recipient->email,
            'role' => 'editor',
        ])
        ->assertCreated();

    $data = $recipient->fresh()->unreadNotifications->firstOrFail()->data;
    expect($recipient->fresh()->unreadNotifications)->toHaveCount(1)
        ->and($data['type'])->toBe('cookbook_invitation')
        ->and($data['status'])->toBe('pending')
        ->and($data['invitation'])->toMatchArray([
            'role' => 'editor',
            'cookbook' => ['id' => $cookbook->public_id, 'name' => $cookbook->name],
        ])
        ->and($inviter->fresh()->notifications)->toHaveCount(0);
});

it('does not persist an invitation notification when disabled by preference', function (): void {
    Mail::fake();
    $inviter = User::factory()->create(['password' => 'password123']);
    $recipient = User::factory()->create(['password' => 'password123']);
    $cookbook = invitationNotificationCookbook($inviter);
    NotificationPreference::query()->create([
        'user_id' => $recipient->id,
        'type' => 'cookbook_invitation',
        'channel' => 'none',
    ]);

    $this->withToken(invitationNotificationToken($inviter))
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/invitations', [
            'email' => $recipient->email,
            'role' => 'reader',
        ])
        ->assertCreated();

    expect($recipient->fresh()->notifications)->toHaveCount(0);
});

it('notifies the inviter when an invitation is accepted or declined', function (): void {
    Mail::fake();
    $inviter = User::factory()->create(['password' => 'password123']);
    $acceptedUser = User::factory()->create(['password' => 'password123']);
    $declinedUser = User::factory()->create(['password' => 'password123']);
    $cookbook = invitationNotificationCookbook($inviter);
    $inviterToken = invitationNotificationToken($inviter);

    foreach ([$acceptedUser, $declinedUser] as $user) {
        $this->withToken($inviterToken)
            ->postJson('/api/cookbooks/'.$cookbook->public_id.'/invitations', [
                'email' => $user->email,
                'role' => 'reader',
            ])
            ->assertCreated();
    }

    $invitations = CookbookInvitation::query()->latest('id')->get();
    $this->withToken(invitationNotificationToken($acceptedUser))
        ->postJson('/api/invitations/'.$invitations[1]->id.'/accept')
        ->assertOk();
    $this->withToken(invitationNotificationToken($declinedUser))
        ->postJson('/api/invitations/'.$invitations[0]->id.'/decline')
        ->assertOk();

    expect($inviter->fresh()->unreadNotifications->pluck('data.status')->all())
        ->toContain('accepted', 'declined')
        ->and($acceptedUser->fresh()->notifications)->toHaveCount(1)
        ->and($declinedUser->fresh()->notifications)->toHaveCount(1);
});
