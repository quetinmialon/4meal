<?php

use App\Mail\CookbookInvitationMail;
use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function invitationToken(): string
{
    $token = null;
    Mail::assertSent(CookbookInvitationMail::class, function (CookbookInvitationMail $mail) use (&$token): bool {
        $token = $mail->token;

        return true;
    });

    return $token;
}

function invitationAuthToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('creates an email invitation with a hashed token and proposed role', function () {
    Mail::fake();
    $owner = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Famille', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    $response = $this->withToken(invitationAuthToken($owner))->postJson(
        '/api/cookbooks/'.$cookbook->public_id.'/invitations',
        ['email' => 'Invite@Example.test', 'role' => 'editor'],
    );

    $response->assertCreated()
        ->assertJsonPath('data.email', 'invite@example.test')
        ->assertJsonPath('data.role', 'editor')
        ->assertJsonMissingPath('data.token_hash');

    $invitation = CookbookInvitation::query()->firstOrFail();
    expect($invitation->token_hash)->toHaveLength(64)
        ->and($invitation->expires_at->isFuture())->toBeTrue();
    expect(invitationToken())->not->toBe($invitation->token_hash);
    Mail::assertSent(CookbookInvitationMail::class, fn (CookbookInvitationMail $mail) => $mail->hasTo('invite@example.test'));
});

it('rejects inviting an already active member', function () {
    Mail::fake();
    $owner = User::factory()->create(['password' => 'password123']);
    $member = User::factory()->create(['email' => 'member@example.test']);
    $cookbook = Cookbook::query()->create(['name' => 'Famille', 'owner_id' => $owner->id]);
    $cookbook->members()->attach([$owner->id => ['role' => 'owner'], $member->id => ['role' => 'viewer']]);

    $this->withToken(invitationAuthToken($owner))
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/invitations', [
            'email' => $member->email, 'role' => 'editor',
        ])
        ->assertStatus(409);

    Mail::assertNothingSent();
});

it('allows consulting a valid invitation without authentication', function () {
    Mail::fake();
    $owner = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Famille', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $this->withToken(invitationAuthToken($owner))->postJson('/api/cookbooks/'.$cookbook->public_id.'/invitations', [
        'email' => 'invite@example.test', 'role' => 'viewer',
    ])->assertCreated();

    $this->getJson('/api/invitations/'.invitationToken())
        ->assertOk()
        ->assertJsonPath('data.cookbook.name', 'Famille')
        ->assertJsonPath('data.role', 'viewer');
});

it('accepts an invitation once for the invited account and creates active membership', function () {
    Mail::fake();
    $owner = User::factory()->create(['password' => 'password123']);
    $invitee = User::factory()->create(['email' => 'invite@example.test', 'password' => 'password123']);
    $secondInvitee = User::factory()->create(['email' => 'second@example.test', 'password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Famille', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $this->withToken(invitationAuthToken($owner))->postJson('/api/cookbooks/'.$cookbook->public_id.'/invitations', [
        'email' => $invitee->email, 'role' => 'viewer',
    ])->assertCreated();
    $token = invitationToken();

    $this->withToken(invitationAuthToken($invitee))->postJson('/api/invitations/token/'.$token.'/accept')
        ->assertOk()->assertJsonPath('data.cookbook.role', 'viewer');
    $this->assertDatabaseHas('cookbook_members', [
        'cookbook_id' => $cookbook->id, 'user_id' => $invitee->id, 'role' => 'viewer',
    ]);
    $this->assertDatabaseHas('cookbook_invitations', ['accepted_by' => $invitee->id]);

    $this->withToken(invitationAuthToken($invitee))->postJson('/api/invitations/token/'.$token.'/accept')
        ->assertStatus(410);
});

it('rejects expired invitations and a different email account', function () {
    Mail::fake();
    $owner = User::factory()->create(['password' => 'password123']);
    $invitee = User::factory()->create(['email' => 'invite@example.test', 'password' => 'password123']);
    $other = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Famille', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $this->withToken(invitationAuthToken($owner))->postJson('/api/cookbooks/'.$cookbook->public_id.'/invitations', [
        'email' => $invitee->email, 'role' => 'viewer',
    ])->assertCreated();
    $token = invitationToken();

    $this->withToken(invitationAuthToken($other))->postJson('/api/invitations/token/'.$token.'/accept')
        ->assertUnauthorized();
    CookbookInvitation::query()->update(['expires_at' => now()->subMinute()]);
    $this->getJson('/api/invitations/'.$token)->assertStatus(410);
});

it('lists only pending invitations for the authenticated email', function () {
    Mail::fake();
    $owner = User::factory()->create(['password' => 'password123']);
    $invitee = User::factory()->create(['email' => 'invite@example.test', 'password' => 'password123']);
    $other = User::factory()->create();
    $cookbook = Cookbook::query()->create(['name' => 'Famille', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    $create = fn (string $email) => $this->withToken(invitationAuthToken($owner))->postJson('/api/cookbooks/'.$cookbook->public_id.'/invitations', [
        'email' => $email, 'role' => 'viewer',
    ])->assertCreated();
    $create($invitee->email);
    $create($other->email);
    CookbookInvitation::query()->where('email', $other->email)->update(['declined_at' => now()]);

    $this->withToken(invitationAuthToken($invitee))->getJson('/api/invitations')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', $invitee->email);
});

it('accepts and declines listed invitations by id without exposing the raw token', function () {
    Mail::fake();
    $owner = User::factory()->create(['password' => 'password123']);
    $invitee = User::factory()->create(['email' => 'invite@example.test', 'password' => 'password123']);
    $secondInvitee = User::factory()->create(['email' => 'second@example.test', 'password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Famille', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    $this->withToken(invitationAuthToken($owner))->postJson('/api/cookbooks/'.$cookbook->public_id.'/invitations', [
        'email' => $invitee->email, 'role' => 'editor',
    ])->assertCreated();
    $invitation = CookbookInvitation::query()->firstOrFail();

    $this->withToken(invitationAuthToken($invitee))->postJson('/api/invitations/'.$invitation->id.'/accept')
        ->assertOk()->assertJsonPath('data.cookbook.role', 'editor');
    $this->assertDatabaseHas('cookbook_members', ['cookbook_id' => $cookbook->id, 'user_id' => $invitee->id, 'role' => 'editor']);

    $this->withToken(invitationAuthToken($owner))->postJson('/api/cookbooks/'.$cookbook->public_id.'/invitations', [
        'email' => $secondInvitee->email, 'role' => 'viewer',
    ])->assertCreated();
    $second = CookbookInvitation::query()->latest('id')->firstOrFail();
    $this->withToken(invitationAuthToken($secondInvitee))->postJson('/api/invitations/'.$second->id.'/decline')
        ->assertOk()->assertJsonPath('data.declined_at', fn ($value) => is_string($value));
    $this->assertDatabaseHas('cookbook_invitations', ['id' => $second->id, 'declined_by' => $secondInvitee->id]);
});
