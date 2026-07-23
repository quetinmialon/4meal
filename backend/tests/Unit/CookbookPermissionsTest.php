<?php

use App\Support\CookbookPermissions;

it('enforces every cell of the cookbook permission matrix', function (string $role, array $expected): void {
    foreach ($expected as $permission => $allowed) {
        expect(CookbookPermissions::allows($role, $permission))->toBe($allowed);
    }
})->with([
    'owner' => [CookbookPermissions::OWNER, [
        CookbookPermissions::VIEW => true,
        CookbookPermissions::UPDATE => true,
        CookbookPermissions::MANAGE_MEMBERS => true,
        CookbookPermissions::INVITE_MEMBERS => true,
        CookbookPermissions::LEAVE => true,
        CookbookPermissions::REMOVE_MEMBERS => true,
        CookbookPermissions::DELETE => true,
        CookbookPermissions::COMMENT => true,
    ]],
    'editor' => [CookbookPermissions::EDITOR, [
        CookbookPermissions::VIEW => true,
        CookbookPermissions::UPDATE => true,
        CookbookPermissions::MANAGE_MEMBERS => false,
        CookbookPermissions::INVITE_MEMBERS => true,
        CookbookPermissions::LEAVE => true,
        CookbookPermissions::REMOVE_MEMBERS => false,
        CookbookPermissions::DELETE => false,
        CookbookPermissions::COMMENT => true,
    ]],
    'reader' => [CookbookPermissions::READER, [
        CookbookPermissions::VIEW => true,
        CookbookPermissions::UPDATE => false,
        CookbookPermissions::MANAGE_MEMBERS => false,
        CookbookPermissions::INVITE_MEMBERS => false,
        CookbookPermissions::LEAVE => true,
        CookbookPermissions::REMOVE_MEMBERS => false,
        CookbookPermissions::DELETE => false,
        CookbookPermissions::COMMENT => false,
    ]],
    'commenter' => [CookbookPermissions::COMMENTER, [
        CookbookPermissions::VIEW => true,
        CookbookPermissions::UPDATE => false,
        CookbookPermissions::MANAGE_MEMBERS => false,
        CookbookPermissions::INVITE_MEMBERS => false,
        CookbookPermissions::LEAVE => true,
        CookbookPermissions::REMOVE_MEMBERS => false,
        CookbookPermissions::DELETE => false,
        CookbookPermissions::COMMENT => true,
    ]],
]);
