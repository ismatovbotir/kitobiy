<?php

namespace App\Enums;

/**
 * Role of a user inside a library, from most to least rights:
 * - Owner: everything; the library always keeps at least one.
 * - Admin: helps the owner run it: rename, invite, remove and re-role members and viewers.
 * - Member: catalogs books (add, edit, delete copies and tags).
 * - Viewer: only looks at the books (e.g. grandparents, young children).
 */
enum LibraryRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Administrator'),
            self::Member => __('Member'),
            self::Viewer => __('Viewer'),
        };
    }

    /**
     * What this role may do, for the invite form and the members list.
     */
    public function description(): string
    {
        return match ($this) {
            self::Owner => __('Can do everything.'),
            self::Admin => __('Invites and manages people, and catalogs books.'),
            self::Member => __('Adds and edits books.'),
            self::Viewer => __('Only looks at the books.'),
        };
    }

    public function canEditBooks(): bool
    {
        return $this !== self::Viewer;
    }

    public function canManageMembers(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    /**
     * The roles this role may give to others (by invitation or role change).
     * Only owners make admins; nobody makes owners here.
     *
     * @return list<self>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::Owner => [self::Admin, self::Member, self::Viewer],
            self::Admin => [self::Member, self::Viewer],
            default => [],
        };
    }

    /**
     * Whether this role may remove or re-role someone who has the other role.
     */
    public function canManage(self $other): bool
    {
        return in_array($other, $this->assignableRoles(), true);
    }
}
