<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HospitalController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('HospitalLab', [
            'overview' => $this->overview(),
            'tables' => $this->tables(),
            'relations' => $this->relations(),
            'exercises' => collect(config('training.exercises'))->map(fn ($e) => collect($e)->except('expected'))->values(),
        ]);
    }

    public function table(Request $request, string $table): JsonResponse
    {
        abort_unless($this->allowedTables()->contains($table), 404, 'Tablo bulunamadı.');
        $perPage = min(max((int)$request->integer('per_page', 15), 5), 50);
        $columns = DB::table('information_schema.columns')->where('table_schema','public')->where('table_name',$table)->orderBy('ordinal_position')->pluck('column_name');
        $query = DB::table($table);
        if ($search = trim((string)$request->query('search'))) {
            $textColumns = DB::table('information_schema.columns')->where('table_schema','public')->where('table_name',$table)->whereIn('data_type',['character varying','text','character'])->pluck('column_name');
            if ($textColumns->isNotEmpty()) $query->where(function ($q) use ($textColumns,$search) { foreach ($textColumns as $column) $q->orWhere($column,'ilike','%'.$search.'%'); });
        }
        $orderColumn = $columns->contains('id') ? 'id' : $columns->first();
        return response()->json(['columns'=>$columns,'rows'=>$query->orderBy($orderColumn)->paginate($perPage)]);
    }

    public function evaluate(Request $request): JsonResponse
    {
        $data=$request->validate(['exercise_id'=>'required|integer','sql'=>'required|string|max:8000']);
        $exercise=collect(config('training.exercises'))->firstWhere('id',(int)$data['exercise_id']);
        abort_unless($exercise,404);
        $sql=trim($data['sql']);
        if (! preg_match('/^(select|with)\b/i',$sql) || preg_match('/(;\s*\S|--|\/\*|\b(insert|update|delete|drop|alter|truncate|grant|revoke|copy|call|do)\b)/i',$sql)) {
            return response()->json(['correct'=>false,'message'=>'Bu alanda yalnızca tek bir SELECT veya WITH sorgusu çalıştırabilirsin.','hint'=>'Veriyi değiştirmeyen bir sorgu yaz.'],422);
        }
        try {
            return DB::transaction(function () use ($sql,$exercise) {
                DB::statement('SET TRANSACTION READ ONLY'); DB::statement("SET LOCAL statement_timeout = '3000ms'");
                $actual=DB::select("SELECT * FROM ({$sql}) AS learner_result LIMIT 501");
                $expected=DB::select("SELECT * FROM ({$exercise['expected']}) AS expected_result LIMIT 501");
                $correct=$this->canonical($actual)===$this->canonical($expected);
                return response()->json(['correct'=>$correct,'message'=>$correct?'Harika! Sonuç kümesi doğru.':'Sorgu çalıştı fakat sonuç beklenen sonuçla eşleşmiyor.','hint'=>$correct?null:$exercise['hint'],'row_count'=>count($actual),'preview'=>array_slice($actual,0,8)]);
            });
        } catch (\Throwable $e) {
            $message=preg_replace('/\s+/', ' ', $e->getMessage());
            return response()->json(['correct'=>false,'message'=>'PostgreSQL sorguyu çalıştıramadı: '.mb_strimwidth($message,0,320,'…'),'hint'=>$exercise['hint']],422);
        }
    }

    private function canonical(array $rows): array
    {
        $normalized=array_map(function ($row) { $values=(array)$row; ksort($values); return $values; },$rows);
        return $normalized;
    }

    private function overview(): array
    {
        $counts=[]; foreach (['patients','doctors','appointments','visits','lab_results','payments'] as $table) $counts[$table]=DB::table($table)->count();
        return ['counts'=>$counts,'table_count'=>$this->allowedTables()->count(),'database'=>'PostgreSQL '.DB::selectOne('SHOW server_version')->server_version,'profile'=>env('DATASET_PROFILE','small')];
    }

    private function allowedTables()
    {
        return DB::table('information_schema.tables')->where('table_schema','public')->where('table_type','BASE TABLE')->whereNotIn('table_name',['migrations','cache','cache_locks','jobs','job_batches','failed_jobs','sessions','password_reset_tokens','users'])->orderBy('table_name')->pluck('table_name');
    }

    private function tables(): array
    {
        $sizes=collect(DB::select("SELECT c.relname AS name, c.reltuples::bigint AS estimated_rows, pg_total_relation_size(c.oid) AS bytes FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relkind='r'"))->keyBy('name');
        return $this->allowedTables()->map(function($name) use($sizes) { $s=$sizes->get($name); return ['name'=>$name,'estimated_rows'=>max(0,(int)($s->estimated_rows??0)),'bytes'=>(int)($s->bytes??0)]; })->values()->all();
    }

    private function relations(): array
    {
        return array_map(fn($r)=>(array)$r,DB::select("SELECT tc.table_name AS source, kcu.column_name AS source_column, ccu.table_name AS target, ccu.column_name AS target_column FROM information_schema.table_constraints tc JOIN information_schema.key_column_usage kcu ON tc.constraint_name=kcu.constraint_name AND tc.table_schema=kcu.table_schema JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name=tc.constraint_name AND ccu.table_schema=tc.table_schema WHERE tc.constraint_type='FOREIGN KEY' AND tc.table_schema='public'"));
    }
}
