<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /** @var MongoDB\Laravel\Cache\MongoStore */
        $store = Cache::store('mongodb');
        $store->createTTLIndex();
        $store->lock('')->createTTLIndex();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /** @var MongoDB\Laravel\Cache\MongoStore */
        $store = Cache::store('mongodb');
        $store->flush();
        /** @var MongoDB\Laravel\Cache\MongoLock */
        $store
            ->restoreLock('lunash', owner: 'app')
            ->forceRelease();
    }
};
