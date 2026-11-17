<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2: Hardware Product & Inventory Management.
 * The products table already exists in the shared Aiven database, so this
 * migration skips itself there. It only builds the table for local and test
 * databases, with the same columns as the live table (checked 18 Nov 2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            return;
        }

        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('sku', 50)->unique();          // e.g. HW-CEM-50KG
            $table->string('name');                       // e.g. YTL Castle Cement 50kg
            $table->string('category', 50)->nullable();   // typed by hand or suggested by the AI Photo Scanner
            $table->text('description')->nullable();      // typed by hand or suggested by the AI Photo Scanner
            $table->string('image_url')->nullable();      // exists in the live table, not used by Module 2 yet

            // Physical hardware only
            $table->enum('unit_type', ['PIECE', 'BAG', 'KG', 'METER', 'BOX', 'TIN', 'ROLL', 'SET']);
            $table->string('bulk_unit_type', 20)->nullable();            // exists in the live table, not used by Module 2 yet
            $table->decimal('bulk_conversion_rate', 10, 2)->nullable();  // exists in the live table, not used by Module 2 yet
            $table->string('lhdn_class_code', 3)->default('022');

            // Dual pricing. Money is always decimal, never float.
            $table->decimal('supplier_cost_price', 10, 2);   // OWNER only
            $table->decimal('retail_selling_price', 10, 2);  // visible to CASHIER
            $table->decimal('bulk_selling_price', 10, 2)->nullable();    // exists in the live table, not used by Module 2 yet

            // Stock
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(10);

            // Soft archive instead of hard delete
            $table->enum('status', ['ACTIVE', 'ARCHIVED'])->default('ACTIVE');

            // Product photo, stored as a path on the public disk, e.g. products/abc.jpg
            $table->string('image_path')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('name');
        });
    }

    public function down(): void
    {
        // Deliberately empty: products lives in the shared database and holds
        // real data, so a rollback must never drop it.
    }
};
