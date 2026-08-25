<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $mappings = [
            1 => [
                'name' => 'Euralíz',
                'trello_member_id' => '604b988f402e011cb47af102',
                'merge_names' => ['Euralíz Bravo', 'Euraliz Bravo', 'Euraliz'],
                'merge_ids' => [204],
            ],
            2 => [
                'name' => 'Adrián',
                'trello_member_id' => '673f6a10a72ad738f55b5c6a',
                'merge_names' => ['Adrian Jose Reinoza Valera', 'Adrian Reinoza', 'Adrian'],
                'merge_ids' => [202],
            ],
            3 => [
                'name' => 'César',
                'trello_member_id' => '597276cab5d158dd73106f3d',
                'merge_names' => ['Cesar Guzman', 'César Guzmán', 'Cesar'],
                'merge_ids' => [203],
            ],
        ];

        foreach ($mappings as $primaryId => $info) {
            // Ensure primary designer exists and has the correct trello_member_id
            $primary = DB::table('designers')->where('id', $primaryId)->first();
            if ($primary) {
                DB::table('designers')->where('id', $primaryId)->update([
                    'trello_member_id' => $info['trello_member_id'],
                    'active' => true,
                ]);
            }

            // Find all duplicate IDs to merge
            $duplicateIds = DB::table('designers')
                ->where('id', '!=', $primaryId)
                ->where(function ($q) use ($info) {
                    $q->where('trello_member_id', $info['trello_member_id'])
                        ->orWhereIn('id', $info['merge_ids'])
                        ->orWhereIn('name', $info['merge_names']);
                })
                ->pluck('id')
                ->toArray();

            foreach ($duplicateIds as $dupId) {
                // Update orders table
                DB::table('orders')->where('designer_id', $dupId)->update(['designer_id' => $primaryId]);

                // Update related_tasks table
                DB::table('related_tasks')->where('assignee_id', $dupId)->update(['assignee_id' => $primaryId]);

                // Update designer_order pivot table safely
                $pivotRecords = DB::table('designer_order')->where('designer_id', $dupId)->get();
                foreach ($pivotRecords as $rec) {
                    $exists = DB::table('designer_order')
                        ->where('order_id', $rec->order_id)
                        ->where('designer_id', $primaryId)
                        ->exists();

                    if (! $exists) {
                        DB::table('designer_order')
                            ->where('id', $rec->id)
                            ->update(['designer_id' => $primaryId]);
                    } else {
                        DB::table('designer_order')
                            ->where('id', $rec->id)
                            ->delete();
                    }
                }

                // Delete the duplicate designer
                DB::table('designers')->where('id', $dupId)->delete();
            }
        }
    }

    public function down(): void
    {
        // No revert needed for data consolidation
    }
};
