<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $t) {
            $t->id(); $t->string('code', 20)->unique(); $t->string('name'); $t->string('city', 80); $t->string('district', 80); $t->string('phone', 30)->nullable(); $t->timestamps();
        });
        Schema::create('buildings', function (Blueprint $t) {
            $t->id(); $t->foreignId('hospital_id')->constrained()->cascadeOnDelete(); $t->string('code', 20); $t->string('name'); $t->timestamps(); $t->unique(['hospital_id','code']);
        });
        Schema::create('floors', function (Blueprint $t) {
            $t->id(); $t->foreignId('building_id')->constrained()->cascadeOnDelete(); $t->smallInteger('floor_no'); $t->string('name'); $t->timestamps();
        });
        Schema::create('departments', function (Blueprint $t) {
            $t->id(); $t->foreignId('hospital_id')->constrained()->cascadeOnDelete(); $t->string('code', 20); $t->string('name'); $t->string('type', 40)->default('clinic'); $t->boolean('active')->default(true); $t->timestamps(); $t->unique(['hospital_id','code']);
        });
        Schema::create('rooms', function (Blueprint $t) {
            $t->id(); $t->foreignId('floor_id')->constrained()->cascadeOnDelete(); $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete(); $t->string('room_no', 20); $t->string('type', 40); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('beds', function (Blueprint $t) {
            $t->id(); $t->foreignId('room_id')->constrained()->cascadeOnDelete(); $t->string('bed_no', 20); $t->string('status', 30)->default('available'); $t->timestamps(); $t->unique(['room_id','bed_no']);
        });
        Schema::create('specialities', function (Blueprint $t) {
            $t->id(); $t->string('code', 20)->unique(); $t->string('name'); $t->timestamps();
        });
        Schema::create('staff', function (Blueprint $t) {
            $t->id(); $t->foreignId('hospital_id')->constrained(); $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete(); $t->string('registry_no', 30)->unique(); $t->string('first_name', 80); $t->string('last_name', 80); $t->string('title', 80)->nullable(); $t->string('email')->nullable(); $t->date('started_at'); $t->boolean('active')->default(true); $t->timestamps(); $t->index(['hospital_id','department_id']);
        });
        Schema::create('doctors', function (Blueprint $t) {
            $t->id(); $t->foreignId('staff_id')->unique()->constrained()->cascadeOnDelete(); $t->string('diploma_no', 40)->unique(); $t->string('academic_title', 40)->nullable(); $t->unsignedSmallInteger('appointment_minutes')->default(20); $t->timestamps();
        });
        Schema::create('doctor_specialities', function (Blueprint $t) {
            $t->id(); $t->foreignId('doctor_id')->constrained()->cascadeOnDelete(); $t->foreignId('speciality_id')->constrained()->cascadeOnDelete(); $t->boolean('is_primary')->default(false); $t->date('certified_at')->nullable(); $t->unique(['doctor_id','speciality_id']);
        });
        Schema::create('patients', function (Blueprint $t) {
            $t->id(); $t->uuid('uuid')->unique(); $t->string('national_id', 11)->unique(); $t->string('first_name', 80); $t->string('last_name', 80); $t->date('birth_date'); $t->string('gender', 20); $t->string('blood_type', 5)->nullable(); $t->string('phone', 30)->nullable(); $t->string('email')->nullable(); $t->string('city', 80)->nullable(); $t->timestamp('registered_at'); $t->timestamps(); $t->index(['last_name','first_name']); $t->index('birth_date');
        });
        Schema::create('patient_addresses', function (Blueprint $t) {
            $t->id(); $t->foreignId('patient_id')->constrained()->cascadeOnDelete(); $t->string('type', 20)->default('home'); $t->string('city', 80); $t->string('district', 80); $t->string('postal_code', 10)->nullable(); $t->text('address_line'); $t->boolean('is_primary')->default(true); $t->timestamps();
        });
        Schema::create('patient_contacts', function (Blueprint $t) {
            $t->id(); $t->foreignId('patient_id')->constrained()->cascadeOnDelete(); $t->string('name'); $t->string('relationship', 40); $t->string('phone', 30); $t->boolean('emergency')->default(true); $t->timestamps();
        });
        Schema::create('insurers', function (Blueprint $t) { $t->id(); $t->string('code',20)->unique(); $t->string('name'); $t->string('tax_no',20)->nullable(); $t->timestamps(); });
        Schema::create('insurance_plans', function (Blueprint $t) { $t->id(); $t->foreignId('insurer_id')->constrained(); $t->string('name'); $t->decimal('coverage_rate',5,2); $t->decimal('annual_limit',12,2)->nullable(); $t->timestamps(); });
        Schema::create('patient_insurances', function (Blueprint $t) { $t->id(); $t->foreignId('patient_id')->constrained()->cascadeOnDelete(); $t->foreignId('insurance_plan_id')->constrained(); $t->string('policy_no',50)->unique(); $t->date('starts_at'); $t->date('ends_at')->nullable(); $t->boolean('active')->default(true); $t->timestamps(); });
        Schema::create('allergies', function (Blueprint $t) { $t->id(); $t->string('code',20)->unique(); $t->string('name'); $t->string('category',40); $t->timestamps(); });
        Schema::create('patient_allergies', function (Blueprint $t) { $t->id(); $t->foreignId('patient_id')->constrained()->cascadeOnDelete(); $t->foreignId('allergy_id')->constrained(); $t->string('severity',20); $t->text('reaction')->nullable(); $t->date('identified_at')->nullable(); $t->timestamps(); });
        Schema::create('appointments', function (Blueprint $t) {
            $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('patient_id')->constrained(); $t->foreignId('doctor_id')->constrained(); $t->foreignId('department_id')->constrained(); $t->timestamp('scheduled_at'); $t->string('status',30); $t->string('channel',30)->default('web'); $t->text('reason')->nullable(); $t->timestamp('checked_in_at')->nullable(); $t->timestamp('cancelled_at')->nullable(); $t->timestamps(); $t->index(['doctor_id','scheduled_at']); $t->index(['patient_id','scheduled_at']); $t->index(['status','scheduled_at']);
        });
        Schema::create('appointment_status_history', function (Blueprint $t) { $t->id(); $t->foreignId('appointment_id')->constrained()->cascadeOnDelete(); $t->string('from_status',30)->nullable(); $t->string('to_status',30); $t->text('note')->nullable(); $t->timestamp('changed_at'); });
        Schema::create('visits', function (Blueprint $t) { $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('patient_id')->constrained(); $t->foreignId('doctor_id')->constrained(); $t->foreignId('department_id')->constrained(); $t->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete(); $t->string('visit_type',30); $t->timestamp('started_at'); $t->timestamp('ended_at')->nullable(); $t->text('complaint')->nullable(); $t->string('status',30); $t->timestamps(); $t->index(['patient_id','started_at']); });
        Schema::create('triage_records', function (Blueprint $t) { $t->id(); $t->foreignId('visit_id')->unique()->constrained()->cascadeOnDelete(); $t->smallInteger('priority'); $t->decimal('temperature',4,1)->nullable(); $t->smallInteger('pulse')->nullable(); $t->smallInteger('systolic')->nullable(); $t->smallInteger('diastolic')->nullable(); $t->decimal('oxygen_saturation',5,2)->nullable(); $t->timestamp('measured_at'); });
        Schema::create('icd_codes', function (Blueprint $t) { $t->id(); $t->string('code',12)->unique(); $t->string('name'); $t->string('chapter',120)->nullable(); $t->timestamps(); });
        Schema::create('diagnoses', function (Blueprint $t) { $t->id(); $t->foreignId('visit_id')->constrained()->cascadeOnDelete(); $t->foreignId('icd_code_id')->constrained(); $t->string('type',20)->default('primary'); $t->text('notes')->nullable(); $t->timestamp('diagnosed_at'); $t->index(['icd_code_id','diagnosed_at']); });
        Schema::create('admissions', function (Blueprint $t) { $t->id(); $t->foreignId('patient_id')->constrained(); $t->foreignId('department_id')->constrained(); $t->foreignId('doctor_id')->constrained(); $t->timestamp('admitted_at'); $t->timestamp('discharged_at')->nullable(); $t->string('status',30); $t->text('discharge_summary')->nullable(); $t->timestamps(); });
        Schema::create('admission_beds', function (Blueprint $t) { $t->id(); $t->foreignId('admission_id')->constrained()->cascadeOnDelete(); $t->foreignId('bed_id')->constrained(); $t->timestamp('starts_at'); $t->timestamp('ends_at')->nullable(); $t->timestamps(); });
        Schema::create('lab_test_catalog', function (Blueprint $t) { $t->id(); $t->string('code',30)->unique(); $t->string('name'); $t->string('sample_type',40); $t->string('unit',30)->nullable(); $t->decimal('price',10,2); $t->timestamps(); });
        Schema::create('lab_orders', function (Blueprint $t) { $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('visit_id')->constrained(); $t->foreignId('ordered_by')->constrained('doctors'); $t->string('status',30); $t->string('priority',20)->default('routine'); $t->timestamp('ordered_at'); $t->timestamps(); });
        Schema::create('lab_order_items', function (Blueprint $t) { $t->id(); $t->foreignId('lab_order_id')->constrained()->cascadeOnDelete(); $t->foreignId('lab_test_catalog_id')->constrained('lab_test_catalog'); $t->string('status',30); $t->timestamps(); });
        Schema::create('lab_samples', function (Blueprint $t) { $t->id(); $t->foreignId('lab_order_item_id')->constrained()->cascadeOnDelete(); $t->string('barcode',50)->unique(); $t->timestamp('collected_at')->nullable(); $t->timestamp('received_at')->nullable(); $t->string('status',30); $t->timestamps(); });
        Schema::create('lab_results', function (Blueprint $t) { $t->id(); $t->foreignId('lab_order_item_id')->constrained()->cascadeOnDelete(); $t->string('result_value',120); $t->string('unit',30)->nullable(); $t->decimal('reference_min',12,3)->nullable(); $t->decimal('reference_max',12,3)->nullable(); $t->boolean('abnormal')->default(false); $t->timestamp('resulted_at'); $t->timestamps(); });
        Schema::create('medications', function (Blueprint $t) { $t->id(); $t->string('barcode',30)->unique(); $t->string('generic_name'); $t->string('brand_name'); $t->string('form',40); $t->string('strength',40); $t->decimal('unit_price',10,2); $t->timestamps(); });
        Schema::create('prescriptions', function (Blueprint $t) { $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('visit_id')->constrained(); $t->foreignId('doctor_id')->constrained(); $t->timestamp('prescribed_at'); $t->string('status',30); $t->timestamps(); });
        Schema::create('prescription_items', function (Blueprint $t) { $t->id(); $t->foreignId('prescription_id')->constrained()->cascadeOnDelete(); $t->foreignId('medication_id')->constrained(); $t->string('dose',60); $t->string('frequency',60); $t->unsignedSmallInteger('duration_days'); $t->text('instructions')->nullable(); $t->timestamps(); });
        Schema::create('invoices', function (Blueprint $t) { $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('patient_id')->constrained(); $t->foreignId('visit_id')->nullable()->constrained(); $t->string('invoice_no',40)->unique(); $t->decimal('subtotal',12,2); $t->decimal('discount',12,2)->default(0); $t->decimal('tax',12,2)->default(0); $t->decimal('total',12,2); $t->string('status',30); $t->timestamp('issued_at'); $t->timestamps(); $t->index(['patient_id','issued_at']); });
        Schema::create('invoice_items', function (Blueprint $t) { $t->id(); $t->foreignId('invoice_id')->constrained()->cascadeOnDelete(); $t->string('item_type',40); $t->string('description'); $t->decimal('quantity',10,2); $t->decimal('unit_price',12,2); $t->decimal('total',12,2); $t->timestamps(); });
        Schema::create('payments', function (Blueprint $t) { $t->id(); $t->foreignId('invoice_id')->constrained(); $t->string('reference_no',50)->unique(); $t->decimal('amount',12,2); $t->string('method',30); $t->string('status',30); $t->timestamp('paid_at'); $t->timestamps(); $t->index('paid_at'); });

        $this->createSupportingTables();
    }

    private function createSupportingTables(): void
    {
        $tables = [
            'doctor_schedules'=>['doctor_id','doctors'], 'staff_shifts'=>['staff_id','staff'],
            'clinical_notes'=>['visit_id','visits'], 'vital_signs'=>['visit_id','visits'],
            'procedures'=>['visit_id','visits'], 'procedure_catalog'=>[null,null],
            'radiology_orders'=>['visit_id','visits'], 'radiology_reports'=>['radiology_order_id','radiology_orders'],
            'surgery_cases'=>['admission_id','admissions'], 'surgery_team_members'=>['surgery_case_id','surgery_cases'],
            'anesthesia_records'=>['surgery_case_id','surgery_cases'], 'nursing_notes'=>['admission_id','admissions'],
            'medication_administrations'=>['admission_id','admissions'], 'discharge_medications'=>['admission_id','admissions'],
            'vaccines'=>[null,null], 'patient_vaccinations'=>['patient_id','patients'],
            'warehouses'=>['hospital_id','hospitals'], 'inventory_items'=>['warehouse_id','warehouses'],
            'stock_movements'=>['inventory_item_id','inventory_items'], 'suppliers'=>[null,null],
            'purchase_orders'=>['supplier_id','suppliers'], 'purchase_order_items'=>['purchase_order_id','purchase_orders'],
            'refunds'=>['payment_id','payments'], 'insurance_claims'=>['invoice_id','invoices'],
            'claim_status_history'=>['insurance_claim_id','insurance_claims'], 'service_prices'=>['hospital_id','hospitals'],
            'ambulance_calls'=>['patient_id','patients'], 'referrals'=>['patient_id','patients'],
            'consents'=>['patient_id','patients'], 'documents'=>['patient_id','patients'],
            'audit_logs'=>['staff_id','staff'], 'notifications'=>['patient_id','patients'],
            'training_modules'=>[null,null], 'training_exercises'=>['training_module_id','training_modules'],
            'training_attempts'=>['training_exercise_id','training_exercises'],
        ];
        foreach ($tables as $name => [$foreign, $parent]) {
            Schema::create($name, function (Blueprint $t) use ($foreign, $parent, $name) {
                $t->id();
                if ($foreign) $t->foreignId($foreign)->nullable()->constrained($parent)->nullOnDelete();
                $t->string('code', 50)->nullable();
                $t->string('name')->nullable();
                $t->string('status', 30)->default('active');
                $t->text('description')->nullable();
                $t->jsonb('metadata')->nullable();
                $t->timestamp('occurred_at')->nullable();
                $t->timestamps();
                $t->index(['status','occurred_at'], $name.'_status_time_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (array_reverse([
            'hospitals','buildings','floors','departments','rooms','beds','specialities','staff','doctors','doctor_specialities','patients','patient_addresses','patient_contacts','insurers','insurance_plans','patient_insurances','allergies','patient_allergies','appointments','appointment_status_history','visits','triage_records','icd_codes','diagnoses','admissions','admission_beds','lab_test_catalog','lab_orders','lab_order_items','lab_samples','lab_results','medications','prescriptions','prescription_items','invoices','invoice_items','payments','doctor_schedules','staff_shifts','clinical_notes','vital_signs','procedures','procedure_catalog','radiology_orders','radiology_reports','surgery_cases','surgery_team_members','anesthesia_records','nursing_notes','medication_administrations','discharge_medications','vaccines','patient_vaccinations','warehouses','inventory_items','stock_movements','suppliers','purchase_orders','purchase_order_items','refunds','insurance_claims','claim_status_history','service_prices','ambulance_calls','referrals','consents','documents','audit_logs','notifications','training_modules','training_exercises','training_attempts'
        ]) as $table) Schema::dropIfExists($table);
        Schema::enableForeignKeyConstraints();
    }
};
