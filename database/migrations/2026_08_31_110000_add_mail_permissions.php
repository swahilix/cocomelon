<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $now = now();
        $permissions = [
            ['View mail', 'mail.view', 'Mail', 'Access assigned business mailboxes and read messages.'],
            ['Compose mail', 'mail.compose', 'Mail', 'Create new messages and save drafts from assigned mailboxes.'],
            ['Send mail', 'mail.send', 'Mail', 'Send queued messages from assigned business mailboxes.'],
            ['Reply to mail', 'mail.reply', 'Mail', 'Reply and reply-all to messages in assigned mailboxes.'],
            ['Forward mail', 'mail.forward', 'Mail', 'Forward messages from assigned mailboxes.'],
            ['Delete mail', 'mail.delete', 'Mail', 'Move mail to Bin and permanently delete where allowed.'],
            ['Restore mail', 'mail.restore', 'Mail', 'Restore messages from Bin.'],
            ['Manage mail contacts', 'mail.manage_contacts', 'Mail', 'Create, edit, group and delete business mail contacts.'],
            ['Manage mailboxes', 'mail.manage_mailboxes', 'Mail', 'Create mailboxes, assign staff and change mailbox access.'],
            ['Manage mail settings', 'mail.manage_settings', 'Mail', 'Change mail transport, signature and synchronization settings.'],
            ['View all mailboxes', 'mail.view_all_mailboxes', 'Mail', 'Access all configured business mailboxes without an individual mailbox assignment.'],
        ];

        foreach ($permissions as [$name, $slug, $group, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'group' => $group,
                    'description' => $description,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $administratorId = DB::table('roles')->where('slug', 'administrator')->value('id');

        if ($administratorId) {
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', collect($permissions)->pluck(1)->all())
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $administratorId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $slugs = [
            'mail.view',
            'mail.compose',
            'mail.send',
            'mail.reply',
            'mail.forward',
            'mail.delete',
            'mail.restore',
            'mail.manage_contacts',
            'mail.manage_mailboxes',
            'mail.manage_settings',
            'mail.view_all_mailboxes',
        ];

        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');

        if (Schema::hasTable('permission_role') && $ids->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        }

        DB::table('permissions')->whereIn('slug', $slugs)->delete();
    }
};
