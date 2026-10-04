<?php

namespace App\Console\Commands;

use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateHospitalData extends Command
{
    protected $signature = 'hbys:seed {profile=small : small, medium veya full}';
    protected $description = 'Türkçe ve ilişkisel HBYS eğitim verisi üretir';

    public function handle(): int
    {
        $profiles = [
            'small' => ['patients' => 500, 'appointments' => 1800],
            'medium' => ['patients' => 25000, 'appointments' => 100000],
            'full' => ['patients' => 500000, 'appointments' => 1250000],
        ];
        $profile = (string) $this->argument('profile');
        if (! isset($profiles[$profile])) {
            $this->error('Profil small, medium veya full olmalı.');
            return self::FAILURE;
        }

        $faker = Factory::create('tr_TR');
        $faker->seed(20261003);
        $this->seedReferenceData($faker);
        $this->seedPatients($faker, $profiles[$profile]['patients']);
        $this->seedPatientDetails($faker, $profiles[$profile]['patients']);
        $this->seedAppointments($faker, $profiles[$profile]['appointments']);
        $clinicalTarget = $profile === 'small' ? 350 : ($profile === 'medium' ? 20000 : 250000);
        $this->seedClinicalAndBilling($faker, $clinicalTarget);
        $this->seedClinicalExtras($faker, $clinicalTarget);
        DB::statement('ANALYZE');
        $this->info("HBYS {$profile} veri seti hazır.");
        return self::SUCCESS;
    }

    private function seedReferenceData($faker): void
    {
        if (DB::table('hospitals')->exists()) return;

        DB::table('hospitals')->insert([
            ['code'=>'IST-01','name'=>'Marmara Eğitim ve Araştırma Hastanesi','city'=>'İstanbul','district'=>'Kadıköy','phone'=>'0216 555 10 10','created_at'=>now(),'updated_at'=>now()],
            ['code'=>'ANK-01','name'=>'Anadolu Şehir Hastanesi','city'=>'Ankara','district'=>'Çankaya','phone'=>'0312 555 20 20','created_at'=>now(),'updated_at'=>now()],
            ['code'=>'IZM-01','name'=>'Ege Sağlık Kampüsü','city'=>'İzmir','district'=>'Bornova','phone'=>'0232 555 30 30','created_at'=>now(),'updated_at'=>now()],
        ]);
        $departments = ['Acil Servis','Kardiyoloji','İç Hastalıkları','Çocuk Sağlığı','Ortopedi','Nöroloji','Genel Cerrahi','Kadın Hastalıkları','Göğüs Hastalıkları','Dermatoloji','Göz Hastalıkları','Kulak Burun Boğaz','Psikiyatri','Üroloji','Enfeksiyon Hastalıkları'];
        $specialities = [];
        foreach ($departments as $i => $name) {
            $specialities[] = ['code'=>'UZM-'.str_pad((string)($i+1),3,'0',STR_PAD_LEFT),'name'=>$name,'created_at'=>now(),'updated_at'=>now()];
            foreach ([1,2,3] as $hospitalId) DB::table('departments')->insert(['hospital_id'=>$hospitalId,'code'=>'K-'.($i+1),'name'=>$name,'type'=>$i===0?'emergency':'clinic','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        }
        DB::table('specialities')->insert($specialities);
        foreach ([1,2,3] as $hospitalId) {
            $buildingId = DB::table('buildings')->insertGetId(['hospital_id'=>$hospitalId,'code'=>'ANA','name'=>'Ana Bina','created_at'=>now(),'updated_at'=>now()]);
            foreach (range(0,4) as $floorNo) {
                $floorId = DB::table('floors')->insertGetId(['building_id'=>$buildingId,'floor_no'=>$floorNo,'name'=>$floorNo===0?'Zemin Kat':$floorNo.'. Kat','created_at'=>now(),'updated_at'=>now()]);
                foreach (range(1,4) as $roomNo) {
                    $roomId = DB::table('rooms')->insertGetId(['floor_id'=>$floorId,'department_id'=>(($hospitalId-1)*15)+(($floorNo*4+$roomNo-1)%15)+1,'room_no'=>$floorNo.str_pad((string)$roomNo,2,'0',STR_PAD_LEFT),'type'=>$floorNo===4?'inpatient':'examination','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
                    foreach ([1,2] as $bedNo) DB::table('beds')->insert(['room_id'=>$roomId,'bed_no'=>(string)$bedNo,'status'=>'available','created_at'=>now(),'updated_at'=>now()]);
                }
            }
        }
        foreach (range(1,90) as $i) {
            $hospitalId = (($i-1)%3)+1; $departmentId = (($hospitalId-1)*15)+(($i-1)%15)+1;
            $staffId = DB::table('staff')->insertGetId(['hospital_id'=>$hospitalId,'department_id'=>$departmentId,'registry_no'=>'DR-'.str_pad((string)$i,5,'0',STR_PAD_LEFT),'first_name'=>$faker->firstName(),'last_name'=>$faker->lastName(),'title'=>'Uzman Doktor','email'=>'doktor'.$i.'@hbys.local','started_at'=>$faker->dateTimeBetween('-20 years','-1 year')->format('Y-m-d'),'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
            $doctorId = DB::table('doctors')->insertGetId(['staff_id'=>$staffId,'diploma_no'=>'DIP-'.str_pad((string)$i,6,'0',STR_PAD_LEFT),'academic_title'=>$i%5===0?'Doç. Dr.':'Uzm. Dr.','appointment_minutes'=>20,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('doctor_specialities')->insert(['doctor_id'=>$doctorId,'speciality_id'=>(($i-1)%15)+1,'is_primary'=>true,'certified_at'=>$faker->dateTimeBetween('-15 years','-1 year')->format('Y-m-d')]);
        }
        DB::table('insurers')->insert([
            ['code'=>'SGK','name'=>'Sosyal Güvenlik Kurumu','created_at'=>now(),'updated_at'=>now()],
            ['code'=>'OZL-1','name'=>'Güvence Sağlık Sigorta','created_at'=>now(),'updated_at'=>now()],
            ['code'=>'OZL-2','name'=>'Birlik Hayat ve Sağlık','created_at'=>now(),'updated_at'=>now()],
        ]);
        foreach (range(1,3) as $i) DB::table('insurance_plans')->insert(['insurer_id'=>$i,'name'=>$i===1?'Genel Sağlık Sigortası':'Tamamlayıcı Sağlık','coverage_rate'=>$i===1?80:95,'annual_limit'=>$i===1?null:100000,'created_at'=>now(),'updated_at'=>now()]);
        foreach ([['J30','Polen alerjisi','çevresel'],['Z88.0','Penisilin alerjisi','ilaç'],['T78.1','Gıda alerjisi','gıda'],['L23','Lateks alerjisi','temas']] as $a) DB::table('allergies')->insert(['code'=>$a[0],'name'=>$a[1],'category'=>$a[2],'created_at'=>now(),'updated_at'=>now()]);
        foreach ([['I10','Esansiyel hipertansiyon'],['E11','Tip 2 diyabet'],['J06.9','Akut üst solunum yolu enfeksiyonu'],['M54.5','Bel ağrısı'],['R51','Baş ağrısı'],['K21','Gastroözofageal reflü'],['J45','Astım'],['N39','İdrar yolu enfeksiyonu']] as $c) DB::table('icd_codes')->insert(['code'=>$c[0],'name'=>$c[1],'chapter'=>'Örnek ICD-10 kodları','created_at'=>now(),'updated_at'=>now()]);
        foreach ([['HEM','Hemogram','EDTA kan','10^9/L',165],['GLU','Glukoz','Serum','mg/dL',90],['CRP','C-Reaktif Protein','Serum','mg/L',125],['TSH','Tiroid Stimülan Hormon','Serum','mIU/L',210]] as $x) DB::table('lab_test_catalog')->insert(['code'=>$x[0],'name'=>$x[1],'sample_type'=>$x[2],'unit'=>$x[3],'price'=>$x[4],'created_at'=>now(),'updated_at'=>now()]);
        foreach (range(1,12) as $i) DB::table('medications')->insert(['barcode'=>'869'.str_pad((string)$i,10,'0',STR_PAD_LEFT),'generic_name'=>['Parasetamol','Amoksisilin','İbuprofen','Metformin'][$i%4],'brand_name'=>'Medika '.$i,'form'=>['Tablet','Kapsül','Şurup'][$i%3],'strength'=>['500 mg','250 mg','100 mg/5 ml'][$i%3],'unit_price'=>25+$i*7,'created_at'=>now(),'updated_at'=>now()]);
    }

    private function seedPatients($faker, int $target): void
    {
        $current = (int) DB::table('patients')->count();
        if ($current >= $target) return;
        $this->info('Hastalar üretiliyor: '.number_format($target, 0, ',', '.'));
        for ($offset=$current+1; $offset <= $target; $offset += 1000) {
            $rows=[]; $end=min($offset+999,$target);
            for ($i=$offset; $i<=$end; $i++) {
                $gender=$i%2?'female':'male'; $registered=$faker->dateTimeBetween('-8 years','now');
                $rows[]=['uuid'=>(string)Str::uuid(),'national_id'=>(string)(10000000000+$i),'first_name'=>$gender==='female'?$faker->firstNameFemale():$faker->firstNameMale(),'last_name'=>$faker->lastName(),'birth_date'=>$faker->dateTimeBetween('-95 years','-1 year')->format('Y-m-d'),'gender'=>$gender,'blood_type'=>['A+','A-','B+','B-','AB+','0+','0-'][$i%7],'phone'=>$faker->phoneNumber(),'email'=>$i%4===0?null:'hasta'.$i.'@example.test','city'=>$faker->city(),'registered_at'=>$registered,'created_at'=>$registered,'updated_at'=>$registered];
            }
            DB::table('patients')->insert($rows);
            if ($target > 50000 && $end%25000===0) $this->output->write('.');
        }
        $this->newLine();
    }

    private function seedAppointments($faker, int $target): void
    {
        $current=(int)DB::table('appointments')->count(); if ($current >= $target) return;
        $patients=(int)DB::table('patients')->count();
        $statuses=['completed','completed','completed','scheduled','cancelled','no_show'];
        $this->info('Randevular üretiliyor: '.number_format($target,0,',','.'));
        for ($offset=$current+1; $offset<=$target; $offset+=1000) {
            $rows=[]; $end=min($offset+999,$target);
            for ($i=$offset;$i<=$end;$i++) { $doctorId=(($i-1)%90)+1; $hospitalId=(($doctorId-1)%3)+1; $scheduled=$faker->dateTimeBetween('-5 years','+90 days'); $status=$statuses[$i%count($statuses)];
                $rows[]=['uuid'=>(string)Str::uuid(),'patient_id'=>(($i*37)%$patients)+1,'doctor_id'=>$doctorId,'department_id'=>(($hospitalId-1)*15)+(($doctorId-1)%15)+1,'scheduled_at'=>$scheduled,'status'=>$status,'channel'=>['web','telefon','MHRS','banko'][$i%4],'reason'=>['Kontrol muayenesi','Ağrı şikâyeti','Tetkik sonucu değerlendirme','Rutin tarama'][$i%4],'checked_in_at'=>$status==='completed'?$scheduled:null,'cancelled_at'=>$status==='cancelled'?$scheduled:null,'created_at'=>$scheduled,'updated_at'=>$scheduled];
            } DB::table('appointments')->insert($rows); if ($target>100000 && $end%50000===0) $this->output->write('.');
        } $this->newLine();
    }

    private function seedPatientDetails($faker, int $target): void
    {
        $start=(int)DB::table('patient_addresses')->max('patient_id')+1;
        for ($offset=$start;$offset<=$target;$offset+=1000) {
            $addresses=[]; $insurances=[]; $allergies=[]; $end=min($offset+999,$target);
            for ($i=$offset;$i<=$end;$i++) {
                $addresses[]=['patient_id'=>$i,'type'=>'home','city'=>$faker->city(),'district'=>$faker->citySuffix(),'postal_code'=>(string)random_int(10000,81999),'address_line'=>$faker->streetAddress(),'is_primary'=>true,'created_at'=>now(),'updated_at'=>now()];
                if ($i%3===0) $insurances[]=['patient_id'=>$i,'insurance_plan_id'=>($i%3)+1,'policy_no'=>'POL-'.str_pad((string)$i,11,'0',STR_PAD_LEFT),'starts_at'=>now()->subYears(2)->format('Y-m-d'),'ends_at'=>now()->addYears(2)->format('Y-m-d'),'active'=>true,'created_at'=>now(),'updated_at'=>now()];
                if ($i%10===0) $allergies[]=['patient_id'=>$i,'allergy_id'=>($i%4)+1,'severity'=>['low','medium','high'][$i%3],'reaction'=>'Hasta beyanına göre kayıt edildi.','identified_at'=>now()->subYears($i%8)->format('Y-m-d'),'created_at'=>now(),'updated_at'=>now()];
            }
            DB::table('patient_addresses')->insert($addresses);
            if ($insurances) DB::table('patient_insurances')->insert($insurances);
            if ($allergies) DB::table('patient_allergies')->insert($allergies);
        }
    }

    private function seedClinicalAndBilling($faker, int $target): void
    {
        $current=(int)DB::table('visits')->count(); if ($current >= $target) return;
        $patients=(int)DB::table('patients')->count();
        $appointments=(int)DB::table('appointments')->count();
        for ($offset=$current+1;$offset<=$target;$offset+=500) {
            DB::transaction(function () use ($faker,$offset,$target,$patients,$appointments) {
                $end=min($offset+499,$target);
                for ($i=$offset;$i<=$end;$i++) { $doctorId=(($i-1)%90)+1; $hospitalId=(($doctorId-1)%3)+1; $started=$faker->dateTimeBetween('-4 years','now'); $patientId=(($i*43)%$patients)+1;
                    $visitId=DB::table('visits')->insertGetId(['uuid'=>(string)Str::uuid(),'patient_id'=>$patientId,'doctor_id'=>$doctorId,'department_id'=>(($hospitalId-1)*15)+(($doctorId-1)%15)+1,'appointment_id'=>$i<=$appointments?$i:null,'visit_type'=>$i%9===0?'emergency':'outpatient','started_at'=>$started,'ended_at'=>$started,'complaint'=>['Baş ağrısı ve halsizlik','Kontrol muayenesi','Öksürük ve ateş','Bel ağrısı'][$i%4],'status'=>'completed','created_at'=>$started,'updated_at'=>$started]);
                    DB::table('diagnoses')->insert(['visit_id'=>$visitId,'icd_code_id'=>(($i-1)%8)+1,'type'=>'primary','diagnosed_at'=>$started]);
                    $invoiceId=DB::table('invoices')->insertGetId(['uuid'=>(string)Str::uuid(),'patient_id'=>$patientId,'visit_id'=>$visitId,'invoice_no'=>'F-'.str_pad((string)$i,10,'0',STR_PAD_LEFT),'subtotal'=>500+($i%12)*75,'discount'=>$i%5===0?50:0,'tax'=>0,'total'=>500+($i%12)*75-($i%5===0?50:0),'status'=>'paid','issued_at'=>$started,'created_at'=>$started,'updated_at'=>$started]);
                    DB::table('invoice_items')->insert(['invoice_id'=>$invoiceId,'item_type'=>'examination','description'=>'Uzman hekim muayenesi','quantity'=>1,'unit_price'=>500,'total'=>500,'created_at'=>$started,'updated_at'=>$started]);
                    DB::table('payments')->insert(['invoice_id'=>$invoiceId,'reference_no'=>'ODE-'.str_pad((string)$i,10,'0',STR_PAD_LEFT),'amount'=>500+($i%12)*75-($i%5===0?50:0),'method'=>['card','cash','insurance'][$i%3],'status'=>'completed','paid_at'=>$started,'created_at'=>$started,'updated_at'=>$started]);
                }
            });
        }
    }

    private function seedClinicalExtras($faker, int $target): void
    {
        $lastLab=(int)(DB::table('lab_orders')->max('visit_id') ?? 0);
        $lastPrescription=(int)(DB::table('prescriptions')->max('visit_id') ?? 0);
        $start=max(1,min($lastLab ?: 1,$lastPrescription ?: 1)-1);
        foreach (DB::table('visits')->whereBetween('id',[$start,$target])->orderBy('id')->cursor() as $visit) {
            if ($visit->id%2===0 && $visit->id>$lastLab) {
                $orderId=DB::table('lab_orders')->insertGetId(['uuid'=>(string)Str::uuid(),'visit_id'=>$visit->id,'ordered_by'=>$visit->doctor_id,'status'=>'completed','priority'=>$visit->id%7===0?'urgent':'routine','ordered_at'=>$visit->started_at,'created_at'=>$visit->started_at,'updated_at'=>$visit->started_at]);
                $itemId=DB::table('lab_order_items')->insertGetId(['lab_order_id'=>$orderId,'lab_test_catalog_id'=>(($visit->id-1)%4)+1,'status'=>'completed','created_at'=>$visit->started_at,'updated_at'=>$visit->started_at]);
                DB::table('lab_samples')->insert(['lab_order_item_id'=>$itemId,'barcode'=>'LAB-'.str_pad((string)$visit->id,12,'0',STR_PAD_LEFT),'collected_at'=>$visit->started_at,'received_at'=>$visit->started_at,'status'=>'analyzed','created_at'=>$visit->started_at,'updated_at'=>$visit->started_at]);
                DB::table('lab_results')->insert(['lab_order_item_id'=>$itemId,'result_value'=>(string)(50+($visit->id%100)),'unit'=>'mg/dL','reference_min'=>60,'reference_max'=>110,'abnormal'=>$visit->id%9===0,'resulted_at'=>$visit->started_at,'created_at'=>$visit->started_at,'updated_at'=>$visit->started_at]);
            }
            if ($visit->id%3===0 && $visit->id>$lastPrescription) {
                $prescriptionId=DB::table('prescriptions')->insertGetId(['uuid'=>(string)Str::uuid(),'visit_id'=>$visit->id,'doctor_id'=>$visit->doctor_id,'prescribed_at'=>$visit->started_at,'status'=>'issued','created_at'=>$visit->started_at,'updated_at'=>$visit->started_at]);
                DB::table('prescription_items')->insert(['prescription_id'=>$prescriptionId,'medication_id'=>(($visit->id-1)%12)+1,'dose'=>'1 tablet','frequency'=>'Günde 2 kez','duration_days'=>7,'instructions'=>'Tok karnına kullanınız.','created_at'=>$visit->started_at,'updated_at'=>$visit->started_at]);
            }
            if ($visit->visit_type==='emergency' && !DB::table('triage_records')->where('visit_id',$visit->id)->exists()) DB::table('triage_records')->insert(['visit_id'=>$visit->id,'priority'=>($visit->id%5)+1,'temperature'=>36.2+(($visit->id%18)/10),'pulse'=>65+($visit->id%45),'systolic'=>105+($visit->id%35),'diastolic'=>65+($visit->id%25),'oxygen_saturation'=>94+($visit->id%6),'measured_at'=>$visit->started_at]);
        }
    }
}
