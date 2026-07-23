<?php

namespace App\Support;

final class CookbookPermissions
{
    public const OWNER = 'owner';

    public const EDITOR = 'editor';

    public const READER = 'reader';

    public const COMMENTER = 'commenter';

    public const VIEW = 'view';

    public const UPDATE = 'update';

    public const MANAGE_MEMBERS = 'manage_members';

    public const INVITE_MEMBERS = 'invite_members';

    public const LEAVE = 'leave';

    public const REMOVE_MEMBERS = 'remove_members';

    public const DELETE = 'delete';

    public const COMMENT = 'comment';

    /** @var list<string> */
    public const ROLES = [
        self::OWNER,
        self::EDITOR,
        self::READER,
        self::COMMENTER,
    ];

    /** @var array<string, list<string>> */
    private const MATRIX = [
        self::OWNER => [self::VIEW, self::UPDATE, self::MANAGE_MEMBERS, self::INVITE_MEMBERS, self::LEAVE, self::REMOVE_MEMBERS, self::DELETE, self::COMMENT],
        self::EDITOR => [self::VIEW, self::UPDATE, self::INVITE_MEMBERS, self::LEAVE, self::COMMENT],
        self::READER => [self::VIEW, self::LEAVE],
        self::COMMENTER => [self::VIEW, self::LEAVE, self::COMMENT],
    ];

    public static function allows(?string $role, string $permission): bool
    {
        return is_string($role) && in_array($permission, self::MATRIX[$role] ?? [], true);
    }

    /** @return list<string> */
    public static function permissionsFor(string $role): array
    {
        return self::MATRIX[$role] ?? [];
    }
}
