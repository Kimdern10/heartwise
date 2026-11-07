<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bell notifications saved before page addresses used random codes still
 * link to /messages/23 or /people/149. Point them at the new addresses.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notifications')
            ->where('data', 'like', '%/messages/%')
            ->orWhere('data', 'like', '%/people/%')
            ->orderBy('id')
            ->each(function ($row) {
                $data = json_decode($row->data, true);
                $url = $data['url'] ?? null;

                if (! is_string($url) || ! preg_match('#/(messages|people)/(\d+)(?=$|[/?\#])#', $url, $m)) {
                    return;
                }

                $publicId = DB::table($m[1] === 'messages' ? 'matches' : 'users')->where('id', $m[2])->value('public_id');

                if ($publicId) {
                    $data['url'] = str_replace("/{$m[1]}/{$m[2]}", "/{$m[1]}/{$publicId}", $url);
                    DB::table('notifications')->where('id', $row->id)->update(['data' => json_encode($data)]);
                }
            });
    }

    public function down(): void
    {
        // Nothing to undo: the old numbered links no longer work.
    }
};
