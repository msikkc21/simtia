<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use App\Http\Traits\HelperTrait;
use App\Http\Traits\PdfTrait;
use App\Http\Traits\ReferenceTrait;
use App\Http\Traits\DepartmentTrait;
use Modules\Academic\Entities\MemorizeCard;
use Modules\Academic\Http\Requests\MemorizeCardRequest;
use Modules\Academic\Repositories\Student\MemorizeCardEloquent;
use Carbon\Carbon;
use View;
use Exception;

class StudentMemorizeCardController extends Controller
{

    use HelperTrait;
    use PdfTrait;
    use ReferenceTrait;
    use DepartmentTrait;

    private $subject = 'Kartu Setoran Santri';

    function __construct(MemorizeCardEloquent $memorizeCardEloquent)
    {
        $this->memorizeCardEloquent = $memorizeCardEloquent;
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        if (!$request->ajax()) {
            abort(404);
        }
        $window = explode(".", $request->w);
        $data['InnerHeight'] = $window[0];
        $data['InnerWidth'] = $window[1];
        $data['ViewType'] = $request->t;
        //
        $data['surahs'] = DB::table('quran_surahs')->orderBy('id')->get()->map(function ($model) {
            $model->surah = $model->id . ' - ' . $model->surah . ' (' . $model->total . ' ayat)';
            return $model;
        });
        $data['departments'] = $this->listDepartment();
        return view('academic::pages.students.memorize_card', $data);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(MemorizeCardRequest $request)
    {
        $validated = $request->validated();
        try {
            $request->merge([
                'memorize_date' => $this->formatDate($request->memorize_date, 'sys'),
                'logged' => auth()->user()->email,
            ]);

            // validate max verse
            $students = [];

            for ($i = 0; $i < count($request->students); $i++) {
                if (
                    $request->students[$i]['from_surah'] != null &&
                    $request->students[$i]['from_verse'] != null &&
                    $request->students[$i]['to_surah'] != null &&
                    $request->students[$i]['to_verse'] != null
                ) {
                    $from_surah = $this->getSurah($request->students[$i]['from_surah']);
                    if ($request->students[$i]['from_verse'] > $from_surah->total) {
                        throw new Exception('Isian jumlah ayat di kolom Dari Ayat: (' . $request->students[$i]['from_verse'] . ') melebihi jumlah ayat di Surat ' . $from_surah->surah . ' (' . $from_surah->total . ' ayat)', 1);
                    }

                    $to_surah = $this->getSurah($request->students[$i]['to_surah']);
                    if ($request->students[$i]['to_verse'] > $to_surah->total) {
                        throw new Exception('Isian jumlah ayat di kolom Sampai Ayat: (' . $request->students[$i]['to_verse'] . ') melebihi jumlah ayat di Surat ' . $to_surah->surah . ' (' . $to_surah->total . ' ayat)', 1);
                    }

                    $students[] = array(
                        'id' => $request->students[$i]['id'],
                        'student_id' => $request->students[$i]['student_id'],
                        'from_surah' => $request->students[$i]['from_surah'],
                        'from_verse' => $request->students[$i]['from_verse'],
                        'to_surah' => $request->students[$i]['to_surah'],
                        'to_verse' => $request->students[$i]['to_verse'],
                        'status' => $request->students[$i]['status'],
                    );
                }
            }

            for ($i = 0; $i < count($students); $i++) {
                $request->merge([
                    'id' => $students[$i]['id'],
                    'student_id' => $students[$i]['student_id'],
                    'from_surah_id' => $students[$i]['from_surah'],
                    'to_surah_id' => $students[$i]['to_surah'],
                    'from_verse' => $students[$i]['from_verse'],
                    'to_verse' => $students[$i]['to_verse'],
                    'status' => $students[$i]['status'],
                ]);

                if ($request->id < 1) {
                    $this->memorizeCardEloquent->create($request, $this->subject);
                } else {
                    $this->memorizeCardEloquent->update($request, $this->subject);
                }
            }
            $response = $this->getResponse('store', '', $this->subject);
        } catch (\Throwable $e) {
            $response = $this->getResponse('error', $e->getMessage(), '');
        }
        return response()->json($response);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($class_id, $date)
    {
        return response()->json(MemorizeCard::where('class_id', $class_id)->where('memorize_date', $date)->get()->map(function ($model) {
            $model['student_no'] = $model->getStudent->student_no;
            $model['name'] = $model->getStudent->name;
            $model['department'] = $model->getClass->getGrade->getDepartment->name;
            $model['school_year'] = $model->getClass->getSchoolYear->school_year;
            $model['grade'] = $model->getClass->getGrade->grade;
            $model['semester'] = $model->getClass->getGrade->getSemesterByDept->semester;
            return $model;
        })[0]);
    }

    /**
     * Display a listing of data.
     * @return JSON
     */
    public function data(Request $request)
    {
        return response()->json($this->memorizeCardEloquent->data($request));
    }

    /**
     * Display a listing of data.
     * @return JSON
     */
    public function dataCard(Request $request)
    {
        return response()->json($this->memorizeCardEloquent->dataCard($request));
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy(Request $request)
    {
        try {
            for ($i = 0; $i < count($request->students); $i++) {
                if ($request->students[$i]['id'] > 0) {
                    $this->memorizeCardEloquent->destroy($request->students[$i]['id'], $this->subject);
                }
            }
            $response = $this->getResponse('destroy', '', $this->subject);
        } catch (\Throwable $e) {
            $response = $this->getResponse('error', $e->getMessage(), $this->subject);
        }
        return response()->json($response);
    }

    /**
     * Export resource to PDF Document.
     * @return PDF
     */
    public function print(Request $request)
    {
        $data['requests'] = json_decode($request->data);
        $data['profile'] = $this->getInstituteProfile();
        //
        $view = View::make('academic::pages.students.memorize_card_pdf', $data);
        $name = Str::lower(config('app.name')) . '_' . Str::of($this->subject)->snake();
        $hashfile = md5(date('Ymdhis') . '_' . $name);
        $filename = date('Ymdhis') . '_' . $name . '.pdf';
        // 
        Storage::disk('local')->put('public/tempo/' . $hashfile . '.html', $view->render());
        $this->pdfPortraits($hashfile, $filename);
        echo $filename;
    }

    /**
     * Export resource to PDF Document.
     * @return PDF
     */
    public function printForm(Request $request)
    {
        $payload = json_decode($request->data);
        $data['profile'] = $this->getInstituteProfile();
        //
        $view = View::make('academic::pages.students.memorize_card_form_pdf', $data);
        $name = Str::lower(config('app.name')) . '_form_' . Str::of($this->subject)->snake();
        $hashfile = md5(date('Ymdhis') . '_' . $name);
        $filename = date('Ymdhis') . '_' . $name . '.pdf';
        // 
        Storage::disk('local')->put('public/tempo/' . $hashfile . '.html', $view->render());
        $this->pdfPortrait($hashfile, $filename);
        echo $filename;
    }

    /**
     * Get all available classes
     * @return JSON
     */
    public function getClasses()
    {
        $classes = DB::table('academic.classes AS c')
            ->join('academic.grades AS g', 'c.grade_id', '=', 'g.id')
            ->join('academic.schoolyears AS s', 'c.schoolyear_id', '=', 's.id')
            ->select('c.id', 'c.class', 'g.grade', 's.school_year', 'g.department_id')
            ->where('g.is_active', 1)
            ->where('c.is_active', 1)
            ->orderBy('g.department_id')
            ->orderBy('g.grade')
            ->orderBy('c.class')
            ->get();

        return response()->json($classes);
    }

    /**
     * Get active semester by department ID
     * @param int $department_id
     * @return object
     */
    private function getSemesterByDept($department_id)
    {
        return DB::table('academic.semesters')
            ->where('department_id', $department_id)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Generate monthly recap of student memorizations
     * @param Request $request
     * @return PDF
     */
    public function printMonthlyRecap(Request $request)
    {
        try {
            // Parse request data
            $month = (int) $request->month;
            $class_id = (int) $request->class;
            $year = Carbon::now()->year;

            // Get start and end date of the specified month
            $startDate = Carbon::createFromDate($year, $month, 1)->format('Y-m-d');
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');

            // Get class information
            $class = DB::table('academic.classes AS c')
                ->join('academic.grades AS g', 'c.grade_id', '=', 'g.id')
                ->join('academic.schoolyears AS s', 'c.schoolyear_id', '=', 's.id')
                ->join('public.departments AS d', 'g.department_id', '=', 'd.id')
                ->select('c.id', 'c.class', 'g.grade', 's.school_year', 'd.name AS department', 'g.department_id')
                ->where('c.id', $class_id)
                ->first();

            if (!$class) {
                throw new Exception('Kelas tidak ditemukan.');
            }

            // Get all students in the specified class with their memorization data
            $students = DB::table('academic.students AS s')
                ->leftJoin('academic.memorize_cards AS m', function ($join) use ($startDate, $endDate) {
                    $join->on('s.id', '=', 'm.student_id')
                        ->whereBetween('m.memorize_date', [$startDate, $endDate]);
                })
                ->leftJoin('public.quran_surahs AS fs', 'm.from_surah_id', '=', 'fs.id')
                ->leftJoin('public.quran_surahs AS ts', 'm.to_surah_id', '=', 'ts.id')
                ->where('s.class_id', $class_id)
                ->where('s.is_active', 1)
                ->select(
                    's.id AS student_id',
                    's.student_no',
                    's.name',
                    DB::raw('MIN(m.from_surah_id) as from_surah'),
                    DB::raw('MIN(m.from_verse) as from_verse'),
                    DB::raw('MAX(m.to_surah_id) as to_surah'),
                    DB::raw('MAX(m.to_verse) as to_verse'),
                    DB::raw('COUNT(m.id) as total_sessions'),
                    DB::raw("STRING_AGG(DISTINCT m.status, ', ') as status")
                )
                ->groupBy('s.id', 's.student_no', 's.name')
                ->orderBy('s.name')
                ->get();

            // Set request data for the view
            $data['requests'] = (object) [
                'department' => $class->department,
                'class' => $class->class,
                'schoolyear' => $class->school_year,
                'grade' => $class->grade,
                'semester' => $this->getSemesterByDept($class->department_id)->semester,
                'employee' => auth()->user()->name,
                'memorize_date' => $startDate,
                'remark' => 'Rekap Bulan ' . Carbon::createFromDate($year, $month, 1)->locale('id')->monthName . ' ' . $year,
                'students' => $students
            ];

            // Get institute profile
            $data['profile'] = $this->getInstituteProfile();

            // Generate PDF
            $view = View::make('academic::pages.students.memorize_card_recap_pdf', $data);
            $name = Str::lower(config('app.name')) . '_rekap_hafalan_' . $month . '_' . $year;
            $hashfile = md5(date('Ymdhis') . '_' . $name);
            $filename = date('Ymdhis') . '_' . $name . '.pdf';

            // Render view to HTML and ensure proper formatting
            $html = $view->render();

            // Save HTML to storage
            Storage::disk('local')->put('public/tempo/' . $hashfile . '.html', $html);

            // Generate PDF with debug info
            $result = $this->pdfLandscape($hashfile, $filename);

            // Check if PDF file was created
            $pdfExists = Storage::disk('local')->exists('public/downloads/' . $filename);

            // Jika PDF tidak dibuat, gunakan HTML sebagai fallback
            if (!$pdfExists) {
                // Salin HTML ke direktori downloads sebagai fallback
                $htmlContent = Storage::disk('local')->get('public/tempo/' . $hashfile . '.html');
                Storage::disk('local')->put('public/downloads/' . $filename . '.html', $htmlContent);

                return response()->json([
                    'success' => true,
                    'message' => 'Rekap berhasil dibuat (HTML mode)',
                    'filename' => $filename . '.html',
                    'isHtml' => true,
                    'debug' => [
                        'pdfExists' => $pdfExists,
                        'shellResult' => $result,
                        'htmlPath' => storage_path('app/public/tempo/' . $hashfile . '.html'),
                        'pdfPath' => storage_path('app/public/downloads/' . $filename)
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Rekap berhasil dibuat',
                'filename' => $filename,
                'isHtml' => false,
                'debug' => [
                    'pdfExists' => $pdfExists,
                    'shellResult' => $result,
                    'htmlPath' => storage_path('app/public/tempo/' . $hashfile . '.html'),
                    'pdfPath' => storage_path('app/public/downloads/' . $filename)
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get all students data for combogrid
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudents()
    {
        $students = DB::table('academic.students AS s')
            ->join('academic.student_class_histories AS sch', 's.id', '=', 'sch.student_id')
            ->join('academic.classes AS c', 'sch.class_id', '=', 'c.id')
            ->where('sch.active', 1) // Berdasarkan definisi tabel, menggunakan kolom 'active' bukan 'is_active'
            ->where('s.is_active', 1)
            ->select('s.id', 's.student_no', 's.name', 'c.class')
            ->orderBy('s.student_no')
            ->get();
            
        return response()->json($students);
    }
    
    /**
     * Generate student memorization data
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function printStudentData(Request $request)
    {
        try {
            // Parse request data
            $month = (int) $request->month;
            $student_id = (int) $request->student;
            $year = Carbon::now()->year;

            // Get start and end date of the specified month
            $startDate = Carbon::createFromDate($year, $month, 1)->format('Y-m-d');
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');
            $monthName = Carbon::createFromDate($year, $month, 1)->locale('id')->monthName . ' ' . $year;

            // Get student information
            $student = DB::table('academic.students AS s')
                ->join('academic.student_class_histories AS sch', 's.id', '=', 'sch.student_id')
                ->join('academic.classes AS c', 'sch.class_id', '=', 'c.id')
                ->join('academic.grades AS g', 'c.grade_id', '=', 'g.id')
                ->join('academic.schoolyears AS sy', 'c.schoolyear_id', '=', 'sy.id')
                ->join('public.departments AS d', 'g.department_id', '=', 'd.id')
                ->where('s.id', $student_id)
                ->where('sch.active', 1) // Menggunakan kolom active, bukan is_active
                ->where('s.is_active', 1)
                ->select('s.id', 's.student_no', 's.name', 'c.class', 'g.grade', 'sy.school_year', 'g.department_id', 'd.name AS department')
                ->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Santri tidak ditemukan'
                ], 404);
            }

            // Get semester
            $semester = $this->getSemesterByDept($student->department_id);

            // Get student memorization data for the specified month
            $memorizations = DB::table('academic.memorize_cards')
                ->where('student_id', $student_id)
                ->whereBetween('memorize_date', [$startDate, $endDate])
                ->orderBy('memorize_date')
                ->get();

            // Set request data for the view
            $data['requests'] = (object) [
                'department' => $student->department,
                'schoolyear' => $student->school_year,
                'grade' => $student->grade,
                'semester' => $semester->semester,
                'class' => $student->class,
                'student_no' => $student->student_no,
                'student_name' => $student->name,
                'month_name' => $monthName,
                'memorizations' => $memorizations
            ];

            // Get institute profile
            $data['profile'] = $this->getInstituteProfile();

            // Generate PDF
            $view = View::make('academic::pages.students.memorize_card_student_pdf', $data);
            $name = Str::lower(config('app.name')) . '_data_hafalan_santri_' . $student->student_no . '_' . $month . '_' . $year;
            $hashfile = md5(date('Ymdhis') . '_' . $name);
            $filename = date('Ymdhis') . '_' . $name . '.pdf';

            // Save HTML to storage
            Storage::disk('local')->put('public/tempo/' . $hashfile . '.html', $view->render());

            // Generate PDF
            $this->pdfPortrait($hashfile, $filename);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dicetak',
                'filename' => $filename
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
