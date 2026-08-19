<?php

use App\Models\Cookbook;
use App\Models\User;
use App\Notifications\NewCookbookMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function preferenceToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('lists both-channel defaults and updates notification preferences by type', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $token = preferenceToken($user);

    $this->withToken($token)
        ->getJson('/api/notifications/preferences')
        ->assertOk()
        ->assertJsonPath('data.0.channel', 'both')
        ->assertJsonCount(4, 'data');

    $this->withToken($token)
        ->putJson('/api/notifications/preferences', [
            'preferences' => [
                ['type' => 'recipe_comment', 'channel' => 'both'],
                ['type' => 'recipe_comment_reply', 'channel' => 'none'],
                ['type' => 'cookbook_message', 'channel' => 'mail'],
            ],
        ])
        ->assertOk()
        ->assertJsonFragment(['type' => 'recipe_comment', 'channel' => 'both'])
        ->assertJsonFragment(['type' => 'recipe_comment_reply', 'channel' => 'none'])
        ->assertJsonFragment(['type' => 'cookbook_message', 'channel' => 'mail']);
});

it('rejects unknown notification types and channels', function (): void {
    $user = User::factory()->create(['password' => 'password123']);

    $this->withToken(preferenceToken($user))
        ->putJson('/api/notifications/preferences', [
            'preferences' => [['type' => 'unknown', 'channel' => 'sms']],
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['preferences.0.type', 'preferences.0.channel']]]]);
});

it('sends only the channels selected for a cookbook message', function (): void {
    Notification::fake();
    $owner = User::factory()->create(['password' => 'password123']);
    $member = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($member, ['role' => 'commenter']);

    $this->withToken(preferenceToken($member))
        ->putJson('/api/notifications/preferences', [
            'preferences' => [['type' => 'cookbook_message', 'channel' => 'mail']],
        ])
        ->assertOk();

    $this->withToken(preferenceToken($owner))
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/messages', ['content' => 'Message par mail'])
        ->assertCreated();

    Notification::assertSentTo($member, NewCookbookMessageNotification::class, function (NewCookbookMessageNotification $notification, array $channels): bool {
        return $channels === ['mail'];
    });
});
