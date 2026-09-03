<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'customer_name')) {
                $table->string('customer_name', 150)->nullable()->after('customer_id');
            }
            if (! Schema::hasColumn('invoices', 'customer_phone')) {
                $table->string('customer_phone', 30)->nullable()->after('customer_name');
            }
            if (! Schema::hasColumn('invoices', 'customer_email')) {
                $table->string('customer_email', 190)->nullable()->after('customer_phone');
            }
            if (! Schema::hasColumn('invoices', 'customer_address')) {
                $table->string('customer_address', 500)->nullable()->after('customer_email');
            }
            if (! Schema::hasColumn('invoices', 'customer_pan_number')) {
                $table->string('customer_pan_number', 30)->nullable()->after('customer_address');
            }
        });

        // Snapshot existing customer details so old invoices remain printable even if
        // the customer record changes later. Invoices without a saved customer retain
        // a neutral walk-in label.
        DB::table('invoices')->orderBy('id')->chunkById(200, function ($invoices): void {
            foreach ($invoices as $invoice) {
                $customer = $invoice->customer_id
                    ? DB::table('customers')->where('id', $invoice->customer_id)->first()
                    : null;

                DB::table('invoices')->where('id', $invoice->id)->update([
                    'customer_name' => $customer?->name ?? 'Walk-in Customer',
                    'customer_phone' => $customer?->phone,
                    'customer_email' => $customer?->email,
                    'customer_address' => $customer?->address,
                    'customer_pan_number' => $customer?->pan_number,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('invoices', 'customer_name') ? 'customer_name' : null,
                Schema::hasColumn('invoices', 'customer_phone') ? 'customer_phone' : null,
                Schema::hasColumn('invoices', 'customer_email') ? 'customer_email' : null,
                Schema::hasColumn('invoices', 'customer_address') ? 'customer_address' : null,
                Schema::hasColumn('invoices', 'customer_pan_number') ? 'customer_pan_number' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
